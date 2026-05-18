<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage layer for the Ads module.
 *
 * - Per-block config (16 blocks) lives in a single `rwai_ads_blocks`
 *   option as a JSON array. Single option = single DB read, no extra
 *   tables, no migrations.
 * - Global settings (AdSense Auto Ads ID, ads.txt content, master enable
 *   toggle) live in `rwai_ads_settings`.
 */
class RankWriter_AI_Ads_DB {

	const BLOCKS_OPTION   = 'rwai_ads_blocks';
	const SETTINGS_OPTION = 'rwai_ads_settings';
	const NUM_BLOCKS      = 16;

	public static function default_block( $id = 1 ) {
		return array(
			'id'               => (int) $id,
			'name'             => sprintf( 'Block %d', (int) $id ),
			'enabled'          => 0,
			'code'             => '',

			// Insertion: where the ad lands in the post body.
			//   - none                — block is configured but never auto-inserted
			//                            (use the shortcode instead)
			//   - after_paragraph     — insert after each paragraph number in
			//                            `insertion_paragraphs` (comma list)
			//   - before_content      — at the very top of the_content
			//   - after_content       — at the very bottom of the_content
			//   - before_excerpt      — top of the_excerpt
			//   - after_excerpt       — bottom of the_excerpt
			//   - between_posts       — between posts on archive/loop pages
			'insertion'            => 'after_paragraph',
			'insertion_paragraphs' => '3',  // CSV of paragraph numbers
			'between_posts_every'  => 3,    // every Nth post on archives

			// Display conditions: WHERE the ad shows up.
			'show_on_posts'      => 1,
			'show_on_pages'      => 0,
			'show_on_homepage'   => 0,
			'show_on_category'   => 0,
			'show_on_tag'        => 0,
			'show_on_search'     => 0,
			'show_on_archive'    => 0,

			// Optional fine-grained targeting (CSV of term IDs / post IDs).
			'include_categories' => '',
			'exclude_categories' => '',
			'include_tags'       => '',
			'exclude_tags'       => '',
			'exclude_post_ids'   => '',

			// Device targeting (NEW).
			'show_desktop' => 1,
			'show_tablet'  => 1,
			'show_mobile'  => 1,

			// Scheduling (NEW). Empty start/end = no boundary.
			'schedule_start'    => '',   // Y-m-d H:i (site timezone)
			'schedule_end'      => '',
			'schedule_hour_from'=> '',   // HH:MM, empty = no daily window
			'schedule_hour_to'  => '',
			'schedule_days'     => '0,1,2,3,4,5,6', // CSV, 0=Sun..6=Sat

			// Alignment: default | left | right | center.
			'alignment' => 'default',
		);
	}

	public static function default_settings() {
		return array(
			'master_enabled'    => 1,
			'auto_ads_enabled'  => 0,
			'auto_ads_pub_id'   => '', // ca-pub-1234567890
			'ads_txt_content'   => '',
			'inject_in_head'    => '', // freeform header HTML (e.g. additional verifications)
		);
	}

	public static function get_blocks() {
		$raw  = get_option( self::BLOCKS_OPTION, array() );
		$list = is_array( $raw ) ? $raw : array();
		$out  = array();
		for ( $i = 1; $i <= self::NUM_BLOCKS; $i++ ) {
			$default = self::default_block( $i );
			if ( isset( $list[ $i ] ) && is_array( $list[ $i ] ) ) {
				$out[ $i ] = array_merge( $default, $list[ $i ] );
				$out[ $i ]['id'] = $i; // never let stored data override the index
			} else {
				$out[ $i ] = $default;
			}
		}
		return $out;
	}

	public static function get_block( $id ) {
		$id = (int) $id;
		$blocks = self::get_blocks();
		return isset( $blocks[ $id ] ) ? $blocks[ $id ] : null;
	}

	public static function save_blocks( array $blocks ) {
		$sanitized = array();
		for ( $i = 1; $i <= self::NUM_BLOCKS; $i++ ) {
			if ( ! isset( $blocks[ $i ] ) || ! is_array( $blocks[ $i ] ) ) {
				continue;
			}
			$sanitized[ $i ] = self::sanitize_block( $blocks[ $i ], $i );
		}
		update_option( self::BLOCKS_OPTION, $sanitized, false );
	}

	public static function save_block( $id, array $patch ) {
		$blocks = self::get_blocks();
		$id     = (int) $id;
		if ( ! isset( $blocks[ $id ] ) ) {
			return false;
		}
		$blocks[ $id ] = array_merge( $blocks[ $id ], $patch );
		self::save_blocks( $blocks );
		return true;
	}

	public static function get_settings() {
		$raw = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $raw ) ? $raw : array(), self::default_settings() );
	}

	public static function save_settings( array $patch ) {
		$current = self::get_settings();
		$merged  = array_merge( $current, $patch );
		$merged['master_enabled']   = empty( $merged['master_enabled'] )   ? 0 : 1;
		$merged['auto_ads_enabled'] = empty( $merged['auto_ads_enabled'] ) ? 0 : 1;
		// pub-id format guardrail.
		$pub = (string) $merged['auto_ads_pub_id'];
		$pub = preg_replace( '/[^A-Za-z0-9\-]/', '', $pub );
		if ( '' !== $pub && 0 !== strpos( $pub, 'ca-pub-' ) && 0 === strpos( $pub, 'pub-' ) ) {
			$pub = 'ca-' . $pub;
		}
		$merged['auto_ads_pub_id'] = $pub;
		update_option( self::SETTINGS_OPTION, $merged, false );
		return $merged;
	}

	private static function sanitize_block( array $block, $id ) {
		$d = self::default_block( $id );
		$out = $d;

		$out['id']      = (int) $id;
		$out['name']    = isset( $block['name'] ) ? sanitize_text_field( (string) $block['name'] ) : $d['name'];
		$out['enabled'] = ! empty( $block['enabled'] ) ? 1 : 0;
		// Code is the only field that may contain HTML/JS/PHP. We DO NOT
		// run kses on it — the user is intentionally pasting AdSense /
		// other ad-network snippets that contain scripts. The Ads page
		// is restricted to manage_options users (admins) so XSS injection
		// would only be possible if an admin uploaded malicious code,
		// which is already an admin-level trust boundary.
		$out['code']    = isset( $block['code'] ) ? (string) $block['code'] : '';

		$insertions = array( 'none', 'after_paragraph', 'before_content', 'after_content', 'before_excerpt', 'after_excerpt', 'between_posts' );
		$out['insertion'] = isset( $block['insertion'] ) && in_array( $block['insertion'], $insertions, true ) ? $block['insertion'] : $d['insertion'];
		$out['insertion_paragraphs'] = isset( $block['insertion_paragraphs'] ) ? self::sanitize_csv_ints( $block['insertion_paragraphs'], 0, 100 ) : $d['insertion_paragraphs'];
		$out['between_posts_every']  = isset( $block['between_posts_every'] ) ? max( 1, min( 50, (int) $block['between_posts_every'] ) ) : $d['between_posts_every'];

		foreach ( array( 'show_on_posts', 'show_on_pages', 'show_on_homepage', 'show_on_category', 'show_on_tag', 'show_on_search', 'show_on_archive', 'show_desktop', 'show_tablet', 'show_mobile' ) as $bool ) {
			$out[ $bool ] = ! empty( $block[ $bool ] ) ? 1 : 0;
		}

		foreach ( array( 'include_categories', 'exclude_categories', 'include_tags', 'exclude_tags', 'exclude_post_ids' ) as $csv ) {
			$out[ $csv ] = isset( $block[ $csv ] ) ? self::sanitize_csv_ints( $block[ $csv ], 0, PHP_INT_MAX ) : '';
		}

		$out['schedule_start']     = isset( $block['schedule_start'] ) ? self::sanitize_datetime( $block['schedule_start'] ) : '';
		$out['schedule_end']       = isset( $block['schedule_end'] ) ? self::sanitize_datetime( $block['schedule_end'] ) : '';
		$out['schedule_hour_from'] = isset( $block['schedule_hour_from'] ) ? self::sanitize_hhmm( $block['schedule_hour_from'] ) : '';
		$out['schedule_hour_to']   = isset( $block['schedule_hour_to'] ) ? self::sanitize_hhmm( $block['schedule_hour_to'] ) : '';
		$out['schedule_days']      = isset( $block['schedule_days'] ) ? self::sanitize_csv_ints( $block['schedule_days'], 0, 6 ) : $d['schedule_days'];

		$alignments = array( 'default', 'left', 'right', 'center' );
		$out['alignment'] = isset( $block['alignment'] ) && in_array( $block['alignment'], $alignments, true ) ? $block['alignment'] : 'default';

		return $out;
	}

	private static function sanitize_csv_ints( $raw, $min, $max ) {
		$raw   = (string) $raw;
		$parts = preg_split( '/[\s,]+/', $raw );
		$out   = array();
		foreach ( (array) $parts as $p ) {
			$p = trim( $p );
			if ( '' === $p ) { continue; }
			if ( ! ctype_digit( $p ) ) { continue; }
			$n = (int) $p;
			if ( $n < $min || $n > $max ) { continue; }
			$out[] = $n;
		}
		return implode( ',', array_values( array_unique( $out ) ) );
	}

	private static function sanitize_datetime( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return ''; }
		// Accept "YYYY-MM-DD HH:MM" or "YYYY-MM-DDTHH:MM" (HTML input).
		$normalized = str_replace( 'T', ' ', $raw );
		$ts = strtotime( $normalized );
		if ( ! $ts ) { return ''; }
		return gmdate( 'Y-m-d H:i', $ts );
	}

	private static function sanitize_hhmm( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return ''; }
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m ) ) {
			return '';
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}
}
