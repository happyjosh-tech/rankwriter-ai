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

		if ( ! wp_next_scheduled( 'rwai_scheduled_blog_analysis' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'rwai_scheduled_blog_analysis' );
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
