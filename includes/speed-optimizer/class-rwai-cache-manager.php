<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static HTML page cache.
 *
 * Strategy:
 *   - On every front-end request, decide if the URL is cacheable. If a
 *     cached HTML file already exists, serve it and exit. Otherwise
 *     start output buffering and write the rendered HTML to disk on
 *     shutdown so the next visitor gets the fast path.
 *   - Bypass cache for: logged-in users, admin, REST, AJAX, cron, POST,
 *     query-string URLs, AMP, search, WooCommerce cart/checkout/account,
 *     and any URL in the user's exclusion list.
 *   - Auto-clear when content/structure changes (save_post, comment_post,
 *     wp_update_nav_menu, customize_save_after, switch_theme,
 *     updated_option for known content-affecting keys).
 *   - Cache files live in `wp-content/cache/rwai-speed/` so they survive
 *     plugin updates and are easy for any admin to wipe by hand.
 */
class RankWriter_AI_Cache_Manager {

	private $cache_dir;
	private $excluded_patterns;
	private $settings;

	public function __construct( $cache_dir, array $settings ) {
		$this->cache_dir         = rtrim( $cache_dir, '/\\' );
		$this->settings          = $settings;
		$this->excluded_patterns = $this->build_exclusion_patterns( $settings );
	}

	public function register_hooks() {
		if ( empty( $this->settings['cache_enabled'] ) ) {
			return;
		}
		// Serve from cache as early as possible.
		add_action( 'init', array( $this, 'maybe_serve_from_cache' ), 0 );
		// Buffer the page output and write to disk.
		add_action( 'template_redirect', array( $this, 'start_buffering' ), 0 );

		// Auto-invalidation hooks. Keep the trigger list tight so we don't
		// thrash the cache on unrelated option writes.
		add_action( 'save_post',            array( $this, 'purge_post' ), 10, 1 );
		add_action( 'deleted_post',         array( $this, 'purge_post' ), 10, 1 );
		add_action( 'comment_post',         array( $this, 'purge_all' ) );
		add_action( 'wp_set_comment_status',array( $this, 'purge_all' ) );
		add_action( 'wp_update_nav_menu',   array( $this, 'purge_all' ) );
		add_action( 'customize_save_after', array( $this, 'purge_all' ) );
		add_action( 'switch_theme',         array( $this, 'purge_all' ) );
		add_action( 'activated_plugin',     array( $this, 'purge_all' ) );
		add_action( 'deactivated_plugin',   array( $this, 'purge_all' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge_all' ) );
	}

	/**
	 * Decide if THIS request is cacheable. Errs heavily on the side of
	 * not caching — the whole point of the safe rules is to avoid
	 * serving the wrong thing to the wrong visitor.
	 */
	public function is_cacheable_request() {
		if ( is_admin() ) {
			return false;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return false;
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			return false;
		}
		// Bypass query-string URLs by default — they're often tracking
		// params (utm_*), pagination, search, or filtered archive views.
		if ( ! empty( $_GET ) && empty( $this->settings['cache_with_query'] ) ) {
			return false;
		}
		if ( ! empty( $_GET['nocache'] ) || ! empty( $_GET['preview'] ) ) {
			return false;
		}
		// WooCommerce / EDD dynamic pages.
		if ( $this->is_dynamic_woocommerce_page() ) {
			return false;
		}
		// AMP variant.
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return false;
		}
		// User-configured exclusions.
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		foreach ( $this->excluded_patterns as $pattern ) {
			if ( '' === $pattern ) { continue; }
			if ( false !== stripos( $uri, $pattern ) ) {
				return false;
			}
		}
		// Common WP routes we never cache.
		if ( preg_match( '#/(wp-login|wp-admin|wp-cron|xmlrpc\.php|robots\.txt|sitemap.*\.xml)#i', $uri ) ) {
			return false;
		}
		// Logged-in cookie shortcut (handles edge cases where is_user_logged_in fires late).
		foreach ( array_keys( $_COOKIE ?? array() ) as $name ) {
			if ( preg_match( '#^(wp-postpass|wordpress_logged_in|comment_author|woocommerce_items_in_cart|edd_items_in_cart)#i', $name ) ) {
				return false;
			}
		}
		return true;
	}

	private function is_dynamic_woocommerce_page() {
		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}
		return is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url();
	}

	private function build_exclusion_patterns( array $settings ) {
		$raw = isset( $settings['cache_exclusions'] ) ? (string) $settings['cache_exclusions'] : '';
		$lines = preg_split( "/\r?\n/", $raw );
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) { continue; }
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Serve the cached HTML if present and exit. Skips all WP processing,
	 * which is where the speed-up comes from.
	 */
	public function maybe_serve_from_cache() {
		if ( ! $this->is_cacheable_request() ) {
			return;
		}
		$path = $this->path_for_url( $this->current_url() );
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return;
		}
		// TTL — re-render if older than the configured value (default 12h).
		$ttl = max( 60, (int) ( $this->settings['cache_ttl'] ?? 43200 ) );
		if ( ( filemtime( $path ) + $ttl ) < time() ) {
			@unlink( $path );
			return;
		}
		$contents = @file_get_contents( $path );
		if ( false === $contents ) {
			return;
		}
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		header( 'X-RWAI-Cache: HIT' );
		header( 'Cache-Control: public, max-age=' . $ttl );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function start_buffering() {
		if ( ! $this->is_cacheable_request() ) {
			return;
		}
		ob_start( array( $this, 'capture_output' ) );
	}

	/**
	 * Called by ob_start on flush. Returns the content unchanged but
	 * writes it to disk in passing. Refuses to cache obviously broken
	 * pages (5xx, "Error" responses, suspiciously short).
	 */
	public function capture_output( $html ) {
		// Drop on HTTP error codes.
		$status = function_exists( 'http_response_code' ) ? (int) http_response_code() : 200;
		if ( $status >= 400 ) {
			return $html;
		}
		// Don't cache obviously broken / empty pages.
		if ( ! is_string( $html ) || strlen( $html ) < 200 ) {
			return $html;
		}
		// Final cacheability re-check (some plugins set cookies during render).
		if ( ! $this->is_cacheable_request() ) {
			return $html;
		}
		$path = $this->path_for_url( $this->current_url() );
		if ( ! is_string( $path ) ) {
			return $html;
		}
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$footer = "\n<!-- rwai-cache: " . esc_html( gmdate( 'c' ) ) . " -->";
		@file_put_contents( $path, $html . $footer, LOCK_EX );
		return $html;
	}

	private function current_url() {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = $_SERVER['HTTP_HOST'] ?? wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = $_SERVER['REQUEST_URI'] ?? '/';
		return $scheme . '://' . $host . $uri;
	}

	/**
	 * Map a URL to a cache file path. The hash gives us safe filenames
	 * and avoids the deep directory trees other cache plugins generate.
	 */
	public function path_for_url( $url ) {
		$url = preg_replace( '#\?.*$#', '', (string) $url );
		if ( '' === $url ) {
			return null;
		}
		$hash = md5( $url );
		// Two-level fan-out so a single dir never holds millions of files.
		return $this->cache_dir . '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 ) . '/' . $hash . '.html';
	}

	public function purge_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$url = get_permalink( $post_id );
		if ( $url ) {
			$path = $this->path_for_url( $url );
			if ( $path && file_exists( $path ) ) {
				@unlink( $path );
			}
		}
		// Also purge the homepage and feeds (cheap; usually empty).
		$home = $this->path_for_url( home_url( '/' ) );
		if ( $home && file_exists( $home ) ) {
			@unlink( $home );
		}
	}

	public function purge_all() {
		$this->rrmdir_contents( $this->cache_dir );
		RankWriter_AI_Speed_Logger::log( 'cache_purge_all', 'Cleared all cached pages.', 'info' );
	}

	public function size_bytes() {
		if ( ! is_dir( $this->cache_dir ) ) {
			return 0;
		}
		$total = 0;
		$iter  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->cache_dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iter as $file ) {
			if ( $file->isFile() ) {
				$total += $file->getSize();
			}
		}
		return $total;
	}

	public function file_count() {
		if ( ! is_dir( $this->cache_dir ) ) {
			return 0;
		}
		$count = 0;
		$iter  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->cache_dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iter as $file ) {
			if ( $file->isFile() && '.html' === substr( $file->getFilename(), -5 ) ) {
				$count++;
			}
		}
		return $count;
	}

	private function rrmdir_contents( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iter  = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() );
			} else {
				@unlink( $f->getPathname() );
			}
		}
	}
}
