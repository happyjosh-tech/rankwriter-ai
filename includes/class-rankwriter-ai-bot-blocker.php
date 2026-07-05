<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bot Blocker — country + IP access control for the public frontend.
 *
 * Built to stop suspicious AdSense "invalid click" traffic from a
 * specific country (or a known bad IP/range) from ever loading a page,
 * so it never gets a chance to click an ad. Country is resolved for
 * free, in this order:
 *
 *   1. A CDN/proxy header, if one is already present (Cloudflare's
 *      CF-IPCountry, Nginx GeoIP module, App Engine, etc.) — zero
 *      latency, zero API calls.
 *   2. A free IP-geolocation HTTP API, cached in a transient per IP
 *      for a week so repeat visitors don't re-trigger a lookup.
 *
 * Fails open: if the visitor's country can't be determined (API down,
 * unknown IP, etc.) the country check is skipped rather than risking
 * a false-positive lockout of real visitors.
 */
class RankWriter_AI_Bot_Blocker {

	const GEO_TRANSIENT_PREFIX = 'rwai_bb_geo_';
	const GEO_TRANSIENT_TTL    = WEEK_IN_SECONDS;

	public function register_hooks() {
		// template_redirect only fires for real frontend page requests —
		// it naturally skips wp-admin, admin-ajax.php, wp-cron.php, and
		// REST API calls, so we never risk locking the admin out of
		// their own dashboard or breaking background jobs.
		add_action( 'template_redirect', array( $this, 'maybe_block' ), 1 );
	}

	public function maybe_block() {
		$settings = RankWriter_AI_Bot_Blocker_DB::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! empty( $settings['exempt_logged_in'] ) && is_user_logged_in() ) {
			return;
		}

		$ip = self::visitor_ip();
		if ( '' === $ip ) {
			return; // Can't identify the visitor — nothing to check against.
		}

		// Whitelisted IPs always win, even over a country block. This is
		// the safety net that keeps an admin from locking out their own
		// office/VPN IP while testing a country rule.
		if ( self::ip_in_list( $ip, $settings['whitelisted_ips'] ) ) {
			return;
		}

		if ( ! empty( $settings['exempt_search_bots'] ) && self::is_search_bot() ) {
			return;
		}

		if ( self::ip_in_list( $ip, $settings['blocked_ips'] ) ) {
			$this->block( $settings, $ip, '', 'ip' );
			return;
		}

		$country = self::visitor_country( $ip, ! empty( $settings['geo_api_lookup'] ) );
		if ( '' === $country ) {
			return; // Unknown country — fail open.
		}

		$listed = in_array( $country, $settings['all_countries'], true );
		$blocked = ( 'whitelist' === $settings['mode'] ) ? ! $listed : $listed;

		if ( $blocked ) {
			$this->block( $settings, $ip, $country, 'country' );
		}
	}

	private function block( array $settings, $ip, $country, $reason ) {
		if ( ! empty( $settings['enable_logging'] ) ) {
			RankWriter_AI_Bot_Blocker_DB::log_block( array(
				'ip'           => $ip,
				'country'      => $country,
				'reason'       => $reason,
				'request_uri'  => isset( $_SERVER['REQUEST_URI'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 0, 500 ) : '',
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			) );
		}

		$message = trim( (string) $settings['block_message'] );
		if ( '' === $message ) {
			$message = __( 'Access to this site is not available from your location.', 'rankwriter-ai' );
		}

		nocache_headers();
		status_header( 403 );
		wp_die(
			esc_html( $message ),
			esc_html__( 'Access Denied', 'rankwriter-ai' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Best-effort real visitor IP. Prefers the Cloudflare header (can't
	 * be spoofed when Cloudflare proxies the request), then a generic
	 * proxy chain header, then the raw connection address.
	 */
	public static function visitor_ip() {
		$candidates = array();
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$candidates[] = trim( $parts[0] );
		}
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$candidates[] = wp_unslash( $_SERVER['HTTP_X_REAL_IP'] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		foreach ( $candidates as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Resolve a visitor's country to an ISO 3166-1 alpha-2 code, or ''
	 * if it can't be determined. Checks common CDN/proxy headers first
	 * (instant, free), then optionally falls back to a cached HTTP
	 * geolocation lookup.
	 */
	public static function visitor_country( $ip, $allow_api_lookup = true ) {
		$header_map = array(
			'HTTP_CF_IPCOUNTRY',        // Cloudflare
			'HTTP_X_COUNTRY_CODE',      // various CDNs/hosts
			'HTTP_GEOIP_COUNTRY_CODE',  // Nginx/Apache GeoIP module
			'HTTP_X_APPENGINE_COUNTRY', // Google App Engine / Cloud LB
			'HTTP_X_GEO_COUNTRY',
		);
		foreach ( $header_map as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code ) {
					return $code;
				}
			}
		}

		if ( ! $allow_api_lookup || '' === $ip ) {
			return '';
		}

		$transient_key = self::GEO_TRANSIENT_PREFIX . md5( $ip );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached; // may be '' (a cached "lookup failed / unknown")
		}

		$code = self::lookup_country_via_api( $ip );
		set_transient( $transient_key, $code, self::GEO_TRANSIENT_TTL );
		return $code;
	}

	/**
	 * Free, no-API-key IP geolocation. Tries two providers in sequence
	 * with a short timeout each so a slow/dead API never meaningfully
	 * delays a page load. Returns '' on any failure (fail open).
	 */
	private static function lookup_country_via_api( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,countryCode',
			array( 'timeout' => 3 )
		);
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) && 'success' === ( $body['status'] ?? '' ) && ! empty( $body['countryCode'] ) ) {
				$code = strtoupper( sanitize_text_field( $body['countryCode'] ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
					return $code;
				}
			}
		}

		$response = wp_remote_get(
			'https://ipapi.co/' . rawurlencode( $ip ) . '/country/',
			array( 'timeout' => 3 )
		);
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$code = strtoupper( trim( sanitize_text_field( wp_remote_retrieve_body( $response ) ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * True if $ip matches any entry in a newline/comma-separated list
	 * of exact IPs or IPv4 CIDR blocks.
	 */
	public static function ip_in_list( $ip, $list ) {
		if ( '' === trim( (string) $list ) ) {
			return false;
		}
		$entries = preg_split( '/[\r\n,]+/', (string) $list );
		foreach ( (array) $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( false !== strpos( $entry, '/' ) ) {
				if ( self::ip_in_cidr( $ip, $entry ) ) {
					return true;
				}
			} elseif ( 0 === strcasecmp( $entry, $ip ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * IPv4 CIDR containment check. IPv6 CIDR ranges are not supported
	 * (rare for this use case) — an IPv6 entry with a "/" is skipped.
	 */
	private static function ip_in_cidr( $ip, $cidr ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}
		list( $subnet, $bits ) = array_pad( explode( '/', $cidr, 2 ), 2, null );
		if ( ! filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || null === $bits || ! ctype_digit( (string) $bits ) ) {
			return false;
		}
		$bits = (int) $bits;
		if ( $bits < 0 || $bits > 32 ) {
			return false;
		}
		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}
		$mask = ( 0 === $bits ) ? 0 : ( ~0 << ( 32 - $bits ) );
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}

	/**
	 * Recognized search engine / ad-verification crawlers, matched by
	 * user-agent substring. Blocking these by accident would tank SEO
	 * (Googlebot) or break AdSense's own crawler (Mediapartners-Google)
	 * — both of which ignore country blocks anyway by crawling from
	 * their own data centers, but this exemption keeps behavior
	 * predictable if a bot's IP happens to also appear on a block list.
	 */
	public static function is_search_bot() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$ua = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
		$bots = array(
			'googlebot', 'mediapartners-google', 'adsbot-google', 'apis-google',
			'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
			'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterestbot',
			'applebot', 'rogerbot', 'ahrefsbot', 'semrushbot', 'msnbot',
		);
		foreach ( $bots as $bot ) {
			if ( false !== strpos( $ua, $bot ) ) {
				return true;
			}
		}
		return false;
	}
}
