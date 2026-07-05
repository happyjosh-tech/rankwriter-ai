<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage layer for the Bot Blocker module.
 *
 * - Settings live in a single `rwai_bot_blocker_settings` option.
 * - Blocked hits are logged to a dedicated table so the admin can see
 *   what got stopped (IP, country, page, time) — the whole point of a
 *   click-fraud defense feature is being able to prove it's working.
 */
class RankWriter_AI_Bot_Blocker_DB {

	const SETTINGS_OPTION = 'rwai_bot_blocker_settings';
	const DB_VERSION_KEY  = 'rwai_bot_blocker_db_version';
	const DB_VERSION      = '1.0';

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_bot_blocker_log';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::log_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			blocked_at DATETIME NOT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			country VARCHAR(2) NOT NULL DEFAULT '',
			reason VARCHAR(20) NOT NULL DEFAULT 'country',
			request_uri VARCHAR(500) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY blocked_at (blocked_at),
			KEY ip (ip),
			KEY country (country)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function default_settings() {
		return array(
			'enabled'              => 0,
			// 'blacklist' = block the listed countries, everyone else gets in.
			// 'whitelist' = only the listed countries get in, everyone else blocked.
			'mode'                 => 'blacklist',
			'countries'            => '',   // CSV of ISO 3166-1 alpha-2 codes
			'manual_countries'     => '',   // CSV of extra/custom codes the admin typed in by hand
			'blocked_ips'          => '',   // one IP or CIDR per line
			'whitelisted_ips'      => '',   // one IP or CIDR per line — never blocked, safety net
			'exempt_logged_in'     => 1,
			'exempt_search_bots'   => 1,
			'geo_api_lookup'       => 1,    // fall back to a free IP-geolocation API when no proxy/CDN header is present
			'enable_logging'       => 1,
			'block_message'        => '',   // empty = use the built-in default
		);
	}

	public static function get_settings() {
		$raw = get_option( self::SETTINGS_OPTION, array() );
		$out = wp_parse_args( is_array( $raw ) ? $raw : array(), self::default_settings() );
		// All manually-added + picked codes, merged, deduped, uppercased.
		$out['all_countries'] = self::merge_codes( $out['countries'], $out['manual_countries'] );
		return $out;
	}

	public static function save_settings( array $patch ) {
		$current = self::get_settings();
		unset( $current['all_countries'] ); // derived, never stored

		$merged = array_merge( $current, $patch );

		$merged['enabled']            = empty( $merged['enabled'] ) ? 0 : 1;
		$merged['exempt_logged_in']   = empty( $merged['exempt_logged_in'] ) ? 0 : 1;
		$merged['exempt_search_bots'] = empty( $merged['exempt_search_bots'] ) ? 0 : 1;
		$merged['geo_api_lookup']     = empty( $merged['geo_api_lookup'] ) ? 0 : 1;
		$merged['enable_logging']     = empty( $merged['enable_logging'] ) ? 0 : 1;
		$merged['mode']               = ( 'whitelist' === $merged['mode'] ) ? 'whitelist' : 'blacklist';

		$merged['countries']        = self::sanitize_codes( $merged['countries'] );
		$merged['manual_countries'] = self::sanitize_codes( $merged['manual_countries'] );
		$merged['blocked_ips']      = self::sanitize_ip_lines( $merged['blocked_ips'] );
		$merged['whitelisted_ips']  = self::sanitize_ip_lines( $merged['whitelisted_ips'] );
		$merged['block_message']    = isset( $merged['block_message'] ) ? sanitize_textarea_field( (string) $merged['block_message'] ) : '';

		update_option( self::SETTINGS_OPTION, $merged, false );
		return self::get_settings();
	}

	/**
	 * Accepts a comma/space/newline separated list of country codes and
	 * returns a clean, deduped, uppercased CSV. Anything that isn't
	 * 2-3 letters is dropped rather than silently mangled.
	 */
	public static function sanitize_codes( $raw ) {
		$raw   = (string) $raw;
		$parts = preg_split( '/[\s,]+/', strtoupper( $raw ) );
		$out   = array();
		foreach ( (array) $parts as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Z]{2,3}$/', $p ) ) {
				continue;
			}
			$out[] = $p;
		}
		return implode( ',', array_values( array_unique( $out ) ) );
	}

	private static function merge_codes( $a, $b ) {
		$a = array_filter( explode( ',', (string) $a ) );
		$b = array_filter( explode( ',', (string) $b ) );
		return array_values( array_unique( array_merge( $a, $b ) ) );
	}

	/**
	 * One IP / CIDR per line (also tolerates comma separation). Only
	 * keeps lines that look like a plausible IPv4/IPv6 address or CIDR
	 * block — bad input is dropped, not fatal.
	 */
	public static function sanitize_ip_lines( $raw ) {
		$raw   = (string) $raw;
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '/', $line );
			$addr  = $parts[0];
			if ( ! filter_var( $addr, FILTER_VALIDATE_IP ) ) {
				continue;
			}
			if ( isset( $parts[1] ) && ! ctype_digit( $parts[1] ) ) {
				continue;
			}
			$out[] = $line;
		}
		return implode( "\n", array_values( array_unique( $out ) ) );
	}

	public static function log_block( array $row ) {
		global $wpdb;
		$defaults = array(
			'blocked_at' => current_time( 'mysql' ),
		);
		$row = array_merge( $defaults, $row );
		$wpdb->insert( self::log_table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$table = self::log_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY blocked_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	public static function count_in_window( $hours = 24 ) {
		global $wpdb;
		$table = self::log_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $hours ) * HOUR_IN_SECONDS ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE blocked_at >= %s", $since )
		);
	}

	public static function top_countries( $limit = 5 ) {
		global $wpdb;
		$table = self::log_table();
		$limit = max( 1, min( 50, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country, COUNT(*) as hits FROM {$table} WHERE country != '' GROUP BY country ORDER BY hits DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function clear_log() {
		global $wpdb;
		$table = self::log_table();
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Full ISO 3166-1 alpha-2 country list (code => English name), used
	 * to populate the "pick countries to block" checklist. Kept as a
	 * plain array (not a DB table) — it never changes at runtime.
	 */
	public static function all_countries() {
		return array(
			'AF' => 'Afghanistan', 'AX' => 'Åland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria',
			'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla',
			'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
			'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
			'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin',
			'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BQ' => 'Bonaire, Sint Eustatius and Saba',
			'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
			'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
			'CA' => 'Canada', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad',
			'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)',
			'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => "Côte d'Ivoire", 'HR' => 'Croatia',
			'CU' => 'Cuba', 'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
			'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
			'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland',
			'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories',
			'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
			'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland',
			'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala',
			'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
			'HT' => 'Haiti', 'HM' => 'Heard Island and McDonald Islands', 'VA' => 'Holy See', 'HN' => 'Honduras',
			'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India',
			'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
			'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
			'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
			'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea (North)', 'KR' => 'Korea (South)',
			'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
			'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
			'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao',
			'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
			'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique',
			'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
			'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
			'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique',
			'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal',
			'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
			'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island',
			'MK' => 'North Macedonia', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman',
			'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama',
			'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
			'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico',
			'QA' => 'Qatar', 'RE' => 'Réunion', 'RO' => 'Romania', 'RU' => 'Russia',
			'RW' => 'Rwanda', 'BL' => 'Saint Barthélemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia',
			'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
			'SG' => 'Singapore', 'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia',
			'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and the South Sandwich Islands',
			'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan',
			'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SE' => 'Sweden', 'CH' => 'Switzerland',
			'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania',
			'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau',
			'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
			'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda',
			'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
			'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu',
			'VE' => 'Venezuela', 'VN' => 'Vietnam', 'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (U.S.)',
			'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);
	}

	public static function country_name( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		$all  = self::all_countries();
		return isset( $all[ $code ] ) ? $all[ $code ] : $code;
	}
}
