<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table for the Pinterest pin library.
 *
 *   {prefix}rwai_pinterest_pins — one row per generated pin
 *
 * Multiple pins per blog post is the common case (test variations), so a
 * normalized table + indexed status/scheduled_at columns let the cron
 * worker scan due pins in O(log n).
 */
class RankWriter_AI_Pinterest_DB {

	const DB_VERSION_OPTION = 'rwai_pinterest_db_version';
	const DB_VERSION        = '1.0.0';

	public static function pins_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_pinterest_pins';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$pins    = self::pins_table();

		$sql = "CREATE TABLE {$pins} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NULL,
			niche VARCHAR(40) NOT NULL DEFAULT 'general',
			title VARCHAR(255) NOT NULL,
			description TEXT NOT NULL,
			hashtags TEXT NULL,
			overlay_text VARCHAR(255) NOT NULL,
			overlay_secondary VARCHAR(255) NULL,
			image_prompt TEXT NULL,
			image_attachment_id BIGINT UNSIGNED NULL,
			board_suggestions TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			scheduled_at DATETIME NULL,
			posted_at DATETIME NULL,
			pin_url VARCHAR(500) NULL,
			variation_signature CHAR(16) NULL,
			error_message TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY niche (niche)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function uninstall() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::pins_table() );
		delete_option( self::DB_VERSION_OPTION );
	}

	public static function ready() {
		global $wpdb;
		$t = self::pins_table();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
				DB_NAME, $t
			)
		);
		return 1 === (int) $found;
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}
}
