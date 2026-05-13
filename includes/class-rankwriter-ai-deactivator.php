<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankWriter_AI_Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( 'rwai_scheduled_blog_analysis' );
		wp_clear_scheduled_hook( 'rwai_autopilot_run' );
		wp_clear_scheduled_hook( 'rwai_pse_queue_run' );
		if ( class_exists( 'RankWriter_AI_Pinterest_Scheduler' ) ) {
			RankWriter_AI_Pinterest_Scheduler::clear_schedules();
		}
		wp_clear_scheduled_hook( 'rwai_auto_translate_run' );
		if ( class_exists( 'RankWriter_AI_Gap_Detector' ) ) {
			RankWriter_AI_Gap_Detector::clear_schedules();
		}
		if ( class_exists( 'RankWriter_AI_Content_Refresher' ) ) {
			RankWriter_AI_Content_Refresher::clear_schedules();
		}
		if ( class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			RankWriter_AI_Seasonal_Engine::clear_schedules();
		}
		if ( class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			RankWriter_AI_SEO_Healer::clear_schedules();
		}
		flush_rewrite_rules();
	}
}
