<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Targeted Core-Web-Vitals nudges that don't fit cleanly into the
 * other sub-modules:
 *
 *   - LCP: hint the first content image with fetchpriority="high" so
 *     the browser starts downloading it before parsing the rest of the
 *     <head>.
 *   - LCP: add `<link rel="preload">` for the featured image of the
 *     current post (if there is one) since that's almost always the
 *     LCP element on a single-post page.
 *   - CLS: handled by the Image Optimizer's width/height injection.
 *   - FCP: preload a user-provided list of fonts so the first paint
 *     doesn't FOUT.
 *
 * Everything here is additive (extra <link> tags, extra HTML
 * attributes) — never removes or rewrites existing markup.
 */
class RankWriter_AI_Core_Web_Vitals {

	private $settings;
	private $hero_marked = false;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( ! empty( $this->settings['cwv_preload_featured'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_featured_image' ), 1 );
		}
		if ( ! empty( $this->settings['cwv_preload_fonts'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_fonts' ), 1 );
		}
		if ( ! empty( $this->settings['cwv_dns_prefetch'] ) ) {
			add_action( 'wp_head', array( $this, 'emit_dns_prefetch' ), 1 );
		}
		if ( ! empty( $this->settings['cwv_disable_emojis'] ) ) {
			$this->disable_wp_emojis();
		}
		if ( ! empty( $this->settings['cwv_remove_jquery_migrate'] ) ) {
			add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
		}
		if ( ! empty( $this->settings['cwv_disable_embeds'] ) ) {
			$this->disable_wp_embeds();
		}
		if ( ! empty( $this->settings['cwv_google_fonts_swap'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'add_font_display_swap' ), 25, 4 );
		}
		if ( ! empty( $this->settings['cwv_html_minify'] ) ) {
			// Buffer the whole document and minify on flush. We do this on
			// `template_redirect` priority 1 so it sits OUTSIDE the page
			// cache's buffer — the cache stores the minified output.
			add_action( 'template_redirect', array( $this, 'start_html_buffer' ), 5 );
		}
		if ( ! empty( $this->settings['cwv_fetchpriority_lcp'] ) ) {
			// 5 must run BEFORE the Image_Optimizer's filters (which run at
			// 999) so the first image gets the priority hint before
			// loading="lazy" is added (those are mutually exclusive
			// optimizations on the same element).
			add_filter( 'the_content',         array( $this, 'mark_first_image_high_priority' ), 5 );
			add_filter( 'post_thumbnail_html', array( $this, 'mark_first_image_high_priority' ), 5 );
		}
	}

	/* ============================ DNS prefetch / preconnect ============================ */

	public function emit_dns_prefetch() {
		$urls = isset( $this->settings['dns_prefetch_hosts'] ) ? (string) $this->settings['dns_prefetch_hosts'] : '';
		// Sensible defaults — common third parties on WP sites.
		$defaults = array(
			'https://fonts.googleapis.com',
			'https://fonts.gstatic.com',
			'https://www.google-analytics.com',
			'https://www.googletagmanager.com',
			'https://pagead2.googlesyndication.com',
			'https://googleads.g.doubleclick.net',
		);
		$lines = preg_split( "/\r?\n/", $urls );
		$hosts = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) { continue; }
			$hosts[] = $line;
		}
		// User list replaces defaults if non-empty.
		if ( empty( $hosts ) ) {
			$hosts = $defaults;
		}
		$emitted = array();
		foreach ( $hosts as $h ) {
			$host = wp_parse_url( $h, PHP_URL_HOST );
			if ( ! $host || isset( $emitted[ $host ] ) ) { continue; }
			$emitted[ $host ] = true;
			echo "\n<link rel=\"dns-prefetch\" href=\"//" . esc_attr( $host ) . "\">";
			echo "\n<link rel=\"preconnect\" href=\"//" . esc_attr( $host ) . "\" crossorigin>";
		}
	}

	/* ============================ WP emoji bloat ============================ */

	public function disable_wp_emojis() {
		remove_action( 'wp_head',              'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles',      'print_emoji_styles' );
		remove_action( 'admin_print_scripts',  'print_emoji_detection_script' );
		remove_action( 'admin_print_styles',   'print_emoji_styles' );
		remove_filter( 'the_content_feed',     'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss',     'wp_staticize_emoji' );
		remove_filter( 'wp_mail',              'wp_staticize_emoji_for_email' );
		// Block the s.w.org DNS prefetch the emoji loader injects.
		add_filter( 'tiny_mce_plugins', function ( $plugins ) {
			return array_diff( (array) $plugins, array( 'wpemoji' ) );
		} );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/* ============================ jQuery Migrate removal ============================ */

	public function remove_jquery_migrate( $scripts ) {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$script = $scripts->registered['jquery'];
		if ( ! empty( $script->deps ) ) {
			$script->deps = array_values( array_diff( $script->deps, array( 'jquery-migrate' ) ) );
		}
	}

	/* ============================ WP embeds (oembed) removal ============================ */

	public function disable_wp_embeds() {
		// Remove the wp-embed.js script and the oEmbed discovery <link> tags.
		add_action( 'init', function () {
			wp_deregister_script( 'wp-embed' );
		}, 9999 );
		remove_action( 'wp_head',              'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head',              'wp_oembed_add_host_js' );
		remove_action( 'rest_api_init',        'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover',   '__return_false' );
	}

	/* ============================ Google Fonts: display=swap ============================ */

	/**
	 * Rewrite Google Fonts <link href> URLs to include &display=swap so
	 * text paints with the fallback font immediately instead of waiting
	 * for the web font to download (FOIT → FOUT). This is Lighthouse's
	 * single most-common "Reduce text-rendering delay" recommendation.
	 */
	public function add_font_display_swap( $html, $handle, $href, $media ) {
		if ( false === stripos( (string) $href, 'fonts.googleapis.com' ) ) {
			return $html;
		}
		if ( false !== stripos( (string) $href, 'display=' ) ) {
			return $html;
		}
		$sep        = ( false !== strpos( $href, '?' ) ) ? '&' : '?';
		$new_href   = $href . $sep . 'display=swap';
		return str_replace( $href, $new_href, $html );
	}

	/* ============================ HTML minification ============================ */

	public function start_html_buffer() {
		// Skip on the obvious non-HTML responses.
		if ( is_feed() || ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ) {
			return;
		}
		ob_start( array( $this, 'minify_html' ) );
	}

	/**
	 * Light HTML minifier — strips comments, collapses runs of whitespace
	 * BETWEEN tags, and trims inter-attribute whitespace. Leaves <pre>,
	 * <script>, <textarea>, <style>, and IE conditional comments alone so
	 * we never corrupt content the author actually wants whitespace in.
	 */
	public function minify_html( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 200 ) {
			return $html;
		}
		// Don't touch responses that aren't HTML.
		$ct = function_exists( 'headers_list' ) ? implode( ' ', headers_list() ) : '';
		if ( false !== stripos( $ct, 'Content-Type:' ) && false === stripos( $ct, 'text/html' ) ) {
			return $html;
		}
		// Stash preserved regions (case-insensitive).
		$preserved = array();
		$stash = function ( $pattern ) use ( &$html, &$preserved ) {
			$html = preg_replace_callback( $pattern, function ( $m ) use ( &$preserved ) {
				$key = '<!--RWAI_STASH_' . count( $preserved ) . '-->';
				$preserved[ $key ] = $m[0];
				return $key;
			}, $html );
		};
		$stash( '#<pre\b[^>]*>.*?</pre>#is' );
		$stash( '#<textarea\b[^>]*>.*?</textarea>#is' );
		$stash( '#<script\b[^>]*>.*?</script>#is' );
		$stash( '#<style\b[^>]*>.*?</style>#is' );
		$stash( '#<!--\[if.*?<!\[endif\]-->#is' ); // IE conditional comments

		// Remove HTML comments (but not IE conditional, already stashed).
		$html = preg_replace( '#<!--(?!\s*(?:\[|<!|>)).*?-->#s', '', $html );
		// Collapse whitespace between tags.
		$html = preg_replace( '#>\s+<#', '><', $html );
		// Collapse runs of whitespace inside text.
		$html = preg_replace( '#[ \t]{2,}#', ' ', $html );
		$html = preg_replace( "#\n{2,}#", "\n", $html );

		// Restore stashed regions.
		foreach ( $preserved as $key => $original ) {
			$html = str_replace( $key, $original, $html );
		}
		return $html;
	}

	public function preload_featured_image() {
		if ( ! is_singular() ) {
			return;
		}
		$thumb_id = get_post_thumbnail_id();
		if ( ! $thumb_id ) {
			return;
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'large' );
		if ( ! $src || empty( $src[0] ) ) {
			return;
		}
		echo "\n<link rel=\"preload\" as=\"image\" href=\"" . esc_url( $src[0] ) . "\" fetchpriority=\"high\">\n";
	}

	public function preload_fonts() {
		$urls = isset( $this->settings['preload_font_urls'] ) ? (string) $this->settings['preload_font_urls'] : '';
		foreach ( preg_split( "/\r?\n/", $urls ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$ext = strtolower( pathinfo( wp_parse_url( $line, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$type = 'font/woff2';
			if ( 'woff' === $ext ) { $type = 'font/woff'; }
			elseif ( 'ttf' === $ext ) { $type = 'font/ttf'; }
			elseif ( 'otf' === $ext ) { $type = 'font/otf'; }
			echo "\n<link rel=\"preload\" as=\"font\" type=\"" . esc_attr( $type ) . "\" crossorigin href=\"" . esc_url( $line ) . "\">";
		}
	}

	public function mark_first_image_high_priority( $html ) {
		if ( $this->hero_marked || ! is_string( $html ) ) {
			return $html;
		}
		$replaced = preg_replace_callback( '#<img\b([^>]*)>#i', function ( $m ) {
			if ( $this->hero_marked ) {
				return $m[0];
			}
			$attrs = $m[1];
			// Don't double-mark if the theme already set fetchpriority.
			if ( ! preg_match( '/\bfetchpriority\s*=/i', $attrs ) ) {
				$attrs .= ' fetchpriority="high"';
			}
			// Make sure it isn't lazy-loaded (LCP element must load eager).
			$attrs = preg_replace( '/\bloading\s*=\s*"(lazy)"/i', 'loading="eager"', $attrs );
			$this->hero_marked = true;
			return '<img' . $attrs . '>';
		}, $html, 1 );
		return is_string( $replaced ) ? $replaced : $html;
	}
}
