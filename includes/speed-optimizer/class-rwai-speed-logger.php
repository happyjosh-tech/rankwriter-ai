<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append-only log of every speed-optimizer action. Stored in a bounded
 * site option (last 100 entries) so we never grow unbounded and never
 * need a DB table. Read by the admin UI to render the activity feed.
 */
class RankWriter_AI_Speed_Logger {

	const OPTION = 'rwai_speed_log';
	const MAX    = 100;

	public static function log( $action, $detail = '', $level = 'info' ) {
		$entries = (array) get_option( self::OPTION, array() );
		$entries[] = array(
			'time'   => current_time( 'mysql' ),
			'action' => (string) $action,
			'detail' => is_scalar( $detail ) ? (string) $detail : wp_json_encode( $detail ),
			'level'  => in_array( $level, array( 'info', 'warning', 'error', 'success' ), true ) ? $level : 'info',
		);
		// Trim from the front so the most-recent N entries stay.
		if ( count( $entries ) > self::MAX ) {
			$entries = array_slice( $entries, -self::MAX );
		}
		update_option( self::OPTION, $entries, false );
	}

	public static function recent( $limit = 50 ) {
		$entries = (array) get_option( self::OPTION, array() );
		$entries = array_reverse( $entries );
		return array_slice( $entries, 0, max( 1, (int) $limit ) );
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
