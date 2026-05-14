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

	const CRON_HOOK   = 'rwai_generate_async';
	const JOBS_OPTION = 'rwai_generation_jobs';
	const MAX_JOBS    = 50;

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'run_job' ), 10, 1 );
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
			'id'         => $job_id,
			'args'       => $args,
			'status'     => 'queued',
			'post_id'    => 0,
			'error'      => '',
			'queued_at'  => current_time( 'mysql' ),
			'started_at' => '',
			'ended_at'   => '',
			'topic'      => isset( $args['topic'] ) ? (string) $args['topic'] : '',
			'user_id'    => get_current_user_id(),
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

		// Fire the cron loopback now — non-blocking — so we don't wait for
		// the next visitor to wake WP-Cron.
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		return $job_id;
	}

	/**
	 * Cron handler. Runs ONE job to completion. The cron loopback request
	 * is the long-running one, but it's a non-blocking background request
	 * so the user's HTTP timeout no longer applies.
	 */
	public function run_job( $job_id ) {
		$jobs = $this->get_all_jobs();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return;
		}
		$job = $jobs[ $job_id ];

		// Idempotency: if already finished or already running, skip.
		if ( in_array( $job['status'], array( 'done', 'failed', 'running' ), true ) ) {
			return;
		}

		// Give the generator plenty of headroom inside the background
		// request. ignore_user_abort lets the script keep running even
		// if the loopback HTTP connection is closed.
		@set_time_limit( 600 );
		@ignore_user_abort( true );

		$job['status']     = 'running';
		$job['started_at'] = current_time( 'mysql' );
		$jobs[ $job_id ]   = $job;
		update_option( self::JOBS_OPTION, $jobs, false );

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
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return true;
	}
}
