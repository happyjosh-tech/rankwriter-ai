<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled-post recovery + cron self-healing.
 *
 * WordPress's "Schedule for later" feature relies on WP-Cron, which only
 * fires when a visitor hits the site. On low-traffic sites, a scheduled
 * post can sit in post_status="future" long past its publish date — the
 * familiar "Missed schedule" symptom. This class does two things on every
 * front-end + admin request (throttled to once per minute):
 *
 *   1) Find every post stuck in `future` with post_date_gmt <= now and
 *      publish it via wp_publish_post() so transition hooks fire properly.
 *   2) Detect any RankWriter cron hook whose next-run is in the past and
 *      spawn cron so the missed tick fires immediately instead of waiting
 *      for the next traffic event.
 *
 * Also exposes a one-shot manual "publish now" pass admins can trigger
 * from the Autopilot screen.
 */
class RankWriter_AI_Schedule_Recovery {

	const THROTTLE_TRANSIENT = 'rwai_schedule_recovery_last_run';
	const LOG_OPTION         = 'rwai_schedule_recovery_log';

	/**
	 * Cron hooks the recovery sweep will re-spawn if their next-run is in
	 * the past. Add more here as new recurring features are added.
	 */
	private static function rwai_cron_hooks() {
		return array(
			'rwai_autopilot_run',
			'rwai_autopilot_run_now',
			'rwai_generate_async',
			'rwai_pse_queue_tick',
			'rwai_pinterest_due',
			'rwai_pinterest_generate',
			'rwai_seo_healer_scan',
			'rwai_seo_healer_fix',
			'rwai_content_refresher_tick',
			'rwai_gap_detector_tick',
			'rwai_seasonal_tick',
			'rwai_scheduled_blog_analysis',
		);
	}

	public function register_hooks() {
		add_action( 'init',     array( $this, 'maybe_run_sweep' ), 20 );
		add_action( 'wp_loaded', array( $this, 'maybe_run_sweep' ), 20 );

		// Manual trigger (admin-post action).
		add_action( 'admin_post_rwai_publish_missed_now', array( $this, 'handle_manual_publish' ) );
	}

	/**
	 * Throttled entry point. Runs at most once per 60 seconds so even very
	 * high-traffic sites don't pay a query cost on every request.
	 */
	public function maybe_run_sweep() {
		if ( get_transient( self::THROTTLE_TRANSIENT ) ) {
			return;
		}
		set_transient( self::THROTTLE_TRANSIENT, 1, MINUTE_IN_SECONDS );

		$published = $this->publish_missed_scheduled_posts();
		$kicked    = $this->kick_missed_crons();

		if ( $published > 0 || $kicked > 0 ) {
			$this->log( sprintf( 'Recovery: published %d missed post(s), kicked %d stalled cron hook(s).', $published, $kicked ) );
		}
	}

	/**
	 * Publish every post stuck at post_status="future" with a publish date
	 * already in the past. Uses wp_publish_post() so post-status transition
	 * hooks (notifications, sitemaps, social autoposters, etc.) all fire.
	 *
	 * @return int  Count of posts published.
	 */
	public function publish_missed_scheduled_posts() {
		global $wpdb;

		// post_date_gmt is the authoritative comparison — independent of
		// site timezone. WP stores this for every post.
		$now_gmt = gmdate( 'Y-m-d H:i:s' );

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID
			   FROM {$wpdb->posts}
			  WHERE post_status = 'future'
			    AND post_date_gmt <= %s
			    AND post_date_gmt != '0000-00-00 00:00:00'
			  ORDER BY post_date_gmt ASC
			  LIMIT 50",
			$now_gmt
		) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( ! $pid ) {
				continue;
			}
			// wp_publish_post correctly fires transition_post_status from
			// future → publish, runs publish_post hooks, schedules cron
			// cleanup, etc. Safer than a raw wp_update_post call.
			wp_publish_post( $pid );

			// Clear the orphan publish_future_post cron entry that WP
			// originally registered when the post was scheduled — it's
			// no longer needed and otherwise sits in the queue.
			wp_clear_scheduled_hook( 'publish_future_post', array( $pid ) );

			$count++;
		}
		return $count;
	}

	/**
	 * For each RankWriter cron hook, if next_scheduled is in the past,
	 * spawn_cron() so it runs on this request. Doesn't reschedule —
	 * recurring events already do that themselves on each fire.
	 *
	 * @return int  Count of stalled hooks we kicked.
	 */
	public function kick_missed_crons() {
		$kicked = 0;
		$now    = time();
		foreach ( self::rwai_cron_hooks() as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( $next && $next <= $now ) {
				$kicked++;
			}
		}
		if ( $kicked > 0 && function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return $kicked;
	}

	/**
	 * Admin-post action: "Publish missed scheduled posts now" button.
	 * Bypasses the 60-second throttle for an on-demand sweep.
	 */
	public function handle_manual_publish() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'rankwriter-ai' ) );
		}
		check_admin_referer( 'rwai_publish_missed_now' );

		delete_transient( self::THROTTLE_TRANSIENT );
		$count  = $this->publish_missed_scheduled_posts();
		$kicked = $this->kick_missed_crons();

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=rwai-autopilot' );
		$redirect = add_query_arg(
			array(
				'rwai_recovery_published' => $count,
				'rwai_recovery_kicked'    => $kicked,
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function get_log( $limit = 20 ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( $log, 0, $limit );
	}

	private function log( $message ) {
		$log = $this->get_log( 100 );
		array_unshift( $log, array(
			'at'      => current_time( 'mysql' ),
			'message' => (string) $message,
		) );
		update_option( self::LOG_OPTION, array_slice( $log, 0, 100 ), false );
	}
}
