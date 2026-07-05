<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankWriter_AI_Activator {

	public static function activate() {

		if ( ! post_type_exists( 'rwai_category' ) ) {
			register_post_type(
				'rwai_category',
				array(
					'public'      => false,
					'show_ui'     => false,
					'has_archive' => false,
				)
			);
		}

		// Defer to the helpers' canonical defaults so this list never drifts
		// from get_settings(). The helper merges via wp_parse_args anyway, so
		// we only seed the option on first activation.
		if ( ! get_option( 'rwai_settings' ) ) {
			add_option( 'rwai_settings', RankWriter_AI_Helpers::get_settings() );
		}

		add_option( 'rwai_db_version', RWAI_VERSION );

		// Create / upgrade the cluster engine tables.
		if ( class_exists( 'RankWriter_AI_Clusters_DB' ) ) {
			RankWriter_AI_Clusters_DB::install();
		}

		// Create / upgrade Programmatic SEO tables + seed starter templates.
		if ( class_exists( 'RankWriter_AI_PSE_DB' ) ) {
			RankWriter_AI_PSE_DB::install();
			if ( class_exists( 'RankWriter_AI_PSE_Presets' ) ) {
				RankWriter_AI_PSE_Presets::seed( false );
			}
		}

		// Create / upgrade Pinterest tables + register recurring "due pins" cron.
		if ( class_exists( 'RankWriter_AI_Pinterest_DB' ) ) {
			RankWriter_AI_Pinterest_DB::install();
		}
		if ( class_exists( 'RankWriter_AI_Pinterest_Scheduler' ) ) {
			( new RankWriter_AI_Pinterest_Scheduler() )->schedule_recurring();
		}

		if ( ! wp_next_scheduled( 'rwai_scheduled_blog_analysis' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'rwai_scheduled_blog_analysis' );
		}

		// Schedule the Content Gap Detector's weekly audit so the dashboard
		// has data even before the admin manually clicks "Run audit now".
		if ( class_exists( 'RankWriter_AI_Gap_Detector' ) ) {
			( new RankWriter_AI_Gap_Detector() )->schedule_recurring();
		}

		// Install the refresh-log table. The cron is NOT auto-scheduled
		// here — the user must explicitly toggle "Enable auto-refresh"
		// on the Auto Update page first, since the refresher consumes
		// Claude API credits.
		if ( class_exists( 'RankWriter_AI_Refresher_DB' ) ) {
			RankWriter_AI_Refresher_DB::install();
		}

		// Schedule the seasonal engine's daily coverage refresh — cheap
		// option-cache write, no API spend.
		if ( class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			( new RankWriter_AI_Seasonal_Engine() )->schedule_recurring();
		}

		// Install the syndication log table for Parasite SEO Mode.
		if ( class_exists( 'RankWriter_AI_Syndication_DB' ) ) {
			RankWriter_AI_Syndication_DB::install();
		}

		// Install the SEO Healer issues + repair-log tables. The cron is
		// only scheduled if the user toggles the healer on later, since
		// auto-fixes can hit the Claude API.
		if ( class_exists( 'RankWriter_AI_SEO_Healer_DB' ) ) {
			RankWriter_AI_SEO_Healer_DB::install();
		}

		// Install the Bot Blocker's blocked-hit log table. Blocking itself
		// stays off until the admin enables it on the Bot Blocker page.
		if ( class_exists( 'RankWriter_AI_Bot_Blocker_DB' ) ) {
			RankWriter_AI_Bot_Blocker_DB::install();
		}

		// Create the Speed Optimizer cache dir up front so the first
		// front-end request after activation doesn't race on mkdir.
		// The orchestrator's ensure_cache_dir() also handles this, but
		// running it on activation gives us the right ownership when
		// the admin clicks "Activate" vs. a deferred first hit.
		if ( class_exists( 'RankWriter_AI_Speed_Optimizer' ) ) {
			( new RankWriter_AI_Speed_Optimizer() )->ensure_cache_dir();
		}

		// Seed the built-in category presets if none exist yet. Subsequent
		// activations are non-destructive — already-existing or user-deleted
		// presets are left alone (the user can re-seed via the admin button).
		$profiles = new RankWriter_AI_Category_Profiles();
		$counts = wp_count_posts( RankWriter_AI_Category_Profiles::POST_TYPE );
		$has_any = ( isset( $counts->publish ) && $counts->publish > 0 ) || ( isset( $counts->draft ) && $counts->draft > 0 );
		if ( ! $has_any ) {
			$profiles->seed_presets( false );
		}

		flush_rewrite_rules();
	}
}
