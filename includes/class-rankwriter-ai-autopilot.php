<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autopilot: scheduled, hands-off article generation.
 *
 * - A per-category-profile queue of fresh seed keywords + competitor-style
 *   working titles is fetched by RankWriter_AI_Keyword_Research.
 * - WP-Cron fires on a configurable cadence and pops the next item off the
 *   queue, calling RankWriter_AI_Content_Generator with it.
 * - Result can be saved as a draft (default) or auto-published.
 */
class RankWriter_AI_Autopilot {

	const QUEUE_OPTION  = 'rwai_autopilot_queue';
	const LOG_OPTION    = 'rwai_autopilot_log';
	const CONFIG_OPTION = 'rwai_autopilot_config';
	const CRON_HOOK     = 'rwai_autopilot_run';

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'tick' ) );
	}

	public function get_config() {
		$defaults = array(
			'enabled'           => 0,
			'frequency'         => 'daily',
			'profile_id'        => 0,
			'seed_keywords'     => '',
			'country'           => 'US',
			'post_status'       => 'draft',
			'word_count'        => 0,
			'auto_seo'          => 1,
			'max_per_run'       => 1,
			'max_tags'          => 2,
			'wp_category_id'    => 0,
			'run_time'          => '09:00',
			'run_day_of_week'   => 1, // Monday (0 = Sunday)
		);
		$saved = get_option( self::CONFIG_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public function save_config( array $values ) {
		$current = $this->get_config();
		$merged  = array_merge( $current, $values );
		$merged['enabled']         = ! empty( $merged['enabled'] ) ? 1 : 0;
		$merged['auto_seo']        = ! empty( $merged['auto_seo'] ) ? 1 : 0;
		$merged['profile_id']      = absint( $merged['profile_id'] );
		$merged['word_count']      = absint( $merged['word_count'] );
		$merged['max_per_run']     = max( 1, min( 5, absint( $merged['max_per_run'] ) ) );
		$merged['max_tags']        = max( 0, min( 10, absint( $merged['max_tags'] ) ) );
		$merged['wp_category_id']  = absint( $merged['wp_category_id'] );
		$merged['post_status']     = in_array( $merged['post_status'], array( 'draft', 'publish', 'pending' ), true ) ? $merged['post_status'] : 'draft';
		$merged['frequency']       = in_array( $merged['frequency'], array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ? $merged['frequency'] : 'daily';
		$merged['country']         = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $merged['country'] ), 0, 2 ) ) ?: 'US';
		$merged['seed_keywords']   = sanitize_textarea_field( (string) $merged['seed_keywords'] );
		$merged['run_time']        = $this->sanitize_run_time( $merged['run_time'] );
		$merged['run_day_of_week'] = max( 0, min( 6, (int) $merged['run_day_of_week'] ) );

		update_option( self::CONFIG_OPTION, $merged, false );

		$this->reschedule( $merged );
		return $merged;
	}

	private function sanitize_run_time( $raw ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m ) ) {
			return '09:00';
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}

	/**
	 * Schedule the cron hook so it fires at the configured time of day
	 * (in the site's timezone). For weekly, also targets the chosen day.
	 *
	 * WP-Cron is pseudo-cron — it only ticks when site traffic hits, so
	 * "exact" time means "first visit after the scheduled timestamp".
	 */
	private function reschedule( array $cfg ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}

		$first_fire = $this->compute_first_fire( $cfg );
		wp_schedule_event( $first_fire, $cfg['frequency'], self::CRON_HOOK );
	}

	/**
	 * Compute the next Unix timestamp (UTC) at which the cron should first
	 * fire, given the configured frequency + run_time + run_day_of_week.
	 *
	 * - hourly:     5 minutes from now (time-of-day doesn't apply)
	 * - twicedaily: next occurrence of run_time, then every 12h
	 * - daily:      next occurrence of run_time
	 * - weekly:     next run_day_of_week at run_time, then every 7d
	 */
	private function compute_first_fire( array $cfg ) {
		if ( 'hourly' === $cfg['frequency'] ) {
			return time() + ( 5 * MINUTE_IN_SECONDS );
		}

		$tz   = wp_timezone();
		$now  = new DateTimeImmutable( 'now', $tz );
		list( $h, $m ) = array_map( 'intval', explode( ':', $cfg['run_time'] ) );

		$target = $now->setTime( $h, $m, 0 );

		if ( 'weekly' === $cfg['frequency'] ) {
			$target_dow  = (int) $cfg['run_day_of_week']; // 0 = Sunday
			$current_dow = (int) $target->format( 'w' );
			$diff_days   = ( $target_dow - $current_dow + 7 ) % 7;
			if ( 0 === $diff_days && $target <= $now ) {
				$diff_days = 7;
			}
			if ( $diff_days > 0 ) {
				$target = $target->modify( '+' . $diff_days . ' days' );
			}
		} else {
			// daily or twicedaily
			if ( $target <= $now ) {
				$target = $target->modify( '+1 day' );
			}
		}

		return $target->getTimestamp();
	}

	public function next_run() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $ts ) {
			return '';
		}
		$tz    = wp_timezone();
		$local = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz );
		$tz_label = $tz->getName();
		return $local->format( 'Y-m-d H:i' ) . ' (' . $tz_label . ')';
	}

	public function get_queue() {
		$q = get_option( self::QUEUE_OPTION, array() );
		return is_array( $q ) ? $q : array();
	}

	public function set_queue( array $items ) {
		update_option( self::QUEUE_OPTION, array_values( $items ), false );
	}

	public function clear_queue() {
		delete_option( self::QUEUE_OPTION );
	}

	public function refill_queue() {
		$cfg = $this->get_config();
		if ( empty( $cfg['profile_id'] ) ) {
			return new WP_Error( 'rwai_no_profile', __( 'Autopilot needs a category profile selected.', 'rankwriter-ai' ) );
		}

		$seeds = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $cfg['seed_keywords'] ) ) );
		if ( empty( $seeds ) ) {
			return new WP_Error( 'rwai_no_seeds', __( 'Add at least one seed keyword in the Autopilot settings.', 'rankwriter-ai' ) );
		}

		$research  = new RankWriter_AI_Keyword_Research();
		$competitors = $this->competitor_list();
		$queue     = $this->get_queue();
		$existing  = array();
		foreach ( $queue as $row ) {
			$existing[ strtolower( $row['topic'] ) ] = true;
		}

		$added = 0;
		foreach ( $seeds as $seed ) {
			$result = $research->discover( $seed, $cfg['country'], $competitors );
			if ( is_wp_error( $result ) || empty( $result['merged_seed_pool'] ) ) {
				continue;
			}
			foreach ( $result['merged_seed_pool'] as $kw ) {
				$topic = $kw['keyword'];
				if ( '' === $topic || isset( $existing[ strtolower( $topic ) ] ) ) {
					continue;
				}
				$queue[] = array(
					'topic'      => $topic,
					'seed'       => $seed,
					'score'      => isset( $kw['score'] ) ? (int) $kw['score'] : 0,
					'country'    => $cfg['country'],
					'profile_id' => (int) $cfg['profile_id'],
					'queued_at'  => current_time( 'mysql' ),
				);
				$existing[ strtolower( $topic ) ] = true;
				$added++;
				if ( $added >= 100 ) {
					break 2;
				}
			}
		}

		$this->set_queue( $queue );
		return $added;
	}

	private function competitor_list() {
		$raw = (string) RankWriter_AI_Helpers::get_setting( 'competitor_domains', '' );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d ) {
				$out[] = $d;
			}
		}
		return $out;
	}

	/**
	 * Cron tick. Pulls up to `max_per_run` items off the queue and generates.
	 */
	public function tick() {
		$cfg = $this->get_config();
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}

		$queue = $this->get_queue();
		if ( empty( $queue ) ) {
			$this->refill_queue();
			$queue = $this->get_queue();
			if ( empty( $queue ) ) {
				$this->log( 'skip', 'Queue empty and could not be refilled.' );
				return;
			}
		}

		$max = max( 1, (int) $cfg['max_per_run'] );
		$generated = 0;

		while ( $generated < $max && ! empty( $queue ) ) {
			$item = array_shift( $queue );
			$this->set_queue( $queue );

			$gen = new RankWriter_AI_Content_Generator();
			$post_id = $gen->generate(
				array(
					'profile_id'              => $item['profile_id'],
					'topic'                   => $item['topic'],
					'word_count'              => $cfg['word_count'],
					'extra_context'           => '',
					'desired_status'          => $cfg['post_status'],
					'write_seo_meta'          => ! empty( $cfg['auto_seo'] ),
					'country_override'        => $item['country'],
					'autopilot'               => true,
					'max_tags'                => isset( $cfg['max_tags'] ) ? (int) $cfg['max_tags'] : 2,
					'override_wp_category_id' => isset( $cfg['wp_category_id'] ) ? (int) $cfg['wp_category_id'] : 0,
				)
			);

			if ( is_wp_error( $post_id ) ) {
				$this->log( 'error', $item['topic'] . ' — ' . $post_id->get_error_message() );
				continue;
			}

			$this->log( 'ok', sprintf( 'Generated post #%d: %s', $post_id, $item['topic'] ) );
			$generated++;
		}
	}

	public function get_log( $limit = 50 ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( $log, 0, $limit );
	}

	private function log( $level, $message ) {
		$log = $this->get_log( 200 );
		array_unshift( $log, array(
			'at'      => current_time( 'mysql' ),
			'level'   => $level,
			'message' => $message,
		) );
		update_option( self::LOG_OPTION, array_slice( $log, 0, 200 ), false );
	}
}
