<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Browser-side WP-Cron trigger.
 *
 * Why this exists: WordPress's built-in spawn_cron() function makes a
 * non-blocking HTTP POST to the site's own wp-cron.php to kick scheduled
 * events. On most hosts that returns in <50ms because of the
 * `blocking => false` flag. But on hosts where the firewall, load
 * balancer, or CDN blocks WordPress from connecting back to its own
 * public URL (Cloudflare-fronted sites, some shared hosts, restrictive
 * VPCs), the underlying TCP connect() call hangs at the OS layer for
 * the full PHP-FPM timeout — even though WordPress asked for non-
 * blocking — which produces nginx 504 Gateway Time-out on every admin
 * page that triggered the spawn.
 *
 * The fix: don't trigger WP-Cron server-to-server at all. Inject a tiny
 * <script> into the admin footer that fires `wp-cron.php` from the
 * user's browser. The browser-to-server connection always works (it's
 * the same path the user used to load the admin page in the first
 * place), so wp-cron.php runs reliably regardless of the host's
 * server-to-server loopback health.
 *
 * Trade-offs vs. spawn_cron():
 *   - PRO: works on every host. No 504s.
 *   - PRO: cheaper (browser fires it; PHP doesn't pay any CPU for it).
 *   - CON: only fires when an admin user has a wp-admin tab open. Real
 *          background processing (autopilot at 3am with no one logged
 *          in) still relies on natural visitor traffic hitting any
 *          frontend page OR a real system cron pointing at wp-cron.php.
 *          We surface this trade-off in the autopilot diagnostics panel.
 */
class RankWriter_AI_Browser_Cron {

	/**
	 * Cron hooks whose overdue state should trigger the browser-side
	 * cron fire. Mirrors RankWriter_AI_Schedule_Recovery::rwai_cron_hooks()
	 * plus WordPress's own `publish_future_post`.
	 */
	private static function watched_hooks() {
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
		// Only fire in wp-admin — frontend pages cache too aggressively
		// (page caches, Cloudflare full-page cache, etc.) for the
		// browser snippet to be reliable. Logged-in admin pages are
		// uncacheable so the snippet is always fresh.
		add_action( 'admin_footer', array( $this, 'maybe_emit_trigger' ) );
	}

	/**
	 * Emit a small JS snippet that fires wp-cron.php from the browser.
	 *
	 * Conditions:
	 *   - At least one watched cron hook is overdue OR there's a future-
	 *     scheduled post past its publish time OR there's a queued/stale
	 *     generation job waiting for a worker.
	 *   - The current user can manage the site (avoids spurious fires
	 *     from subscriber-level logins that landed in /wp-admin).
	 */
	public function maybe_emit_trigger() {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! $this->has_work_pending() ) {
			return;
		}

		$url = site_url( 'wp-cron.php?doing_wp_cron=' . sprintf( '%.22F', microtime( true ) ) );
		?>
		<script>
		(function () {
			// Fire wp-cron.php from the browser after the page settles.
			// We use a setTimeout so the admin page finishes painting
			// before we kick the background fetch — keeps the user's
			// interactive experience snappy.
			setTimeout(function () {
				try {
					var xhr = new XMLHttpRequest();
					xhr.open('GET', <?php echo wp_json_encode( esc_url_raw( $url ) ); ?>, true);
					// keepalive-style: if the user navigates away, the
					// request continues anyway. Most browsers honor this
					// for XHR; fetch() would too but XHR works on more
					// old browsers without polyfills.
					xhr.timeout = 30000;
					xhr.send();
				} catch (e) {
					/* swallow — best-effort */
				}
			}, 250);
		})();
		</script>
		<?php
	}

	/**
	 * Return true if there's pending background work (overdue cron,
	 * future post past its date, or queued/stale generation job). When
	 * nothing is pending we don't bother firing wp-cron.php on every
	 * admin page load — saves a request per page.
	 */
	private function has_work_pending() {
		$now = time();

		// Check 1: any watched RWAI cron hook overdue?
		foreach ( self::watched_hooks() as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( $next && $next <= $now ) {
				return true;
			}
		}

		// Check 2: missed-schedule future posts (the "post stuck at
		// future status past its date" symptom). Cheap DB query
		// because post_status='future' rows are very few.
		global $wpdb;
		$now_gmt = gmdate( 'Y-m-d H:i:s' );
		$missed  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			  WHERE post_status = 'future'
			    AND post_date_gmt <= %s
			    AND post_date_gmt != '0000-00-00 00:00:00'",
			$now_gmt
		) );
		if ( $missed > 0 ) {
			return true;
		}

		// Check 3: queued generation job waiting for a worker.
		if ( class_exists( 'RankWriter_AI_Generation_Queue' ) ) {
			$queue = new RankWriter_AI_Generation_Queue();
			foreach ( $queue->get_all_jobs() as $job ) {
				if ( 'queued' === ( $job['status'] ?? '' ) ) {
					return true;
				}
				// A "running" job whose started_at_ts is stale also counts
				// because the recovery sweep will reset it to queued on
				// the next sweep — better to fire cron now and let it
				// pick up immediately.
				if ( 'running' === ( $job['status'] ?? '' ) ) {
					$started = (int) ( $job['started_at_ts'] ?? 0 );
					if ( $started > 0 && ( $now - $started ) > 600 ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
