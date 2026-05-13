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
