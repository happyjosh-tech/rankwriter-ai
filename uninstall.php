<?php
/**
 * Uninstall handler — runs when the user deletes the plugin from the WP admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rwai_options = array(
	'rwai_settings',
	'rwai_blog_style_profile',
	'rwai_last_analysis_run',
	'rwai_db_version',
	'rwai_keyword_pool',
	'rwai_autopilot_queue',
	'rwai_autopilot_log',
	'rwai_autopilot_config',
	'rwai_legal_settings',
	'rwai_gap_last_audit',
	'rwai_refresher_settings',
	'rwai_refresher_db_version',
	'rwai_schema_organization',
	'rwai_seasonal_coverage_cache',
	'rwai_seasonal_dismissed',
	'rwai_voice_profile',
	'rwai_syndication_db_version',
	'rwai_seo_healer_settings',
	'rwai_seo_healer_cursor',
	'rwai_seo_healer_dup_cache',
	'rwai_seo_healer_db_version',
);

foreach ( $rwai_options as $rwai_opt ) {
	delete_option( $rwai_opt );
	delete_site_option( $rwai_opt );
}

$rwai_profiles = get_posts(
	array(
		'post_type'      => 'rwai_category',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	)
);

if ( ! empty( $rwai_profiles ) ) {
	foreach ( $rwai_profiles as $rwai_pid ) {
		wp_delete_post( $rwai_pid, true );
	}
}

wp_clear_scheduled_hook( 'rwai_scheduled_blog_analysis' );
wp_clear_scheduled_hook( 'rwai_autopilot_run' );
wp_clear_scheduled_hook( 'rwai_pse_queue_run' );

// Drop the cluster engine tables.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_cluster_topics" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_clusters" );
delete_option( 'rwai_clusters_db_version' );

// Drop the Programmatic SEO tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_pse_rows" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_pse_templates" );
delete_option( 'rwai_pse_db_version' );
delete_option( 'rwai_pse_queue_config' );

// Drop Pinterest tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_pinterest_pins" );
delete_option( 'rwai_pinterest_db_version' );
wp_clear_scheduled_hook( 'rwai_pinterest_due_tick' );
wp_clear_scheduled_hook( 'rwai_pinterest_auto_generate' );
wp_clear_scheduled_hook( 'rwai_auto_translate_run' );
wp_clear_scheduled_hook( 'rwai_gap_audit_run' );
wp_clear_scheduled_hook( 'rwai_refresher_tick' );
wp_clear_scheduled_hook( 'rwai_seasonal_tick' );

// Drop refresher log table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_refresh_log" );

// Drop syndication log table (Parasite SEO).
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_syndications" );

// Drop SEO Healer tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_seo_repair_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rwai_seo_issues" );
wp_clear_scheduled_hook( 'rwai_seo_healer_scan_tick' );
wp_clear_scheduled_hook( 'rwai_seo_healer_fix_tick' );
