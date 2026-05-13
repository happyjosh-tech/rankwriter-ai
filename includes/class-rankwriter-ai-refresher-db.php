<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table for the Content Refresher's update log + revision history.
 *
 * Schema is deliberately minimal — WP already stores full pre/post revisions
 * via wp_save_post_revision(), so this table only tracks the *fact* of an
 * automated refresh and a summary of what changed.
 */
class RankWriter_AI_Refresher_DB {

	const DB_VERSION_KEY = 'rwai_refresher_db_version';
	const DB_VERSION     = '1.0';

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_refresh_log';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::log_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			refreshed_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			revision_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			freshness_before TINYINT UNSIGNED NULL DEFAULT NULL,
			freshness_after TINYINT UNSIGNED NULL DEFAULT NULL,
			summary TEXT NULL,
			changes_json LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY refreshed_at (refreshed_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $row ) {
		global $wpdb;
		$defaults = array(
			'refreshed_at' => current_time( 'mysql' ),
			'status'       => 'success',
		);
		$row = array_merge( $defaults, $row );
		$wpdb->insert( self::log_table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function recent( $limit = 50 ) {
		global $wpdb;
		$table = self::log_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY refreshed_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	public static function for_post( $post_id, $limit = 20 ) {
		global $wpdb;
		$table = self::log_table();
		$limit = max( 1, min( 200, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY refreshed_at DESC LIMIT %d", absint( $post_id ), $limit ),
			ARRAY_A
		);
	}

	public static function count_in_window( $hours = 24 ) {
		global $wpdb;
		$table = self::log_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $hours ) * HOUR_IN_SECONDS ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE refreshed_at >= %s", $since )
		);
	}
}
