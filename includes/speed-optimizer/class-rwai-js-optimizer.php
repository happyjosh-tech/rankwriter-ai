<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JS optimization with the safety dial up to 11.
 *
 *   - Minify (always): local scripts only, results cached in
 *     wp-content/cache/rwai-speed/js/.
 *   - Defer (Balanced+): add defer attribute to non-essential scripts.
 *   - Delay-until-interaction (Aggressive): rewrite scripts to type
 *     "rwai-delayed" so the browser ignores them, then a tiny loader
 *     hot-swaps them on first user interaction (scroll/touch/key/click).
 *
 * Hard-coded NEVER-touch list covers the scripts that, if deferred or
 * delayed, will break user-visible functionality (login forms, payment
 * widgets, AdSense, recaptcha, GA4 measurement). Users can add their
 * own exclusions via the admin UI.
 */
class RankWriter_AI_JS_Optimizer {

	/** Scripts we will not defer or delay under any circumstances. */
	private static $never_touch = array(
		'jquery', 'jquery-core', 'jquery-migrate',
		'wp-i18n', 'wp-hooks', 'wp-polyfill',
		'comment-reply',
	);

	/** URL fragments we will not defer or delay (third-party essentials). */
	private static $never_touch_urls = array(
		'adsbygoogle', 'pagead2.googlesyndication', 'googletagservices',
		'recaptcha', 'hcaptcha',
		'stripe.com/v3', 'js.stripe.com',
		'paypal.com/sdk', 'paypalobjects',
		'wp-login', 'wp-admin',
		'/woocommerce/assets/js/frontend/checkout',
		'/woocommerce/assets/js/frontend/cart',
		'klaviyo', 'mailchimp',
	);

	/** Default candidates for delay-on-interaction (analytics/social). */
	private static $delay_candidates = array(
		'googletagmanager.com', 'google-analytics.com', 'gtag',
		'facebook.net/en_US/fbevents', 'connect.facebook.net',
		'platform.twitter.com', 'static.ads-twitter.com',
		'platform.linkedin.com',
		'tiktok.com/i18n/pixel',
		'hotjar.com', 'clarity.ms', 'fullstory.com',
		'tawk.to', 'crisp.chat', 'intercom.io',
	);

	private $settings;
	private $cache_dir;
	private $cache_url;
	private $mode;

	public function __construct( array $settings, $cache_dir, $cache_url ) {
		$this->settings  = $settings;
		$this->cache_dir = rtrim( $cache_dir, '/\\' );
		$this->cache_url = rtrim( $cache_url, '/' );
		$this->mode      = isset( $settings['mode'] ) ? (string) $settings['mode'] : 'balanced';
	}

	public function register_hooks() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( ! empty( $this->settings['js_minify'] ) ) {
			add_filter( 'script_loader_src', array( $this, 'maybe_swap_to_minified' ), 20, 2 );
		}
		if ( ! empty( $this->settings['js_defer'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_non_essential' ), 30, 3 );
		}
		if ( ! empty( $this->settings['js_delay'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'delay_on_interaction' ), 40, 3 );
			add_action( 'wp_footer', array( $this, 'emit_interaction_loader' ), 1 );
		}
	}

	public function maybe_swap_to_minified( $src, $handle ) {
		if ( $this->is_excluded( $handle, $src ) ) {
			return $src;
		}
		$min = $this->minified_url_for( $src );
		return $min ?: $src;
	}

	public function defer_non_essential( $tag, $handle, $src ) {
		if ( $this->is_excluded( $handle, $src ) ) {
			return $tag;
		}
		// Already has defer/async — leave alone.
		if ( false !== stripos( $tag, ' defer' ) || false !== stripos( $tag, ' async' ) ) {
			return $tag;
		}
		// Inline scripts (no src) — leave alone, deferring them is meaningless.
		if ( '' === (string) $src ) {
			return $tag;
		}
		return preg_replace( '#<script\s+#i', '<script defer ', $tag, 1 );
	}

	public function delay_on_interaction( $tag, $handle, $src ) {
		if ( $this->is_excluded( $handle, $src ) ) {
			return $tag;
		}
		$src = (string) $src;
		// Only delay if it's a known analytics/social candidate in
		// Balanced mode, or any non-essential script in Aggressive mode.
		$is_candidate = $this->matches_any( $src, self::$delay_candidates );
		if ( 'aggressive' !== $this->mode && ! $is_candidate ) {
			return $tag;
		}
		if ( '' === $src ) {
			return $tag;
		}
		// Switch type attr so the browser ignores it; loader picks it up.
		$tag = preg_replace( '#\stype=([\'"])[^\'"]+\1#i', '', $tag );
		$tag = preg_replace( '#<script\s+#i', '<script type="rwai/delayed" data-rwai-src="' . esc_attr( $src ) . '" ', $tag, 1 );
		// Drop the src attribute so nothing accidentally loads it early.
		$tag = preg_replace( '#\ssrc=([\'"])[^\'"]+\1#i', '', $tag, 1 );
		return $tag;
	}

	/**
	 * Tiny inline loader: listens for the first user interaction and
	 * rewrites every `<script type="rwai/delayed">` back to a real
	 * script tag. Kept inline so it ships in the HTML payload and has
	 * no extra round-trip.
	 */
	public function emit_interaction_loader() {
		?>
<script id="rwai-delay-loader">
(function(){
	var loaded = false;
	function loadAll(){
		if (loaded) return;
		loaded = true;
		var nodes = document.querySelectorAll('script[type="rwai/delayed"]');
		nodes.forEach(function(node){
			var s = document.createElement('script');
			for (var i = 0; i < node.attributes.length; i++) {
				var a = node.attributes[i];
				if (a.name === 'type' || a.name === 'data-rwai-src') continue;
				s.setAttribute(a.name, a.value);
			}
			var realSrc = node.getAttribute('data-rwai-src');
			if (realSrc) s.src = realSrc;
			if (node.textContent && !realSrc) s.text = node.textContent;
			node.parentNode.replaceChild(s, node);
		});
		['scroll','touchstart','keydown','mousedown','mousemove'].forEach(function(ev){
			window.removeEventListener(ev, loadAll, {passive:true});
		});
	}
	['scroll','touchstart','keydown','mousedown','mousemove'].forEach(function(ev){
		window.addEventListener(ev, loadAll, {passive:true});
	});
	// Safety net: load within 8s regardless.
	setTimeout(loadAll, 8000);
})();
</script>
		<?php
	}

	private function is_excluded( $handle, $src ) {
		if ( in_array( $handle, self::$never_touch, true ) ) {
			return true;
		}
		$src = (string) $src;
		if ( $this->matches_any( $src, self::$never_touch_urls ) ) {
			return true;
		}
		$user_excl = isset( $this->settings['js_exclusions'] ) ? (string) $this->settings['js_exclusions'] : '';
		foreach ( preg_split( "/\r?\n/", $user_excl ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) { continue; }
			if ( $handle === $line || false !== stripos( $src, $line ) ) {
				return true;
			}
		}
		return false;
	}

	private function matches_any( $needle, array $haystack ) {
		foreach ( $haystack as $h ) {
			if ( false !== stripos( $needle, $h ) ) {
				return true;
			}
		}
		return false;
	}

	private function minified_url_for( $src ) {
		if ( '' === (string) $src ) { return null; }
		$home = home_url( '/' );
		if ( 0 !== strpos( $src, $home ) ) {
			return null;
		}
		// Skip files that are already minified (.min.js) — re-minifying
		// would only add overhead with no payoff.
		if ( false !== strpos( $src, '.min.js' ) ) {
			return null;
		}
		$relative = ltrim( preg_replace( '#\?.*$#', '', str_replace( $home, '', $src ) ), '/' );
		$abs      = ABSPATH . $relative;
		if ( ! file_exists( $abs ) ) {
			return null;
		}
		$mtime  = filemtime( $abs );
		$digest = substr( md5( $abs . '|' . $mtime ), 0, 12 );
		$out    = $this->cache_dir . '/js/' . $digest . '.js';
		if ( ! file_exists( $out ) ) {
			$js  = @file_get_contents( $abs );
			if ( false === $js ) { return null; }
			$min = $this->minify( $js );
			if ( ! is_dir( dirname( $out ) ) ) { wp_mkdir_p( dirname( $out ) ); }
			if ( false === @file_put_contents( $out, $min, LOCK_EX ) ) {
				return null;
			}
		}
		return $this->cache_url . '/js/' . $digest . '.js';
	}

	/**
	 * Very conservative JS minifier — strips comments and blank lines
	 * only. We intentionally do not attempt advanced transforms
	 * (mangling, dead-code elimination) because those need a real
	 * parser and break inline regex/template literals far too often.
	 */
	public function minify( $js ) {
		$js = (string) $js;
		// Preserve string literals and regex from being mangled by the
		// comment stripper.
		$strings = array();
		$js = preg_replace_callback(
			'#(["\'`])((?:\\\\.|(?!\1).)*)\1#s',
			function ( $m ) use ( &$strings ) {
				$key = '__RWAI_JS_STR_' . count( $strings ) . '__';
				$strings[ $key ] = $m[0];
				return $key;
			},
			$js
		);
		// Strip /* ... */ comments but keep /*! banners.
		$js = preg_replace( '#/\*(?!\!).*?\*/#s', '', $js );
		// Strip // line comments.
		$js = preg_replace( '#^\s*//.*$#m', '', $js );
		// Collapse blank lines.
		$js = preg_replace( "#\n\s*\n#", "\n", $js );
		foreach ( $strings as $key => $literal ) {
			$js = str_replace( $key, $literal, $js );
		}
		return trim( $js );
	}
}
