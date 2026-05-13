<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB schema for the Self-Healing SEO Engine.
 *
 * Two tables:
 *   - rwai_seo_issues       open issues found by the scanner. Deleted
 *                           when resolved (either auto-fixed or fixed
 *                           by the user).
 *   - rwai_seo_repair_log   audit trail. Stores the before / after
 *                           snapshot so any repair can be rolled back.
 *
 * Keeping issues out of the log table (instead of "status" toggles)
 * keeps the open-issues query fast even on sites with thousands of
 * historical repairs.
 */
class RankWriter_AI_SEO_Healer_DB {

	const DB_VERSION_KEY = 'rwai_seo_healer_db_version';
	const DB_VERSION     = '1.0';

	public static function issues_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_seo_issues';
	}

	public static function repair_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_seo_repair_log';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$issues  = self::issues_table();
		$log     = self::repair_log_table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql1 = "CREATE TABLE {$issues} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			rule VARCHAR(64) NOT NULL,
			severity VARCHAR(20) NOT NULL DEFAULT 'warning',
			auto_fixable TINYINT(1) NOT NULL DEFAULT 0,
			detected_at DATETIME NOT NULL,
			message TEXT NULL,
			context_json LONGTEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_post_rule (post_id, rule),
			KEY rule (rule),
			KEY severity (severity)
		) {$charset};";
		dbDelta( $sql1 );

		$sql2 = "CREATE TABLE {$log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			rule VARCHAR(64) NOT NULL,
			repaired_at DATETIME NOT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'auto',
			before_value LONGTEXT NULL,
			after_value LONGTEXT NULL,
			notes TEXT NULL,
			rolled_back_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY repaired_at (repaired_at),
			KEY rule (rule)
		) {$charset};";
		dbDelta( $sql2 );

		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ============================ Issues ============================ */

	/**
	 * Upsert an issue. Same (post_id, rule) replaces in place — that's
	 * how we avoid drift / duplicates across multiple scans.
	 */
	public static function upsert_issue( array $row ) {
		global $wpdb;
		$t = self::issues_table();
		$row['detected_at'] = $row['detected_at'] ?? current_time( 'mysql' );
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t} WHERE post_id = %d AND rule = %s",
			(int) $row['post_id'], (string) $row['rule']
		) );
		if ( $exists ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $exists ) );
			return (int) $exists;
		}
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function clear_issue( $post_id, $rule ) {
		global $wpdb;
		$t = self::issues_table();
		return $wpdb->delete( $t, array( 'post_id' => (int) $post_id, 'rule' => (string) $rule ) );
	}

	public static function get_issue( $id ) {
		global $wpdb;
		$t = self::issues_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function open_issues( $limit = 200, $filter = array() ) {
		global $wpdb;
		$t = self::issues_table();
		$where = '1=1';
		$params = array();
		if ( ! empty( $filter['rule'] ) ) {
			$where .= ' AND rule = %s';
			$params[] = $filter['rule'];
		}
		if ( ! empty( $filter['severity'] ) ) {
			$where .= ' AND severity = %s';
			$params[] = $filter['severity'];
		}
		if ( ! empty( $filter['post_id'] ) ) {
			$where .= ' AND post_id = %d';
			$params[] = (int) $filter['post_id'];
		}
		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY severity ASC, detected_at DESC LIMIT %d";
		$params[] = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
	}

	public static function counts_by_rule() {
		global $wpdb;
		$t = self::issues_table();
		$rows = $wpdb->get_results( "SELECT rule, severity, COUNT(*) AS c FROM {$t} GROUP BY rule, severity", ARRAY_A );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r['rule'] ]['total']   = ( $out[ $r['rule'] ]['total']   ?? 0 ) + (int) $r['c'];
			$out[ $r['rule'] ][ $r['severity'] ] = (int) $r['c'];
		}
		return $out;
	}

	public static function total_open() {
		global $wpdb;
		$t = self::issues_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
	}

	public static function severity_totals() {
		global $wpdb;
		$t = self::issues_table();
		$rows = $wpdb->get_results( "SELECT severity, COUNT(*) AS c FROM {$t} GROUP BY severity", ARRAY_A );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r['severity'] ] = (int) $r['c'];
		}
		return $out;
	}

	/* ============================ Repair log ============================ */

	public static function log_repair( array $row ) {
		global $wpdb;
		$t = self::repair_log_table();
		$row['repaired_at'] = $row['repaired_at'] ?? current_time( 'mysql' );
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function get_repair( $id ) {
		global $wpdb;
		$t = self::repair_log_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function mark_rolled_back( $id ) {
		global $wpdb;
		return $wpdb->update( self::repair_log_table(), array( 'rolled_back_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function recent_repairs( $limit = 50 ) {
		global $wpdb;
		$t = self::repair_log_table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} ORDER BY repaired_at DESC LIMIT %d", max( 1, min( 500, (int) $limit ) ) ),
			ARRAY_A
		);
	}

	public static function count_repairs_in_window( $hours = 24 ) {
		global $wpdb;
		$t = self::repair_log_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $hours ) * HOUR_IN_SECONDS ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE repaired_at >= %s AND rolled_back_at IS NULL", $since )
		);
	}
}
