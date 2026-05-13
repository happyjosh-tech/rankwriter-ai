<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database cleanup helpers. Every method is opt-in (the admin button
 * for each is wired separately) and every method touches ONLY rows we
 * can prove are safe to remove:
 *
 *   - Post revisions older than $keep
 *   - Auto-drafts left behind by Gutenberg
 *   - Posts in trash (per WordPress's own trash-empty semantics)
 *   - Spam + trashed comments
 *   - Expired transients (rows whose _transient_timeout_* < now)
 *   - Orphan post meta (postmeta rows whose post_id no longer exists)
 *
 * We do NOT touch: users, orders (WC), live posts, settings, options,
 * plugin tables, or anything user-facing.
 */
class RankWriter_AI_Database_Cleaner {

	const OPTION_STATUS = 'rwai_speed_db_status';

	public function clean_post_revisions( $keep_per_post = 3 ) {
		global $wpdb;
		$keep_per_post = max( 0, (int) $keep_per_post );
		// Find revision IDs grouped by parent, keep most recent N.
		$rows = $wpdb->get_results( "SELECT ID, post_parent, post_date FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent, post_date DESC" );
		$per_parent = array();
		$to_delete = array();
		foreach ( $rows as $r ) {
			$per_parent[ $r->post_parent ] = ( $per_parent[ $r->post_parent ] ?? 0 ) + 1;
			if ( $per_parent[ $r->post_parent ] > $keep_per_post ) {
				$to_delete[] = (int) $r->ID;
			}
		}
		$deleted = 0;
		foreach ( $to_delete as $id ) {
			if ( wp_delete_post_revision( $id ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	public function clean_auto_drafts() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	public function clean_trashed_posts() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	public function clean_spam_comments() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_comment( (int) $id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	public function clean_expired_transients() {
		global $wpdb;
		$now = time();
		// Pair _transient_timeout_X with its value _transient_X; delete both when expired.
		$expired = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_timeout\_%%' AND option_value < %d",
			$now
		) );
		$deleted = 0;
		foreach ( $expired as $timeout_key ) {
			$name = substr( $timeout_key, strlen( '_transient_timeout_' ) );
			delete_transient( $name );
			$deleted++;
		}
		// Also site transients.
		$site_expired = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE '\_site\_transient\_timeout\_%%' AND option_value < %d",
			$now
		) );
		foreach ( $site_expired as $timeout_key ) {
			$name = substr( $timeout_key, strlen( '_site_transient_timeout_' ) );
			delete_site_transient( $name );
			$deleted++;
		}
		return $deleted;
	}

	public function clean_orphan_meta() {
		global $wpdb;
		// Postmeta with no parent post.
		$deleted = $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
		return (int) $deleted;
	}

	/**
	 * Run everything in a single batch and return a digest the UI can show.
	 * The caller decides when to invoke — never on a cron, always on a
	 * manual admin button so the user is aware deletes happened.
	 */
	public function run_all( $keep_revisions_per_post = 3 ) {
		$result = array(
			'revisions_deleted'  => $this->clean_post_revisions( $keep_revisions_per_post ),
			'auto_drafts_deleted'=> $this->clean_auto_drafts(),
			'trashed_deleted'    => $this->clean_trashed_posts(),
			'spam_deleted'       => $this->clean_spam_comments(),
			'transients_deleted' => $this->clean_expired_transients(),
			'orphan_meta_deleted'=> $this->clean_orphan_meta(),
			'ran_at'             => current_time( 'mysql' ),
		);
		update_option( self::OPTION_STATUS, $result, false );
		RankWriter_AI_Speed_Logger::log( 'db_cleanup', $result, 'success' );
		return $result;
	}

	public function get_status() {
		return (array) get_option( self::OPTION_STATUS, array() );
	}

	/**
	 * Pre-flight count — how many rows the user would delete if they
	 * clicked Clean Database right now. Lets the UI show "5,432 rows
	 * to delete" before the user confirms.
	 */
	public function preflight_counts() {
		global $wpdb;
		return array(
			'revisions'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'auto_drafts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trashed'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ),
			'transients'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%%' AND option_value < %d", time() ) ),
			'orphan_meta' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" ),
		);
	}
}
