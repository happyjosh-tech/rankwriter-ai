<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrator for the RankWriter Site Speed Optimizer module.
 *
 * Responsibilities:
 *   - Own the settings + mode (Safe / Balanced / Aggressive).
 *   - Boot each sub-module with its mode-derived flags.
 *   - Provide one-shot "optimize now" + "restore" entry points the
 *     admin screen calls.
 *   - Talk to the PageSpeed Insights API when the user supplies a key.
 *
 * Design notes:
 *   - Sub-modules are passed the resolved settings array on construct;
 *     they never read $_GET / $_POST themselves.
 *   - Everything is reversible: we snapshot the pre-optimization
 *     settings on first activation, and restore() rewrites that
 *     snapshot back over the current state + purges generated files.
 */
class RankWriter_AI_Speed_Optimizer {

	const OPTION_SETTINGS = 'rwai_speed_settings';
	const OPTION_BACKUP   = 'rwai_speed_settings_backup';
	const OPTION_STATUS   = 'rwai_speed_status';
	const MODE_SAFE       = 'safe';
	const MODE_BALANCED   = 'balanced';
	const MODE_AGGRESSIVE = 'aggressive';

	private $cache_dir;
	private $cache_url;
	private $settings;

	public function __construct() {
		$wp_content_dir  = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$wp_content_url  = content_url();
		$this->cache_dir = $wp_content_dir . '/cache/rwai-speed';
		$this->cache_url = $wp_content_url . '/cache/rwai-speed';
		$this->settings  = $this->get_settings();
	}

	/* ============================ Defaults & mode resolution ============================ */

	public static function default_settings() {
		return array(
			'enabled'                 => 0,
			'mode'                    => self::MODE_BALANCED,

			// Cache
			'cache_enabled'           => 1,
			'cache_ttl'               => 12 * HOUR_IN_SECONDS,
			'cache_with_query'        => 0,
			'cache_exclusions'        => "/cart\n/checkout\n/my-account",

			// Browser cache headers
			'browser_cache_enabled'   => 1,
			'browser_cache_html_ttl'  => HOUR_IN_SECONDS,

			// CSS
			'css_minify'              => 1,
			'css_defer'               => 0,    // requires non-empty critical_css
			'css_exclusions'          => '',
			'critical_css'            => '',

			// JS
			'js_minify'               => 1,
			'js_defer'                => 0,
			'js_delay'                => 0,
			'js_exclusions'           => '',

			// Images
			'image_lazyload'          => 1,
			'image_dims'              => 1,
			'image_webp'              => 0,

			// Core Web Vitals
			'cwv_fetchpriority_lcp'   => 1,
			'cwv_preload_featured'    => 1,
			'cwv_preload_fonts'       => 0,
			'preload_font_urls'       => '',

			// Page polish (added v1.2.1 — score-mover features)
			'cwv_html_minify'         => 0,
			'cwv_dns_prefetch'        => 1,
			'cwv_disable_emojis'      => 0,
			'cwv_remove_jquery_migrate'=> 0,
			'cwv_disable_embeds'      => 0,
			'cwv_google_fonts_swap'   => 1,
			'dns_prefetch_hosts'      => '',

			// PageSpeed
			'pagespeed_api_key'       => '',
		);
	}

	public function get_settings() {
		$saved = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Given a target mode and a user-overridable settings array, return
	 * the resolved settings that actually go into effect. The mode
	 * provides the safety floor — Safe mode forces off features that
	 * could possibly break a fragile theme, regardless of what the
	 * user toggled.
	 */
	public function resolve_for_mode( array $settings ) {
		$mode = isset( $settings['mode'] ) ? $settings['mode'] : self::MODE_BALANCED;
		switch ( $mode ) {
			case self::MODE_SAFE:
				$settings['css_defer']            = 0;
				$settings['js_defer']             = 0;
				$settings['js_delay']             = 0;
				$settings['image_webp']           = 0;
				$settings['cwv_preload_fonts']    = 0;
				$settings['cwv_html_minify']      = 0;
				$settings['cwv_disable_emojis']   = 0;
				$settings['cwv_remove_jquery_migrate'] = 0;
				$settings['cwv_disable_embeds']   = 0;
				break;
			case self::MODE_AGGRESSIVE:
				$settings['css_defer']            = 1;
				$settings['js_defer']             = 1;
				$settings['js_delay']             = 1;
				$settings['image_webp']           = 1;
				$settings['cwv_html_minify']      = 1;
				$settings['cwv_disable_emojis']   = 1;
				$settings['cwv_remove_jquery_migrate'] = 1;
				$settings['cwv_disable_embeds']   = 1;
				$settings['cwv_google_fonts_swap']= 1;
				$settings['cwv_dns_prefetch']     = 1;
				break;
			case self::MODE_BALANCED:
			default:
				// Balanced: defer JS but don't delay everything by default.
				$settings['js_defer']             = 1;
				$settings['js_delay']             = 1; // delay only the analytics/social candidates
				$settings['image_webp']           = 1;
				$settings['css_defer']            = 0;
				$settings['cwv_disable_emojis']   = 1;
				$settings['cwv_google_fonts_swap']= 1;
				$settings['cwv_dns_prefetch']     = 1;
				$settings['cwv_html_minify']      = 1;
				break;
		}
		return $settings;
	}

	public function save_settings( array $values ) {
		$current  = $this->get_settings();
		$valid    = self::default_settings();
		$merged   = array();
		foreach ( $valid as $key => $default ) {
			if ( ! array_key_exists( $key, $values ) ) {
				$merged[ $key ] = $current[ $key ] ?? $default;
				continue;
			}
			if ( is_int( $default ) ) {
				$merged[ $key ] = (int) $values[ $key ];
			} elseif ( in_array( $key, array( 'mode' ), true ) ) {
				$merged[ $key ] = in_array( $values[ $key ], array( self::MODE_SAFE, self::MODE_BALANCED, self::MODE_AGGRESSIVE ), true ) ? $values[ $key ] : self::MODE_BALANCED;
			} else {
				$merged[ $key ] = is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : '';
			}
		}
		$merged = $this->resolve_for_mode( $merged );
		update_option( self::OPTION_SETTINGS, $merged );
		$this->settings = $merged;
		return $merged;
	}

	/* ============================ Boot ============================ */

	public function register_hooks() {
		// Always available for admin actions; only attach front-end
		// filters when the module is turned on.
		add_action( 'init', array( $this, 'ensure_cache_dir' ) );
		if ( empty( $this->settings['enabled'] ) ) {
			return;
		}
		$resolved = $this->resolve_for_mode( $this->settings );

		( new RankWriter_AI_Cache_Manager( $this->cache_dir, $resolved ) )->register_hooks();
		( new RankWriter_AI_Browser_Cache( $resolved ) )->register_hooks();
		( new RankWriter_AI_CSS_Optimizer( $resolved, $this->cache_dir, $this->cache_url ) )->register_hooks();
		( new RankWriter_AI_JS_Optimizer( $resolved, $this->cache_dir, $this->cache_url ) )->register_hooks();
		( new RankWriter_AI_Image_Optimizer( $resolved ) )->register_hooks();
		( new RankWriter_AI_Core_Web_Vitals( $resolved ) )->register_hooks();
	}

	public function ensure_cache_dir() {
		if ( is_dir( $this->cache_dir ) ) {
			return;
		}
		wp_mkdir_p( $this->cache_dir );
		// Belt-and-braces: deny direct directory listing.
		$index    = $this->cache_dir . '/index.php';
		$htaccess = $this->cache_dir . '/.htaccess';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php // silence is golden\n" );
		}
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all\n<FilesMatch \"\\.(html|css|js|webp)$\">\nOrder Allow,Deny\nAllow from all\n</FilesMatch>\n" );
		}
	}

	/* ============================ One-click optimize + restore ============================ */

	/**
	 * Enable the module with the chosen mode, snapshot the current
	 * settings (only the first time, so a re-run doesn't overwrite a
	 * good backup with a partially-optimized one), and prime the cache
	 * dir.
	 */
	public function optimize_now( $mode = self::MODE_BALANCED ) {
		// Take backup the FIRST time only.
		if ( false === get_option( self::OPTION_BACKUP, false ) ) {
			update_option( self::OPTION_BACKUP, $this->get_settings(), false );
		}
		$settings = $this->get_settings();
		$settings['enabled'] = 1;
		$settings['mode']    = $mode;
		$this->save_settings( $settings );

		$this->ensure_cache_dir();
		( new RankWriter_AI_Cache_Manager( $this->cache_dir, $this->get_settings() ) )->purge_all();
		$status = array(
			'last_optimized_at' => current_time( 'mysql' ),
			'last_mode'         => $mode,
		);
		update_option( self::OPTION_STATUS, $status, false );

		RankWriter_AI_Speed_Logger::log( 'optimize_now', 'Mode: ' . $mode, 'success' );
		return true;
	}

	/**
	 * Wipe the cache + restore the pre-optimization settings snapshot,
	 * effectively returning the site to its un-optimized state.
	 */
	public function restore_previous() {
		( new RankWriter_AI_Cache_Manager( $this->cache_dir, $this->get_settings() ) )->purge_all();
		$backup = get_option( self::OPTION_BACKUP, null );
		if ( is_array( $backup ) ) {
			update_option( self::OPTION_SETTINGS, $backup );
			$this->settings = $backup;
		} else {
			// No backup → just disable everything as the safe fallback.
			$defaults            = self::default_settings();
			$defaults['enabled'] = 0;
			update_option( self::OPTION_SETTINGS, $defaults );
			$this->settings = $defaults;
		}
		RankWriter_AI_Speed_Logger::log( 'restore_previous', 'Settings restored to pre-optimization snapshot.', 'success' );
		return true;
	}

	public function disable() {
		$current = $this->get_settings();
		$current['enabled'] = 0;
		update_option( self::OPTION_SETTINGS, $current );
		$this->settings = $current;
		( new RankWriter_AI_Cache_Manager( $this->cache_dir, $current ) )->purge_all();
		RankWriter_AI_Speed_Logger::log( 'disable', 'Speed Optimizer disabled by user.', 'info' );
		return true;
	}

	/* ============================ Status snapshot ============================ */

	public function status_snapshot() {
		$settings = $this->get_settings();
		$cache    = new RankWriter_AI_Cache_Manager( $this->cache_dir, $settings );
		$images   = new RankWriter_AI_Image_Optimizer( $settings );
		$db       = new RankWriter_AI_Database_Cleaner();
		return array(
			'enabled'        => ! empty( $settings['enabled'] ),
			'mode'           => $settings['mode'] ?? self::MODE_BALANCED,
			'cache_files'    => $cache->file_count(),
			'cache_size'     => $cache->size_bytes(),
			'image_stats'    => $images->get_stats(),
			'webp_supported' => $images->webp_supported(),
			'db_status'      => $db->get_status(),
			'last_status'    => (array) get_option( self::OPTION_STATUS, array() ),
		);
	}

	/* ============================ PageSpeed Insights API ============================ */

	/**
	 * Fetch PageSpeed scores for a URL (mobile + desktop). Returns
	 * WP_Error on any failure — the UI degrades gracefully to just
	 * showing internal optimization status.
	 */
	public function fetch_pagespeed( $url, $strategy = 'mobile' ) {
		$key = isset( $this->settings['pagespeed_api_key'] ) ? trim( (string) $this->settings['pagespeed_api_key'] ) : '';
		if ( '' === $key ) {
			return new WP_Error( 'rwai_no_key', __( 'Add a PageSpeed Insights API key first.', 'rankwriter-ai' ) );
		}
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile';
		$endpoint = add_query_arg(
			array(
				'url'      => rawurlencode( $url ),
				'strategy' => $strategy,
				'category' => 'performance',
				'key'      => $key,
			),
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
		);
		$resp = wp_remote_get( $endpoint, array( 'timeout' => 45 ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'rwai_psi_http', sprintf( 'PageSpeed API returned HTTP %d', $code ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $body ) || empty( $body['lighthouseResult'] ) ) {
			return new WP_Error( 'rwai_psi_bad_body', __( 'PageSpeed API returned an unexpected response.', 'rankwriter-ai' ) );
		}
		$lh    = $body['lighthouseResult'];
		$score = isset( $lh['categories']['performance']['score'] ) ? (int) round( $lh['categories']['performance']['score'] * 100 ) : null;
		$audits = $lh['audits'] ?? array();
		$cwv = array(
			'lcp_ms'   => isset( $audits['largest-contentful-paint']['numericValue'] ) ? (int) $audits['largest-contentful-paint']['numericValue'] : null,
			'cls'      => isset( $audits['cumulative-layout-shift']['numericValue'] ) ? round( (float) $audits['cumulative-layout-shift']['numericValue'], 3 ) : null,
			'inp_ms'   => isset( $audits['interaction-to-next-paint']['numericValue'] ) ? (int) $audits['interaction-to-next-paint']['numericValue'] : null,
			'tbt_ms'   => isset( $audits['total-blocking-time']['numericValue'] ) ? (int) $audits['total-blocking-time']['numericValue'] : null,
			'fcp_ms'   => isset( $audits['first-contentful-paint']['numericValue'] ) ? (int) $audits['first-contentful-paint']['numericValue'] : null,
		);
		return array(
			'strategy' => $strategy,
			'score'    => $score,
			'metrics'  => $cwv,
			'fetched_at' => current_time( 'mysql' ),
		);
	}
}
