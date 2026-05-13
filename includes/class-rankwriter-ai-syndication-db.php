<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table for the Parasite SEO syndication log.
 *
 * Stores one row per (post, platform, attempt). Lets the admin see at a
 * glance which posts have been syndicated where, the URL of the live
 * external copy, and whether the user used a rel=canonical link.
 */
class RankWriter_AI_Syndication_DB {

	const DB_VERSION_KEY = 'rwai_syndication_db_version';
	const DB_VERSION     = '1.0';

	const STATUS_DRAFT     = 'draft';
	const STATUS_PUBLISHED = 'published';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_syndications';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			platform VARCHAR(32) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			external_url TEXT NULL,
			canonical_used TINYINT(1) NOT NULL DEFAULT 0,
			generated_title TEXT NULL,
			generated_body LONGTEXT NULL,
			generated_cta TEXT NULL,
			generated_hashtags TEXT NULL,
			compliance_notes TEXT NULL,
			created_at DATETIME NOT NULL,
			published_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY platform (platform),
			KEY status (status)
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
			'created_at' => current_time( 'mysql' ),
			'status'     => self::STATUS_DRAFT,
		);
		$row = array_merge( $defaults, $row );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $row ) {
		global $wpdb;
		return $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function for_post( $post_id, $limit = 30 ) {
		global $wpdb;
		$t = self::table();
		$limit = max( 1, min( 200, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d", (int) $post_id, $limit ),
			ARRAY_A
		);
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$t = self::table();
		$limit = max( 1, min( 500, (int) $limit ) );
		return $wpdb->get_results(
			"SELECT * FROM {$t} ORDER BY created_at DESC LIMIT " . (int) $limit,
			ARRAY_A
		);
	}

	public static function count_for_post_platform( $post_id, $platform ) {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE post_id = %d AND platform = %s",
			(int) $post_id, $platform
		) );
	}
}
