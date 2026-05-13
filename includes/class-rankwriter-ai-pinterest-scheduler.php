<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pin scheduler — moves scheduled pins to "ready" when their scheduled
 * time arrives, and fires the rwai_pinterest_pin_ready action so external
 * automation (Zapier / Make / Pinterest Business API integrations / a
 * custom WP plugin) can pick them up and actually publish.
 *
 * RankWriter AI does NOT post to Pinterest directly — Pinterest's API
 * requires per-developer app review + OAuth + a business account. We
 * stop at "ready" and emit a clean handoff event.
 *
 * Lifecycle:
 *   draft     — just generated, not scheduled
 *   scheduled — user set a scheduled_at timestamp
 *   ready     — cron has fired, scheduled_at has passed, integration
 *               can now publish
 *   posted    — user manually marked posted (or integration callback
 *               flipped the status); pin_url is recorded
 *
 * Auto-generate-on-save: if the "Auto-generate pins on post publish"
 * setting is on, we hook save_post_post and queue a single async cron
 * tick (3 minutes out) to call the engine. That keeps the editor's
 * publish click instant.
 */
class RankWriter_AI_Pinterest_Scheduler {

	const CRON_DUE       = 'rwai_pinterest_due_tick';
	const CRON_GENERATE  = 'rwai_pinterest_auto_generate';

	public function register_hooks() {
		add_action( self::CRON_DUE,      array( $this, 'tick_due_pins' ) );
		add_action( self::CRON_GENERATE, array( $this, 'cron_auto_generate' ), 10, 1 );
		add_action( 'save_post_post',    array( $this, 'maybe_queue_auto_generate' ), 30, 3 );
	}

	public function schedule_recurring() {
		if ( ! wp_next_scheduled( self::CRON_DUE ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::CRON_DUE );
		}
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_DUE );
		wp_clear_scheduled_hook( self::CRON_GENERATE );
	}

	/**
	 * Promote any `scheduled` pins whose scheduled_at <= now to `ready`,
	 * and fire the action hook downstream automation listens to.
	 */
	public function tick_due_pins() {
		global $wpdb;
		$table = RankWriter_AI_Pinterest_DB::pins_table();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s LIMIT 20",
			'scheduled', current_time( 'mysql' )
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return;
		}
		$engine = new RankWriter_AI_Pinterest_Engine();
		foreach ( $rows as $row ) {
			$engine->update_pin( (int) $row['id'], array( 'status' => 'ready' ) );
			do_action( 'rwai_pinterest_pin_ready', (int) $row['id'], $row );
		}
	}

	/**
	 * Schedule a deferred auto-generation when a post is published, if the
	 * setting is enabled and the post has no pins yet. We DON'T generate
	 * inline — the publish action should be instant; the cron tick fires
	 * 3 minutes later.
	 */
	public function maybe_queue_auto_generate( $post_id, $post, $update ) {
		if ( ! self::auto_generate_enabled() ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		// Skip if pins already exist for this post.
		global $wpdb;
		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . RankWriter_AI_Pinterest_DB::pins_table() . ' WHERE post_id = %d', $post_id
		) );
		if ( (int) $existing > 0 ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_GENERATE, array( $post_id ) ) ) {
			return;
		}
		wp_schedule_single_event( time() + 3 * MINUTE_IN_SECONDS, self::CRON_GENERATE, array( $post_id ) );
	}

	public function cron_auto_generate( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) { return; }
		if ( ! self::auto_generate_enabled() ) { return; }

		$count  = (int) RankWriter_AI_Helpers::get_setting( 'pinterest_pins_per_post', 3 );
		$engine = new RankWriter_AI_Pinterest_Engine();
		$ids    = $engine->generate_for_post( $post_id, max( 1, min( 5, $count ) ) );

		// Optionally auto-render images for each generated pin.
		if ( ! is_wp_error( $ids ) && self::auto_render_images_enabled() ) {
			$image = new RankWriter_AI_Pinterest_Image();
			foreach ( (array) $ids as $pid ) {
				$image->render_for_pin( $pid );
			}
		}
	}

	public static function auto_generate_enabled() {
		return (int) RankWriter_AI_Helpers::get_setting( 'pinterest_auto_generate', 0 ) === 1;
	}

	public static function auto_render_images_enabled() {
		return (int) RankWriter_AI_Helpers::get_setting( 'pinterest_auto_render_images', 1 ) === 1;
	}
}
