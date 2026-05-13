<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankWriter_AI_Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( 'rwai_scheduled_blog_analysis' );
		wp_clear_scheduled_hook( 'rwai_autopilot_run' );
		flush_rewrite_rules();
	}
}
