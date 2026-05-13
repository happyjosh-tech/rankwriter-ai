<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator. Wires modules into WordPress hooks.
 */
class RankWriter_AI {

	public function run() {

		$profiles = new RankWriter_AI_Category_Profiles();
		$profiles->register_hooks();

		add_action( 'rwai_scheduled_blog_analysis', array( $this, 'cron_run_analysis' ) );

		$autopilot = new RankWriter_AI_Autopilot();
		$autopilot->register_hooks();

		$schema_injector = new RankWriter_AI_Schema_Injector();
		$schema_injector->register_hooks();

		load_plugin_textdomain( 'rankwriter-ai', false, dirname( RWAI_PLUGIN_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			$admin = new RankWriter_AI_Admin();
			$admin->register_hooks();
		}
	}

	public function cron_run_analysis() {
		$analyzer = new RankWriter_AI_Blog_Analyzer();
		$signals  = $analyzer->analyze();
		$profile  = new RankWriter_AI_Style_Profile();
		$profile->build_and_save( $signals );
	}
}
