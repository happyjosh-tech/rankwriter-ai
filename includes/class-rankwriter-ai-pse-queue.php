<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron-driven PSE queue worker + admin-triggered batch runner.
 *
 * Behavior:
 *   - register_hooks() wires the cron action.
 *   - tick() pulls the next N pending rows and generates each one.
 *   - run_batch( $n ) does the same synchronously from the admin (used
 *     for "Run 5 now" buttons).
 *
 * Batch size is capped at the user's setting `pse_batch_size` (default 3).
 * That keeps a single cron tick cheap and prevents API throttling.
 */
class RankWriter_AI_PSE_Queue {

	const CRON_HOOK = 'rwai_pse_queue_run';

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'tick' ) );
	}

	public function schedule_if_enabled() {
		$cfg = self::get_config();
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( ! empty( $cfg['enabled'] ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS * 5, $cfg['frequency'], self::CRON_HOOK );
		}
	}

	public static function get_config() {
		$defaults = array(
			'enabled'    => 0,
			'frequency'  => 'hourly',
			'batch_size' => 3,
		);
		$saved = get_option( 'rwai_pse_queue_config', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function save_config( array $values ) {
		$cur = self::get_config();
		$cur['enabled']    = ! empty( $values['enabled'] ) ? 1 : 0;
		$cur['frequency']  = in_array( ( $values['frequency'] ?? 'hourly' ), array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ? $values['frequency'] : 'hourly';
		$cur['batch_size'] = max( 1, min( 10, (int) ( $values['batch_size'] ?? 3 ) ) );
		update_option( 'rwai_pse_queue_config', $cur, false );
		( new self() )->schedule_if_enabled();
		return $cur;
	}

	public function next_run() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $ts ) {
			return '';
		}
		$tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$local = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz );
		return $local->format( 'Y-m-d H:i' ) . ' (' . $tz->getName() . ')';
	}

	public function tick() {
		$cfg = self::get_config();
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}
		$this->run_batch( (int) $cfg['batch_size'] );
	}

	/**
	 * Synchronous batch run. Used by the admin "Run N now" button and by
	 * the cron tick. Returns counts.
	 */
	public function run_batch( $n = 3 ) {
		$n       = max( 1, min( 10, (int) $n ) );
		$manager = new RankWriter_AI_PSE_Manager();
		$engine  = new RankWriter_AI_PSE_Engine();
		$rows    = $manager->next_pending_rows( $n );

		$results = array( 'attempted' => 0, 'generated' => 0, 'failed' => 0, 'errors' => array() );
		foreach ( $rows as $row ) {
			$results['attempted']++;
			$post_id_or_error = $engine->generate_row( (int) $row['id'] );
			if ( is_wp_error( $post_id_or_error ) ) {
				$results['failed']++;
				$results['errors'][] = $post_id_or_error->get_error_message();
			} else {
				$results['generated']++;
			}
		}
		return $results;
	}
}
