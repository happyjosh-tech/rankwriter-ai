<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-updater that hooks into WordPress' standard plugin-update machinery
 * and pulls releases directly from this plugin's GitHub repository.
 *
 * How it works:
 *   1. On the `pre_set_site_transient_update_plugins` filter, fetch the
 *      latest release from GitHub's Releases API (cached for 12h).
 *   2. Compare the release tag (stripped of the leading "v") to the
 *      current plugin header version.
 *   3. If newer, inject our update entry into WordPress' update list.
 *      The WordPress admin then shows the standard "Update Available"
 *      prompt with a one-click "Update Now" button.
 *   4. When the user clicks update, WordPress downloads the ZIP attached
 *      to the GitHub release and installs it like any other plugin update.
 *
 * Requirements:
 *   - The GitHub repository must be PUBLIC (the GitHub API endpoint we
 *     hit requires no auth for public repos; private repos would need a
 *     personal access token from every user, which defeats the purpose).
 *   - Each release must attach an asset named `rankwriter-ai.zip` (the
 *     GitHub Actions workflow in this repo does that automatically).
 *
 * Configuration: the repo URL is set in rankwriter-ai.php via the constants
 *   RWAI_GITHUB_USER / RWAI_GITHUB_REPO. Change those two values to point
 *   at a fork, mirror, or rename without touching this file.
 */
class RankWriter_AI_GitHub_Updater {

	const TRANSIENT      = 'rwai_github_release_cache';
	const TTL            = 12 * HOUR_IN_SECONDS;
	const USER_AGENT     = 'RankWriter-AI-Updater';
	const SLUG           = 'rankwriter-ai';

	private $user;
	private $repo;
	private $plugin_file;     // absolute path to main plugin file
	private $plugin_basename; // "rankwriter-ai/rankwriter-ai.php"
	private $current_version;

	public function __construct( $user, $repo, $plugin_file ) {
		$this->user            = $user;
		$this->repo            = $repo;
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data                  = get_plugin_data( $plugin_file, false, false );
		$this->current_version = isset( $data['Version'] ) ? $data['Version'] : '0.0.0';
	}

	public function register_hooks() {
		// Inject our release into WP's update list.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );

		// Power the "View details" popup.
		add_filter( 'plugins_api', array( $this, 'plugins_api_details' ), 20, 3 );

		// Refresh the cached release info after a successful update.
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );

		// Add a "Check for updates" row action on the Plugins screen.
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_check_now_link' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_check_now' ) );

		// Settling: when WordPress finishes installing the ZIP, the new
		// folder name may differ from the original (GitHub names assets
		// with the release version). Rename it back so the plugin keeps
		// loading from the same path.
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Fetch (with transient caching) the latest release from GitHub.
	 *
	 * @param bool $force Bypass the transient and re-fetch.
	 * @return array|null Normalized release data or null on failure.
	 */
	public function get_latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $this->user ), rawurlencode( $this->repo ) );

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => self::USER_AGENT,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			// Cache an empty result briefly so we don't hammer the API on errors.
			set_site_transient( self::TRANSIENT, array( '_error' => $code ), HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		$tag         = (string) $body['tag_name'];
		$version     = ltrim( $tag, 'vV' );
		$zip_url     = '';
		$zip_size    = 0;
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && self::SLUG . '.zip' === $asset['name'] ) {
					$zip_url  = isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
					$zip_size = isset( $asset['size'] ) ? (int) $asset['size'] : 0;
					break;
				}
			}
		}
		// Fallback to the GitHub-generated zipball if no plugin ZIP asset attached.
		if ( '' === $zip_url ) {
			$zip_url = isset( $body['zipball_url'] ) ? $body['zipball_url'] : '';
		}

		$normalized = array(
			'tag'          => $tag,
			'version'      => $version,
			'name'         => isset( $body['name'] ) ? $body['name'] : ( 'RankWriter AI ' . $tag ),
			'body'         => isset( $body['body'] ) ? $body['body'] : '',
			'published_at' => isset( $body['published_at'] ) ? $body['published_at'] : '',
			'zip_url'      => $zip_url,
			'zip_size'     => $zip_size,
			'html_url'     => isset( $body['html_url'] ) ? $body['html_url'] : '',
		);

		set_site_transient( self::TRANSIENT, $normalized, self::TTL );
		return $normalized;
	}

	/**
	 * Hook: inject our release as an available update if the tag is newer
	 * than the installed plugin's header version.
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! is_array( $release ) || empty( $release['version'] ) || empty( $release['zip_url'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->current_version, '<=' ) ) {
			// Already up to date or older — ensure no stale update entry.
			if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
				unset( $transient->response[ $this->plugin_basename ] );
			}
			$transient->no_update[ $this->plugin_basename ] = $this->build_update_object( $release );
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = $this->build_update_object( $release );
		return $transient;
	}

	private function build_update_object( $release ) {
		$obj                = new stdClass();
		$obj->slug          = self::SLUG;
		$obj->plugin        = $this->plugin_basename;
		$obj->new_version   = $release['version'];
		$obj->url           = $release['html_url'];
		$obj->package       = $release['zip_url'];
		$obj->tested        = '6.6';
		$obj->requires_php  = '7.4';
		$obj->compatibility = new stdClass();
		return $obj;
	}

	/**
	 * Power the WP "View version details" popup with the release notes.
	 */
	public function plugins_api_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! is_array( $release ) ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'RankWriter AI';
		$info->slug          = self::SLUG;
		$info->version       = $release['version'];
		$info->author        = '<a href="' . esc_url( 'https://github.com/' . $this->user ) . '">' . esc_html( $this->user ) . '</a>';
		$info->homepage      = $release['html_url'] ?: ( 'https://github.com/' . $this->user . '/' . $this->repo );
		$info->requires      = '6.0';
		$info->tested        = '6.6';
		$info->requires_php  = '7.4';
		$info->last_updated  = $release['published_at'];
		$info->download_link = $release['zip_url'];
		$info->trunk         = $release['zip_url'];

		$info->sections = array(
			'description' => 'AI-powered WordPress content generator that learns from your existing blog and ships fresh, SEO-optimized articles via the Claude API.',
			'changelog'   => $this->format_release_notes( $release['body'] ),
		);

		return $info;
	}

	private function format_release_notes( $markdown ) {
		$markdown = (string) $markdown;
		if ( '' === trim( $markdown ) ) {
			return '<p>See <a href="' . esc_url( 'https://github.com/' . $this->user . '/' . $this->repo . '/releases' ) . '">the GitHub releases page</a> for change history.</p>';
		}
		// Lightweight markdown → HTML for the changelog popup.
		$lines = preg_split( "/\r?\n/", $markdown );
		$out   = '';
		$in_ul = false;
		foreach ( $lines as $line ) {
			$line = rtrim( $line );
			if ( '' === $line ) {
				if ( $in_ul ) {
					$out  .= '</ul>';
					$in_ul = false;
				}
				continue;
			}
			if ( preg_match( '/^####\s+(.+)$/', $line, $m ) ) {
				if ( $in_ul ) {
					$out  .= '</ul>';
					$in_ul = false;
				}
				$out .= '<h4>' . esc_html( $m[1] ) . '</h4>';
			} elseif ( preg_match( '/^###\s+(.+)$/', $line, $m ) ) {
				if ( $in_ul ) {
					$out  .= '</ul>';
					$in_ul = false;
				}
				$out .= '<h3>' . esc_html( $m[1] ) . '</h3>';
			} elseif ( preg_match( '/^##\s+(.+)$/', $line, $m ) ) {
				if ( $in_ul ) {
					$out  .= '</ul>';
					$in_ul = false;
				}
				$out .= '<h2>' . esc_html( $m[1] ) . '</h2>';
			} elseif ( preg_match( '/^[-*]\s+(.+)$/', $line, $m ) ) {
				if ( ! $in_ul ) {
					$out  .= '<ul>';
					$in_ul = true;
				}
				$out .= '<li>' . esc_html( $m[1] ) . '</li>';
			} else {
				if ( $in_ul ) {
					$out  .= '</ul>';
					$in_ul = false;
				}
				$out .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
		if ( $in_ul ) {
			$out .= '</ul>';
		}
		return $out;
	}

	public function clear_cache_after_update( $upgrader, $options ) {
		if ( ! is_array( $options ) ) {
			return;
		}
		if ( isset( $options['action'] ) && 'update' === $options['action']
			&& isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_site_transient( self::TRANSIENT );
		}
	}

	public function add_check_now_link( $links ) {
		$nonce = wp_create_nonce( 'rwai_force_update_check' );
		$url   = add_query_arg(
			array(
				'rwai_force_update_check' => '1',
				'_wpnonce'                => $nonce,
			),
			admin_url( 'plugins.php' )
		);
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'rankwriter-ai' ) . '</a>';
		return $links;
	}

	public function maybe_handle_check_now() {
		if ( empty( $_GET['rwai_force_update_check'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rwai_force_update_check' ) ) {
			return;
		}
		delete_site_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * GitHub release ZIPs sometimes unpack into a folder named e.g.
	 * "rankwriter-ai-1.0.1" instead of just "rankwriter-ai". WordPress
	 * would then activate the new folder as a "different" plugin and
	 * leave the old one orphaned. Rename the unpacked folder back to
	 * the canonical slug.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::SLUG;
		if ( trailingslashit( $source ) === trailingslashit( $desired ) ) {
			return $source;
		}

		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return trailingslashit( $desired );
		}
		return $source;
	}
}
