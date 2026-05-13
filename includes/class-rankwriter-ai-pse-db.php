<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom database tables for the Programmatic SEO Engine.
 *
 *   {prefix}rwai_pse_templates  — one row per programmatic template
 *                                 (recipe + outline + variation rules)
 *   {prefix}rwai_pse_rows       — one row per variable-combination that
 *                                 should become an article
 *
 * Why custom tables:
 *   - A template can spawn 1,000s of dataset rows (city × profession × year).
 *     CPT + post-meta would be far too slow for queue scans and uniqueness
 *     checks.
 *   - The unique key on (template_id, values_hash) gives DB-level dedupe.
 *   - Indexed status column lets the cron worker grab the next N pending
 *     rows in O(log n).
 */
class RankWriter_AI_PSE_DB {

	const DB_VERSION_OPTION = 'rwai_pse_db_version';
	const DB_VERSION        = '1.0.0';

	public static function templates_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_pse_templates';
	}

	public static function rows_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_pse_rows';
	}

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$templates = self::templates_table();
		$rows      = self::rows_table();

		$sql_templates = "CREATE TABLE {$templates} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			title_template TEXT NOT NULL,
			slug_template VARCHAR(500) NOT NULL,
			intent VARCHAR(20) NOT NULL DEFAULT 'informational',
			outline_json LONGTEXT NOT NULL,
			variables_json LONGTEXT NOT NULL,
			semantic_keywords TEXT NULL,
			profile_id BIGINT UNSIGNED NULL,
			cluster_id BIGINT UNSIGNED NULL,
			min_word_count INT NOT NULL DEFAULT 1200,
			min_uniqueness TINYINT UNSIGNED NOT NULL DEFAULT 70,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY profile_id (profile_id)
		) {$charset};";

		$sql_rows = "CREATE TABLE {$rows} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id BIGINT UNSIGNED NOT NULL,
			values_json LONGTEXT NOT NULL,
			values_hash CHAR(32) NOT NULL,
			post_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			uniqueness_score TINYINT UNSIGNED NULL,
			variant_signature VARCHAR(32) NULL,
			error_message TEXT NULL,
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			queued_at DATETIME NULL,
			generated_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY template_hash (template_id, values_hash),
			KEY status (status),
			KEY post_id (post_id),
			KEY template_id (template_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_templates );
		dbDelta( $sql_rows );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function uninstall() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::rows_table() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::templates_table() );
		delete_option( self::DB_VERSION_OPTION );
	}

	public static function ready() {
		global $wpdb;
		$t = self::templates_table();
		$r = self::rows_table();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name IN (%s, %s)",
				DB_NAME, $t, $r
			)
		);
		return 2 === (int) $found;
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}
}
