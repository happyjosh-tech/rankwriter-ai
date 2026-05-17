<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Async generation queue for manual "Generate Article" requests.
 *
 * Why: the full generation pipeline (keyword research + intent detection
 * + main Claude call + humanizer + image sourcing + fact-checker) can
 * take 90-180s end-to-end. Most managed hosts time out at nginx
 * (proxy_read_timeout) or PHP-FPM (request_terminate_timeout) somewhere
 * between 30-60s, so a synchronous request returns a 504 Gateway Time-out
 * to the browser even though Claude is still running on the backend.
 *
 * Solution: enqueue the job + fire a non-blocking WP-Cron event +
 * spawn_cron() to kick a loopback request that runs the generator in
 * the background. The browser gets a "Queued" page in under a second;
 * the article appears under Posts → All Posts within 1-3 minutes
 * regardless of how strict the front-edge nginx timeouts are.
 */
class RankWriter_AI_Generation_Queue {

	const CRON_HOOK        = 'rwai_generate_async';
	const JOBS_OPTION      = 'rwai_generation_jobs';
	const MAX_JOBS         = 50;
	const STALE_AFTER_SECS = 600;  // 10 minutes — anything still "running" past this is presumed dead
	const MAX_ATTEMPTS     = 3;

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'run_job' ), 10, 1 );

		// Listen for per-step progress hooks emitted from the generator so
		// the recent-jobs panel shows WHERE a long-running job is right
		// now (e.g. "keyword research" vs "main Claude call" vs
		// "humanizer"). If a worker dies, the last logged step tells us
		// exactly which stage was timing out.
		add_action( 'rwai_generation_step', array( $this, 'record_step' ), 10, 1 );

		// Manual "Reset & retry" button on the recent-jobs panel.
		add_action( 'admin_post_rwai_reset_generation_job', array( $this, 'handle_manual_reset' ) );
	}

	/**
	 * Admin-post action: reset a single job to "queued" and re-fire it.
	 * Used by the "Reset & retry" button on the recent-jobs panel so the
	 * user can unstick a job whose worker died without waiting for the
	 * 10-minute auto-recovery window.
	 */
	public function handle_manual_reset() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'rankwriter-ai' ) );
		}
		check_admin_referer( 'rwai_reset_generation_job' );

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( '' === $job_id ) {
			wp_die( esc_html__( 'Missing job ID.', 'rankwriter-ai' ) );
		}

		$jobs = $this->get_all_jobs();
		if ( isset( $jobs[ $job_id ] ) ) {
			$jobs[ $job_id ]['status']        = 'queued';
			$jobs[ $job_id ]['error']         = '';
			$jobs[ $job_id ]['started_at']    = '';
			$jobs[ $job_id ]['started_at_ts'] = 0;
			$jobs[ $job_id ]['ended_at']      = '';
			// Reset attempts so the user gets a fresh MAX_ATTEMPTS window.
			$jobs[ $job_id ]['attempts']      = 0;
			$jobs[ $job_id ]['progress']      = 'Manually reset';
			$jobs[ $job_id ]['progress_at']   = current_time( 'mysql' );
			update_option( self::JOBS_OPTION, $jobs, false );

			wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $job_id ) );
			// Browser-cron trigger (admin_footer in RankWriter_AI_Browser_Cron)
			// will fire wp-cron.php from the user's tab. We deliberately do
			// NOT call spawn_cron() server-side — see schedule-recovery
			// notes for why that hangs on some hosts.
		}

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=rankwriter-ai-generate' );
		$redirect = add_query_arg( array( 'rwai_msg' => 'generate-reset', 'job' => rawurlencode( $job_id ) ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Enqueue a new generation job. Returns the job ID.
	 *
	 * @param array $args Same shape as RankWriter_AI_Content_Generator::generate().
	 * @return string Job ID.
	 */
	public function enqueue( array $args ) {
		$job_id = 'rwai_' . wp_generate_password( 12, false, false );

		$job = array(
			'id'              => $job_id,
			'args'            => $args,
			'status'          => 'queued',
			'post_id'         => 0,
			'error'           => '',
			'queued_at'       => current_time( 'mysql' ),
			'started_at'      => '',
			'ended_at'        => '',
			'topic'           => isset( $args['topic'] ) ? (string) $args['topic'] : '',
			'user_id'         => get_current_user_id(),
			'attempts'        => 0,
			'progress'        => '',
			'progress_at'     => '',
			'started_at_ts'   => 0,  // unix timestamp — used to detect stale "running" jobs
		);

		$jobs = $this->get_all_jobs();
		$jobs[ $job_id ] = $job;
		// Keep only the most recent N jobs.
		if ( count( $jobs ) > self::MAX_JOBS ) {
			$jobs = array_slice( $jobs, -self::MAX_JOBS, null, true );
		}
		update_option( self::JOBS_OPTION, $jobs, false );

		// Schedule for immediate firing. The +1 buffer gives spawn_cron()
		// time to detect the event as due.
		wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $job_id ) );

		// WP-Cron will fire this event the next time wp-cron.php is hit.
		// We used to call spawn_cron() here to trigger an immediate
		// server-side loopback, but on hosts that block WordPress's
		// outbound HTTP to its own public URL (Cloudflare front, locked-
		// down firewalls) that hangs at the TCP layer for the full PHP
		// timeout and 504s the admin redirect. RankWriter_AI_Browser_Cron
		// triggers wp-cron.php from the user's browser instead, which
		// works on every host because the browser → server direction is
		// never blocked.

		return $job_id;
	}

	/**
	 * Cron handler. Runs ONE job to completion. The cron loopback request
	 * is the long-running one, but it's a non-blocking background request
	 * so the user's HTTP timeout no longer applies.
	 *
	 * Resilience contract:
	 *   - "done" / "failed" jobs are never re-run (terminal states).
	 *   - "running" jobs are re-run if started_at_ts is older than
	 *     STALE_AFTER_SECS — assumption is the previous worker process
	 *     was killed by PHP-FPM / nginx without a chance to mark the
	 *     job terminal. Without this, a job stuck at "running" because
	 *     its first worker died would never retry.
	 *   - attempts is incremented per try; after MAX_ATTEMPTS the job is
	 *     marked failed.
	 */
	public function run_job( $job_id ) {
		$jobs = $this->get_all_jobs();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return;
		}
		$job = $jobs[ $job_id ];

		// Terminal states — never re-run.
		if ( in_array( $job['status'], array( 'done', 'failed' ), true ) ) {
			return;
		}

		// "running" → only re-run if it's stale (previous worker died).
		if ( 'running' === $job['status'] ) {
			$started_ts = (int) ( $job['started_at_ts'] ?? 0 );
			if ( $started_ts > 0 && ( time() - $started_ts ) < self::STALE_AFTER_SECS ) {
				return; // Another worker is genuinely processing this right now.
			}
			// Stale — record the death and proceed.
			$job['progress'] = sprintf( 'Previous worker died at: %s (auto-retry)', (string) ( $job['progress'] ?: 'unknown step' ) );
		}

		$attempts = (int) ( $job['attempts'] ?? 0 );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$job['status']   = 'failed';
			$job['error']    = sprintf( __( 'Failed after %d attempts — last known step: %s. The generation pipeline is likely exceeding your host\'s PHP timeout. Try toggling "Humanize pass" OFF in Settings to shorten the critical path.', 'rankwriter-ai' ), $attempts, (string) ( $job['progress'] ?: 'unknown' ) );
			$job['ended_at'] = current_time( 'mysql' );
			$jobs[ $job_id ] = $job;
			update_option( self::JOBS_OPTION, $jobs, false );
			return;
		}

		// Give the generator plenty of headroom inside the background
		// request. ignore_user_abort lets the script keep running even
		// if the loopback HTTP connection is closed.
		@set_time_limit( 600 );
		@ignore_user_abort( true );

		$job['status']        = 'running';
		$job['started_at']    = current_time( 'mysql' );
		$job['started_at_ts'] = time();
		$job['attempts']      = $attempts + 1;
		$jobs[ $job_id ]      = $job;
		update_option( self::JOBS_OPTION, $jobs, false );

		// Track which job the rwai_generation_step hook should record
		// progress against. Set via class state so the static callback
		// path inside record_step() knows which job to update.
		$GLOBALS['rwai_active_job_id'] = $job_id;

		$gen     = new RankWriter_AI_Content_Generator();
		$args    = is_array( $job['args'] ) ? $job['args'] : array();
		// Mark autopilot=true so generate() skips the current-user permission
		// check (we already validated when enqueueing). The author_id falls
		// back to the originally-submitting user if set.
		$args['autopilot'] = true;
		if ( ! empty( $job['user_id'] ) ) {
			$args['author_id'] = (int) $job['user_id'];
		}
		$post_id = $gen->generate( $args );

		unset( $GLOBALS['rwai_active_job_id'] );

		// Re-read jobs to avoid losing updates if the option changed
		// while we were running.
		$jobs = $this->get_all_jobs();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return;
		}
		$job = $jobs[ $job_id ];
		$job['ended_at'] = current_time( 'mysql' );

		if ( is_wp_error( $post_id ) ) {
			$job['status']  = 'failed';
			$job['error']   = $post_id->get_error_message();
		} else {
			$job['status']  = 'done';
			$job['post_id'] = (int) $post_id;
		}

		$jobs[ $job_id ] = $job;
		update_option( self::JOBS_OPTION, $jobs, false );
	}

	/**
	 * Hooked into `rwai_generation_step` (fired from Content_Generator)
	 * to update the job's per-step progress so a stuck job tells us
	 * exactly which stage it's on right now.
	 */
	public function record_step( $step ) {
		$job_id = isset( $GLOBALS['rwai_active_job_id'] ) ? (string) $GLOBALS['rwai_active_job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}
		$jobs = $this->get_all_jobs();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return;
		}
		$jobs[ $job_id ]['progress']    = (string) $step;
		$jobs[ $job_id ]['progress_at'] = current_time( 'mysql' );
		update_option( self::JOBS_OPTION, $jobs, false );
	}

	/**
	 * Find every job stuck at "running" whose started_at_ts is older
	 * than STALE_AFTER_SECS, reset it to "queued", and re-fire the cron.
	 * Called from the schedule-recovery sweep so stuck jobs self-heal
	 * on the next admin page load instead of sitting forever.
	 *
	 * @return int  Number of jobs reset.
	 */
	public function recover_stale_running_jobs() {
		$jobs    = $this->get_all_jobs();
		$now     = time();
		$resets  = 0;
		$changed = false;

		foreach ( $jobs as $id => &$job ) {
			if ( 'running' !== ( $job['status'] ?? '' ) ) {
				continue;
			}
			$started_ts = (int) ( $job['started_at_ts'] ?? 0 );
			if ( 0 === $started_ts ) {
				// Job started before this field existed — best-effort: parse started_at.
				if ( ! empty( $job['started_at'] ) ) {
					$started_ts = (int) strtotime( (string) $job['started_at'] );
				}
			}
			if ( $started_ts > 0 && ( $now - $started_ts ) < self::STALE_AFTER_SECS ) {
				continue; // Still inside the live window.
			}

			$last_step = (string) ( $job['progress'] ?: 'unknown step' );
			$attempts  = (int) ( $job['attempts'] ?? 0 );

			if ( $attempts >= self::MAX_ATTEMPTS ) {
				$job['status']   = 'failed';
				$job['error']    = sprintf( __( 'Worker died %d times in a row, last step: %s. Likely cause: your host\'s PHP-FPM is killing the worker mid-generation. Toggle Humanize pass OFF in Settings to halve the critical path.', 'rankwriter-ai' ), $attempts, $last_step );
				$job['ended_at'] = current_time( 'mysql' );
				$changed = true;
				continue;
			}

			$job['status']   = 'queued';
			$job['progress'] = 'Auto-reset after stale "running" — last step: ' . $last_step;
			$job['started_at_ts'] = 0;
			$changed = true;
			$resets++;

			wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $id ) );
		}
		unset( $job );

		if ( $changed ) {
			update_option( self::JOBS_OPTION, $jobs, false );
			// Re-firing happens via browser-side cron (admin_footer hook in
			// RankWriter_AI_Browser_Cron) — no server-side spawn_cron call
			// to avoid the loopback-HTTP hang on locked-down hosts.
		}
		return $resets;
	}

	public function get_job( $job_id ) {
		$jobs = $this->get_all_jobs();
		return isset( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : null;
	}

	/**
	 * Return jobs in newest-first order. Optionally filtered by status.
	 */
	public function get_recent( $limit = 10, $status = '' ) {
		$jobs = array_reverse( $this->get_all_jobs(), true );
		if ( '' !== $status ) {
			$jobs = array_filter( $jobs, function ( $j ) use ( $status ) {
				return isset( $j['status'] ) && $j['status'] === $status;
			} );
		}
		return array_slice( $jobs, 0, max( 1, (int) $limit ), true );
	}

	public function get_all_jobs() {
		$jobs = get_option( self::JOBS_OPTION, array() );
		return is_array( $jobs ) ? $jobs : array();
	}

	public function clear_finished() {
		$jobs = $this->get_all_jobs();
		$kept = array();
		foreach ( $jobs as $id => $j ) {
			if ( ! isset( $j['status'] ) || ! in_array( $j['status'], array( 'done', 'failed' ), true ) ) {
				$kept[ $id ] = $j;
			}
		}
		update_option( self::JOBS_OPTION, $kept, false );
	}

	/**
	 * Manually re-fire a stuck "queued" job. Used by the admin "Retry" link.
	 */
	public function retry( $job_id ) {
		$jobs = $this->get_all_jobs();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return false;
		}
		$jobs[ $job_id ]['status']     = 'queued';
		$jobs[ $job_id ]['error']      = '';
		$jobs[ $job_id ]['started_at'] = '';
		$jobs[ $job_id ]['ended_at']   = '';
		update_option( self::JOBS_OPTION, $jobs, false );

		wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $job_id ) );
		// Browser-cron trigger (see RankWriter_AI_Browser_Cron) fires
		// wp-cron.php from the user's tab; no server-side spawn_cron.
		return true;
	}
}
