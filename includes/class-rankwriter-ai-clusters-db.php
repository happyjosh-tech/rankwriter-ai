<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom database tables for the Topical Authority Cluster Engine.
 *
 * Two tables, narrow and indexed:
 *
 *   {prefix}rwai_clusters         — one row per topic cluster
 *   {prefix}rwai_cluster_topics   — one row per supporting topic inside a cluster
 *
 * Custom tables (vs CPT + meta) chosen for scalability: hundreds of
 * clusters with thousands of topics each must remain fast to list, count,
 * and filter. Indexed columns keep dashboard queries O(log n).
 */
class RankWriter_AI_Clusters_DB {

	const DB_VERSION_OPTION = 'rwai_clusters_db_version';
	const DB_VERSION        = '1.0.0';

	public static function clusters_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_clusters';
	}

	public static function topics_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwai_cluster_topics';
	}

	/**
	 * Idempotent — safe to call on every activation or upgrade.
	 */
	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$clusters = self::clusters_table();
		$topics   = self::topics_table();

		$sql_clusters = "CREATE TABLE {$clusters} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			pillar_post_id BIGINT UNSIGNED NULL,
			profile_id BIGINT UNSIGNED NULL,
			target_supporting_count SMALLINT UNSIGNED NOT NULL DEFAULT 6,
			semantic_keywords TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY pillar_post_id (pillar_post_id),
			KEY profile_id (profile_id)
		) {$charset};";

		$sql_topics = "CREATE TABLE {$topics} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cluster_id BIGINT UNSIGNED NOT NULL,
			topic VARCHAR(500) NOT NULL,
			post_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'suggested',
			semantic_keywords TEXT NULL,
			priority SMALLINT NOT NULL DEFAULT 100,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY cluster_id (cluster_id),
			KEY post_id (post_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_clusters );
		dbDelta( $sql_topics );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function uninstall() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::topics_table() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::clusters_table() );
		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Returns true if both tables exist. Used by the manager to fail soft
	 * when activation didn't run (e.g., during plugin file replacement).
	 */
	public static function ready() {
		global $wpdb;
		$clusters = self::clusters_table();
		$topics   = self::topics_table();
		$found    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name IN (%s, %s)",
				DB_NAME,
				$clusters,
				$topics
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
