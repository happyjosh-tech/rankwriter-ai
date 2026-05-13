<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-update old articles. Lightweight background pipeline:
 *
 *   1. Cron tick (hourly, register_recurring)
 *   2. Pick the single stalest post that hasn't been touched recently
 *   3. Hand the post to Claude with strict preservation rules: keep slug,
 *      keep canonical URL, keep title intent, keep H1 — only refresh
 *      stale stats / dates / keywords / readability.
 *   4. Save the pre-update version as a WP revision (so the user can
 *      always restore).
 *   5. Re-run the internal linker on the new body. Re-write SEO meta if a
 *      supported SEO plugin is present.
 *   6. Re-score freshness via Fact Checker and log the result.
 *
 * Quota-gated: max N refreshes per 24h (default 5). Never two ticks of the
 * same post within `min_interval_days` (default 30).
 */
class RankWriter_AI_Content_Refresher {

	const OPTION_SETTINGS  = 'rwai_refresher_settings';
	const META_LAST_REFRESH = '_rwai_last_refresh_at';
	const META_REFRESH_COUNT = '_rwai_refresh_count';
	const META_ORIGINAL_PUBLISHED = '_rwai_original_published';
	const CRON_HOOK = 'rwai_refresher_tick';

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'tick' ) );
	}

	public function schedule_recurring() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function get_settings() {
		$defaults = array(
			'enabled'             => 0,
			'stale_threshold_days' => 180,
			'min_freshness_score' => 60,
			'min_interval_days'   => 30,
			'daily_quota'         => 5,
			'preserve_url'        => 1,
			'preserve_title'      => 1,
			'use_claude'          => 1,
		);
		$saved = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public function save_settings( array $values ) {
		$s = $this->get_settings();
		$merged = array(
			'enabled'              => ! empty( $values['enabled'] ) ? 1 : 0,
			'stale_threshold_days' => max( 30, (int) ( $values['stale_threshold_days'] ?? $s['stale_threshold_days'] ) ),
			'min_freshness_score'  => max( 0, min( 100, (int) ( $values['min_freshness_score'] ?? $s['min_freshness_score'] ) ) ),
			'min_interval_days'    => max( 7, (int) ( $values['min_interval_days'] ?? $s['min_interval_days'] ) ),
			'daily_quota'          => max( 1, min( 50, (int) ( $values['daily_quota'] ?? $s['daily_quota'] ) ) ),
			'preserve_url'         => ! empty( $values['preserve_url'] ) ? 1 : 0,
			'preserve_title'       => ! empty( $values['preserve_title'] ) ? 1 : 0,
			'use_claude'           => ! empty( $values['use_claude'] ) ? 1 : 0,
		);
		update_option( self::OPTION_SETTINGS, $merged );
		return $merged;
	}

	/* ============================ Cron tick ============================ */

	public function tick() {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( RankWriter_AI_Refresher_DB::count_in_window( 24 ) >= (int) $settings['daily_quota'] ) {
			return; // already hit daily budget
		}
		$candidate = $this->next_candidate();
		if ( ! $candidate ) {
			return;
		}
		$this->refresh_post( $candidate, array( 'source' => 'cron' ) );
	}

	/**
	 * Pick the stalest post that:
	 *   - hasn't been refreshed within `min_interval_days`
	 *   - has a freshness_score under threshold (or unknown)
	 *   - older than `stale_threshold_days` since post_modified
	 */
	public function next_candidate() {
		$settings   = $this->get_settings();
		$threshold  = (int) $settings['stale_threshold_days'];
		$min_score  = (int) $settings['min_freshness_score'];
		$min_gap    = (int) $settings['min_interval_days'];
		$cutoff_mod = gmdate( 'Y-m-d H:i:s', time() - ( $threshold * DAY_IN_SECONDS ) );
		$cutoff_ref = gmdate( 'Y-m-d H:i:s', time() - ( $min_gap   * DAY_IN_SECONDS ) );

		global $wpdb;
		// Two-pass: first ask for posts whose freshness has been scored and
		// is below the threshold. Fall back to "old + never scored" if
		// nothing pops.
		$posts_table = $wpdb->posts;
		$meta_table  = $wpdb->postmeta;

		$candidate = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$posts_table} p
			 INNER JOIN {$meta_table} mfs ON mfs.post_id = p.ID AND mfs.meta_key = %s
			 LEFT JOIN {$meta_table} mlr ON mlr.post_id = p.ID AND mlr.meta_key = %s
			 WHERE p.post_type = 'post' AND p.post_status = 'publish'
			   AND p.post_modified_gmt <= %s
			   AND CAST(mfs.meta_value AS UNSIGNED) < %d
			   AND ( mlr.meta_value IS NULL OR mlr.meta_value <= %s )
			 ORDER BY CAST(mfs.meta_value AS UNSIGNED) ASC, p.post_modified_gmt ASC
			 LIMIT 1",
			RankWriter_AI_Fact_Checker::META_FRESH_SCORE,
			self::META_LAST_REFRESH,
			$cutoff_mod,
			$min_score,
			$cutoff_ref
		) );

		if ( $candidate ) {
			return (int) $candidate;
		}

		// Fallback: oldest post never scored AND older than threshold.
		$candidate = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$posts_table} p
			 LEFT JOIN {$meta_table} mfs ON mfs.post_id = p.ID AND mfs.meta_key = %s
			 LEFT JOIN {$meta_table} mlr ON mlr.post_id = p.ID AND mlr.meta_key = %s
			 WHERE p.post_type = 'post' AND p.post_status = 'publish'
			   AND p.post_modified_gmt <= %s
			   AND mfs.meta_id IS NULL
			   AND ( mlr.meta_value IS NULL OR mlr.meta_value <= %s )
			 ORDER BY p.post_modified_gmt ASC
			 LIMIT 1",
			RankWriter_AI_Fact_Checker::META_FRESH_SCORE,
			self::META_LAST_REFRESH,
			$cutoff_mod,
			$cutoff_ref
		) );

		return $candidate ? (int) $candidate : 0;
	}

	/**
	 * Refresh a single post. Returns the log row on success or WP_Error.
	 */
	public function refresh_post( $post_id, array $opts = array() ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}

		$settings = $this->get_settings();
		$checker  = new RankWriter_AI_Fact_Checker();

		// Score "before" — uses cached report when fresh.
		$before_report = $checker->get_report( $post_id );
		if ( empty( $before_report ) ) {
			$before_report = $checker->check_post( $post_id, false );
		}
		$before_score = is_array( $before_report ) ? (int) ( $before_report['freshness_score'] ?? 0 ) : 0;

		// Always create a revision so the user can roll back if the refresh
		// makes things worse. This is the entire "revision history" promise.
		if ( function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( $post_id );
		}

		// Track the original first-publish date so we can show "Originally
		// published: …, last refreshed: …" downstream.
		if ( ! get_post_meta( $post_id, self::META_ORIGINAL_PUBLISHED, true ) ) {
			update_post_meta( $post_id, self::META_ORIGINAL_PUBLISHED, $post->post_date );
		}

		$new_content = $post->post_content;
		$new_title   = $post->post_title;
		$changes     = array();

		if ( ! empty( $settings['use_claude'] ) && class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			$claude_out = $this->claude_refresh( $post, $before_report, $settings );
			if ( ! is_wp_error( $claude_out ) && ! empty( $claude_out['content'] ) ) {
				$new_content = $claude_out['content'];
				if ( empty( $settings['preserve_title'] ) && ! empty( $claude_out['title'] ) ) {
					$new_title = $claude_out['title'];
				}
				$changes = $claude_out['changes'] ?? array();
			}
		}

		// Re-run internal linker. The class is language-aware, so we pass the
		// post's language so cross-language links don't leak in.
		if ( class_exists( 'RankWriter_AI_Internal_Linker' ) ) {
			$linker = new RankWriter_AI_Internal_Linker();
			if ( method_exists( $linker, 'set_target_language' ) && class_exists( 'RankWriter_AI_Language' ) ) {
				$linker->set_target_language( RankWriter_AI_Language::get_post_language( $post_id ) );
			}
			$cats = wp_get_post_categories( $post_id );
			$primary_cat = ! empty( $cats ) ? (int) $cats[0] : 0;
			$kw_seed = array( $post->post_title );
			$candidates = $linker->get_candidates( $primary_cat, $kw_seed, 12 );
			if ( ! empty( $candidates ) ) {
				$relinked = $linker->auto_link( $new_content, $candidates, 5 );
				if ( is_string( $relinked ) && '' !== $relinked ) {
					$new_content = $relinked;
				}
			}
		}

		// Update the post. preserve_url = keep post_name (slug) so the
		// canonical URL never changes. We bump post_modified so Google
		// sees the freshness signal but leave post_date alone.
		$updated = array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $new_content ),
		);
		if ( ! empty( $settings['preserve_title'] ) ) {
			// don't touch
		} else {
			$updated['post_title'] = wp_slash( $new_title );
		}
		if ( ! empty( $settings['preserve_url'] ) ) {
			$updated['post_name'] = $post->post_name; // explicit no-op
		}
		$result = wp_update_post( $updated, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Re-score freshness — this gives us the "after" number.
		$after_report = $checker->check_post( $post_id, ! empty( $settings['use_claude'] ) );
		$after_score  = is_array( $after_report ) ? (int) ( $after_report['freshness_score'] ?? 0 ) : 0;

		update_post_meta( $post_id, self::META_LAST_REFRESH, current_time( 'mysql' ) );
		$count = (int) get_post_meta( $post_id, self::META_REFRESH_COUNT, true );
		update_post_meta( $post_id, self::META_REFRESH_COUNT, $count + 1 );

		$summary = $this->build_summary( $changes, $before_score, $after_score );
		$log_id  = RankWriter_AI_Refresher_DB::insert( array(
			'post_id'          => $post_id,
			'refreshed_at'     => current_time( 'mysql' ),
			'status'           => 'success',
			'freshness_before' => $before_score,
			'freshness_after'  => $after_score,
			'summary'          => $summary,
			'changes_json'     => wp_json_encode( array(
				'changes' => $changes,
				'source'  => $opts['source'] ?? 'manual',
			) ),
		) );

		return array(
			'log_id'           => $log_id,
			'post_id'          => $post_id,
			'freshness_before' => $before_score,
			'freshness_after'  => $after_score,
			'summary'          => $summary,
			'changes'          => $changes,
		);
	}

	/* ============================ Claude refresh prompt ============================ */

	protected function claude_refresh( $post, $before_report, $settings ) {
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key not configured.', 'rankwriter-ai' ) );
		}

		$today      = current_time( 'Y-m-d' );
		$age_days   = (int) ( $before_report['post_age_days'] ?? 0 );
		$warnings   = array();
		foreach ( (array) ( $before_report['warnings'] ?? array() ) as $w ) {
			$warnings[] = ( $w['severity'] ?? 'info' ) . ' / ' . ( $w['type'] ?? '' ) . ': ' . ( $w['detail'] ?? $w['text'] ?? '' );
		}
		$warnings_block = empty( $warnings ) ? '(none)' : "- " . implode( "\n- ", array_slice( $warnings, 0, 20 ) );

		$preserve_title = ! empty( $settings['preserve_title'] );

		$system = "You are a content-refresh editor. The article below is " . $age_days . " days old. Today's date is " . $today . ". " .
			"Your job: refresh it WITHOUT changing its meaning, its target keyword, or its URL slug. " .
			"DO: update outdated statistics to the most recent year you are confident about, replace expired deadlines with the current cycle if obvious, refresh stale year anchors, tighten readability, add 1–2 fresh internal-linking sentences only if natural, improve subheadings, fix awkward AI-tells. " .
			"DO NOT: change the H1/title (it's preserved), change the URL slug, change the article's core argument, add unverified claims, fabricate dates/figures you are not confident about, remove citations. " .
			"If you are NOT confident a figure is current, leave it as-is. Quality over freshness. " .
			"Output ONLY valid JSON of shape: " .
			'{"title":"...optional new title (leave blank to keep)","content":"<full rewritten HTML body>","changes":[{"type":"date|stat|readability|seo|link|keyword","note":"short description of the change"}]}';

		if ( $preserve_title ) {
			$system .= " The title MUST be preserved exactly — leave the title field blank.";
		}

		$user = "TITLE: " . $post->post_title . "\n" .
			"FRESHNESS WARNINGS FROM HEURISTIC SCAN:\n" . $warnings_block . "\n\n" .
			"CURRENT BODY (HTML):\n" . $post->post_content;

		$response = $client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_refresh_json( $response );
		if ( ! is_array( $parsed ) || empty( $parsed['content'] ) ) {
			return new WP_Error( 'rwai_bad_refresh', __( 'Refresh response could not be parsed.', 'rankwriter-ai' ) );
		}

		return array(
			'title'   => isset( $parsed['title'] ) ? (string) $parsed['title'] : '',
			'content' => (string) $parsed['content'],
			'changes' => is_array( $parsed['changes'] ?? null ) ? $parsed['changes'] : array(),
		);
	}

	protected function parse_refresh_json( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return null; }
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) { return $decoded; }
		if ( preg_match( '/\{.*\}/s', $raw, $m ) ) {
			$decoded = json_decode( $m[0], true );
			if ( is_array( $decoded ) ) { return $decoded; }
			// Try unescaping leaked backslashes.
			$cleaned = stripslashes( $m[0] );
			$decoded = json_decode( $cleaned, true );
			if ( is_array( $decoded ) ) { return $decoded; }
		}
		return null;
	}

	protected function build_summary( array $changes, $before, $after ) {
		$delta = (int) $after - (int) $before;
		$parts = array();
		$parts[] = sprintf( __( 'Freshness %1$d → %2$d (%3$+d)', 'rankwriter-ai' ), (int) $before, (int) $after, $delta );
		if ( ! empty( $changes ) ) {
			$counts = array();
			foreach ( $changes as $c ) {
				$t = (string) ( $c['type'] ?? 'misc' );
				$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
			}
			$pieces = array();
			foreach ( $counts as $t => $n ) {
				$pieces[] = $n . ' ' . $t;
			}
			$parts[] = implode( ', ', $pieces );
		}
		return implode( ' · ', $parts );
	}

	/* ============================ Inventory ============================ */

	public function stale_inventory( $limit = 50 ) {
		$checker = new RankWriter_AI_Fact_Checker();
		return $checker->bulk_inventory( $limit );
	}
}
