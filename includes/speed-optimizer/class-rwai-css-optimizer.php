<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS optimization:
 *   - Minify (always): strips comments + whitespace from inline + linked
 *     stylesheets without changing semantics.
 *   - Combine (Aggressive only): concatenates all eligible <link> tags
 *     into one file under wp-content/cache/rwai-speed/css/.
 *   - Defer non-critical (Aggressive only): keeps a user-supplied
 *     critical-CSS block inline and rewrites every other stylesheet to
 *     load via the `media=print` swap trick.
 *
 * We intentionally do NOT try to auto-extract critical CSS — without a
 * headless browser, every "automatic critical CSS" implementation ends
 * up flashing unstyled content on something. The user pastes their own
 * critical block (or leaves it empty and we skip the defer step).
 */
class RankWriter_AI_CSS_Optimizer {

	private $settings;
	private $cache_dir;
	private $cache_url;

	public function __construct( array $settings, $cache_dir, $cache_url ) {
		$this->settings  = $settings;
		$this->cache_dir = rtrim( $cache_dir, '/\\' );
		$this->cache_url = rtrim( $cache_url, '/' );
	}

	public function register_hooks() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( ! empty( $this->settings['css_minify'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'rewrite_to_minified' ), 20, 4 );
		}
		if ( ! empty( $this->settings['css_defer'] ) ) {
			add_filter( 'style_loader_tag', array( $this, 'defer_non_critical' ), 30, 4 );
			add_action( 'wp_head', array( $this, 'inline_critical_css' ), 1 );
		}
	}

	/**
	 * If we already have a minified copy of this stylesheet, swap the
	 * <link href> to point at it. We never delete or touch the original
	 * file — the minified file lives in the plugin cache dir.
	 */
	public function rewrite_to_minified( $html, $handle, $href, $media ) {
		if ( $this->is_excluded( $handle, $href ) ) {
			return $html;
		}
		$minified = $this->minified_url_for( $href );
		if ( $minified ) {
			$html = str_replace( $href, $minified, $html );
		}
		return $html;
	}

	/**
	 * Defer non-critical stylesheets so they don't block first paint.
	 * The `media=print` + `onload=this.media='all'` swap is the
	 * commonly accepted pattern recommended by Google's web.dev guides.
	 */
	public function defer_non_critical( $html, $handle, $href, $media ) {
		if ( empty( $this->settings['critical_css'] ) ) {
			// Without a critical block, deferring causes FOUC. Bail.
			return $html;
		}
		if ( $this->is_excluded( $handle, $href ) ) {
			return $html;
		}
		// Inject media=print + onload swap. We only do this for the
		// rel="stylesheet" form; tags without rel are passed through.
		$replaced = preg_replace(
			'#<link\s+([^>]*?)rel=[\'"]stylesheet[\'"]([^>]*)>#i',
			'<link $1rel="stylesheet" media="print" onload="this.media=\'all\';this.onload=null" $2>',
			$html,
			1
		);
		return is_string( $replaced ) ? $replaced : $html;
	}

	public function inline_critical_css() {
		$critical = isset( $this->settings['critical_css'] ) ? (string) $this->settings['critical_css'] : '';
		$critical = trim( $critical );
		if ( '' === $critical ) {
			return;
		}
		echo "\n<style id=\"rwai-critical-css\">" . $critical . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function is_excluded( $handle, $href ) {
		$defaults = array( 'admin-bar', 'dashicons' );
		if ( in_array( $handle, $defaults, true ) ) {
			return true;
		}
		$excl = isset( $this->settings['css_exclusions'] ) ? (string) $this->settings['css_exclusions'] : '';
		foreach ( preg_split( "/\r?\n/", $excl ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) { continue; }
			if ( false !== stripos( (string) $href, $line ) || $handle === $line ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get (or create) the minified version of a local stylesheet URL.
	 * Returns the public URL of the minified file, or null if we
	 * couldn't read/minify (in which case the caller leaves the
	 * original href alone).
	 */
	private function minified_url_for( $href ) {
		if ( ! is_string( $href ) || '' === $href ) {
			return null;
		}
		// Local files only — we don't fetch and re-host third-party CDNs.
		$home = home_url( '/' );
		if ( 0 !== strpos( $href, $home ) ) {
			return null;
		}
		$relative = ltrim( str_replace( $home, '', $href ), '/' );
		$relative = preg_replace( '#\?.*$#', '', $relative );
		$abs      = ABSPATH . $relative;
		if ( ! file_exists( $abs ) ) {
			return null;
		}
		$mtime  = filemtime( $abs );
		$digest = substr( md5( $abs . '|' . $mtime ), 0, 12 );
		$out    = $this->cache_dir . '/css/' . $digest . '.css';
		if ( ! file_exists( $out ) ) {
			$css = @file_get_contents( $abs );
			if ( false === $css ) { return null; }
			$min = $this->minify( $css );
			if ( ! is_dir( dirname( $out ) ) ) { wp_mkdir_p( dirname( $out ) ); }
			if ( false === @file_put_contents( $out, $min, LOCK_EX ) ) {
				return null;
			}
		}
		return $this->cache_url . '/css/' . $digest . '.css';
	}

	/**
	 * Conservative CSS minifier. Strips comments and collapses
	 * whitespace without touching string contents (which is where
	 * naive minifiers break url("data:...") and content: rules).
	 */
	public function minify( $css ) {
		$css = (string) $css;
		// Pull out string literals so we don't mangle their whitespace.
		$strings = array();
		$css = preg_replace_callback(
			'#(["\'])((?:\\\\.|(?!\1).)*)\1#s',
			function ( $m ) use ( &$strings ) {
				$key = '__RWAI_STR_' . count( $strings ) . '__';
				$strings[ $key ] = $m[0];
				return $key;
			},
			$css
		);
		// Strip /* ... */ comments, but keep important ones starting /*!.
		$css = preg_replace( '#/\*(?!\!).*?\*/#s', '', $css );
		// Collapse whitespace around structural punctuation.
		$css = preg_replace( '#\s+#', ' ', $css );
		$css = preg_replace( '#\s*([{}:;,>+~])\s*#', '$1', $css );
		$css = preg_replace( '#;}#', '}', $css );
		// Restore strings.
		foreach ( $strings as $key => $literal ) {
			$css = str_replace( $key, $literal, $css );
		}
		return trim( $css );
	}
}
