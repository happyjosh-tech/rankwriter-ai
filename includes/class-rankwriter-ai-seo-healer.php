<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-Healing SEO Engine.
 *
 * Cron-driven scanner walks the post catalog in cursor-based batches (so
 * one tick costs the same regardless of site size), records open issues
 * in a custom table, and optionally auto-fixes the safe ones (alt text,
 * missing meta description, missing schema). Every repair stores a
 * before/after snapshot for rollback.
 *
 * Detection rules:
 *   - broken_internal_link        link → non-existent or trashed post
 *   - missing_meta_description    no SEO-plugin / RankWriter meta desc
 *   - missing_alt_text            <img> with no/empty alt=
 *   - orphan_post                 zero inbound internal links from peers
 *   - thin_content                word_count < threshold (default 300)
 *   - duplicate_title             post_title appears on >1 post
 *   - duplicate_meta_description  same meta description on >1 post
 *   - missing_schema              no JSON-LD payload + no SEO plugin
 *   - weak_headings               no H2 in a long post
 *   - outdated_seo_settings       title > 60 chars or meta-desc length out of range
 */
class RankWriter_AI_SEO_Healer {

	const OPTION_SETTINGS = 'rwai_seo_healer_settings';
	const OPTION_CURSOR   = 'rwai_seo_healer_cursor';
	const OPTION_DUP_CACHE= 'rwai_seo_healer_dup_cache';
	const CRON_HOOK_SCAN  = 'rwai_seo_healer_scan_tick';
	const CRON_HOOK_FIX   = 'rwai_seo_healer_fix_tick';

	const SEV_CRITICAL = 'critical';
	const SEV_ERROR    = 'error';
	const SEV_WARNING  = 'warning';
	const SEV_INFO     = 'info';

	const RULE_BROKEN_LINK    = 'broken_internal_link';
	const RULE_NO_META_DESC   = 'missing_meta_description';
	const RULE_NO_ALT         = 'missing_alt_text';
	const RULE_ORPHAN         = 'orphan_post';
	const RULE_THIN           = 'thin_content';
	const RULE_DUP_TITLE      = 'duplicate_title';
	const RULE_DUP_META_DESC  = 'duplicate_meta_description';
	const RULE_NO_SCHEMA      = 'missing_schema';
	const RULE_WEAK_HEADINGS  = 'weak_headings';
	const RULE_OUTDATED_SEO   = 'outdated_seo_settings';

	public function register_hooks() {
		add_action( self::CRON_HOOK_SCAN, array( $this, 'scan_tick' ) );
		add_action( self::CRON_HOOK_FIX,  array( $this, 'fix_tick' ) );
		// Re-scan a post whenever it's updated so any issue the user just
		// fixed (manually in the editor OR via our Replace/Delete buttons)
		// clears immediately, instead of waiting for the next cron tick.
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		// Trashing / deleting a post should wipe its issues so we don't
		// keep flagging URLs against a post that no longer exists.
		add_action( 'wp_trash_post',  array( $this, 'on_remove_post' ) );
		add_action( 'before_delete_post', array( $this, 'on_remove_post' ) );
	}

	/**
	 * `save_post` callback. Skips autosaves, revisions, and bulk-edit noise.
	 */
	public function on_save_post( $post_id, $post = null, $update = null ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post = $post ?: get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return;
		}
		// Defensive: make sure the next get_post() inside scan_post()
		// reads the freshly saved content rather than a cached copy.
		clean_post_cache( $post_id );
		$this->scan_post( (int) $post_id );
	}

	public function on_remove_post( $post_id ) {
		global $wpdb;
		$t = RankWriter_AI_SEO_Healer_DB::issues_table();
		$wpdb->delete( $t, array( 'post_id' => (int) $post_id ) );
	}

	public function schedule_recurring() {
		if ( ! wp_next_scheduled( self::CRON_HOOK_SCAN ) ) {
			wp_schedule_event( time() + ( 10 * MINUTE_IN_SECONDS ), 'hourly', self::CRON_HOOK_SCAN );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_FIX ) ) {
			wp_schedule_event( time() + ( 30 * MINUTE_IN_SECONDS ), 'hourly', self::CRON_HOOK_FIX );
		}
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_HOOK_SCAN );
		wp_clear_scheduled_hook( self::CRON_HOOK_FIX );
	}

	/* ============================ Settings ============================ */

	public function get_settings() {
		$defaults = array(
			'enabled'              => 1,
			'batch_size'           => 20,
			'thin_threshold_words' => 300,
			'auto_fix_alt'         => 1,
			'auto_fix_meta_desc'   => 1,
			'auto_fix_schema'      => 1,
			'use_claude_for_fixes' => 1,
			'daily_fix_quota'      => 30,
			'rules_enabled'        => array(
				self::RULE_BROKEN_LINK   => 1,
				self::RULE_NO_META_DESC  => 1,
				self::RULE_NO_ALT        => 1,
				self::RULE_ORPHAN        => 1,
				self::RULE_THIN          => 1,
				self::RULE_DUP_TITLE     => 1,
				self::RULE_DUP_META_DESC => 1,
				self::RULE_NO_SCHEMA     => 1,
				self::RULE_WEAK_HEADINGS => 1,
				self::RULE_OUTDATED_SEO  => 1,
			),
		);
		$saved = get_option( self::OPTION_SETTINGS, array() );
		$merged = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		$merged['rules_enabled'] = wp_parse_args( (array) ( $merged['rules_enabled'] ?? array() ), $defaults['rules_enabled'] );
		return $merged;
	}

	public function save_settings( array $values ) {
		$s = $this->get_settings();
		$merged = array(
			'enabled'              => ! empty( $values['enabled'] ) ? 1 : 0,
			'batch_size'           => max( 5, min( 200, (int) ( $values['batch_size'] ?? $s['batch_size'] ) ) ),
			'thin_threshold_words' => max( 100, min( 2000, (int) ( $values['thin_threshold_words'] ?? $s['thin_threshold_words'] ) ) ),
			'auto_fix_alt'         => ! empty( $values['auto_fix_alt'] ) ? 1 : 0,
			'auto_fix_meta_desc'   => ! empty( $values['auto_fix_meta_desc'] ) ? 1 : 0,
			'auto_fix_schema'      => ! empty( $values['auto_fix_schema'] ) ? 1 : 0,
			'use_claude_for_fixes' => ! empty( $values['use_claude_for_fixes'] ) ? 1 : 0,
			'daily_fix_quota'      => max( 1, min( 500, (int) ( $values['daily_fix_quota'] ?? $s['daily_fix_quota'] ) ) ),
			'rules_enabled'        => array(),
		);
		foreach ( array_keys( $s['rules_enabled'] ) as $rule ) {
			$merged['rules_enabled'][ $rule ] = ! empty( $values['rules_enabled'][ $rule ] ) ? 1 : 0;
		}
		update_option( self::OPTION_SETTINGS, $merged );
		return $merged;
	}

	/* ============================ Cron — scan tick ============================ */

	public function scan_tick() {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		$batch = $this->next_batch( (int) $settings['batch_size'] );
		if ( empty( $batch ) ) {
			return;
		}
		// Refresh site-wide duplicate caches once per scan tick.
		$this->refresh_duplicate_caches();
		foreach ( $batch as $post_id ) {
			$this->scan_post( (int) $post_id, $settings );
		}
	}

	/**
	 * Cursor-based pagination so each tick costs the same regardless of
	 * the total post count. The cursor stores the last-scanned post ID;
	 * when we reach the end of the catalog we wrap back to zero.
	 */
	protected function next_batch( $batch_size ) {
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'post' AND post_status = 'publish' AND ID > %d
			 ORDER BY ID ASC LIMIT %d",
			$cursor, max( 1, (int) $batch_size )
		) );
		if ( empty( $rows ) ) {
			update_option( self::OPTION_CURSOR, 0, false );
			return array();
		}
		update_option( self::OPTION_CURSOR, (int) end( $rows ), false );
		return array_map( 'intval', $rows );
	}

	/* ============================ Per-post scan ============================ */

	public function scan_post( $post_id, $settings = null ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$settings = $settings ?: $this->get_settings();
		$rules    = (array) $settings['rules_enabled'];

		if ( ! empty( $rules[ self::RULE_BROKEN_LINK ] ) )    { $this->check_broken_links( $post ); }
		if ( ! empty( $rules[ self::RULE_NO_META_DESC ] ) )   { $this->check_meta_description( $post ); }
		if ( ! empty( $rules[ self::RULE_NO_ALT ] ) )         { $this->check_alt_text( $post ); }
		if ( ! empty( $rules[ self::RULE_ORPHAN ] ) )         { $this->check_orphan( $post ); }
		if ( ! empty( $rules[ self::RULE_THIN ] ) )           { $this->check_thin_content( $post, (int) $settings['thin_threshold_words'] ); }
		if ( ! empty( $rules[ self::RULE_DUP_TITLE ] ) )      { $this->check_duplicate_title( $post ); }
		if ( ! empty( $rules[ self::RULE_DUP_META_DESC ] ) )  { $this->check_duplicate_meta_desc( $post ); }
		if ( ! empty( $rules[ self::RULE_NO_SCHEMA ] ) )      { $this->check_schema( $post ); }
		if ( ! empty( $rules[ self::RULE_WEAK_HEADINGS ] ) )  { $this->check_weak_headings( $post ); }
		if ( ! empty( $rules[ self::RULE_OUTDATED_SEO ] ) )   { $this->check_outdated_seo( $post ); }
	}

	/* ============================ Detectors ============================ */

	protected function check_broken_links( WP_Post $post ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		// Capture the full <a>…</a> block so we know the URL + the anchor
		// text + the original tag (the latter lets the replace/delete
		// handlers find the exact substring to mutate).
		if ( ! preg_match_all( '#<a\b([^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*)>(.*?)</a>#is', $post->post_content, $m, PREG_SET_ORDER ) ) {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_BROKEN_LINK );
			return;
		}
		$broken = array();
		$seen   = array();
		foreach ( $m as $match ) {
			$full_tag    = $match[0];
			$url         = $match[2];
			$anchor_html = $match[3];
			$anchor_text = trim( wp_strip_all_tags( $anchor_html ) );
			if ( '' === $url || '#' === $url[0] ) { continue; }
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && $host !== $home ) { continue; } // skip external — too expensive
			$target_id = url_to_postid( $url );
			$is_broken = false;
			if ( ! $target_id ) {
				$path = wp_parse_url( $url, PHP_URL_PATH );
				if ( $path && '/' !== $path ) {
					$is_broken = true;
				}
			} else {
				$target = get_post( $target_id );
				if ( ! $target || 'publish' !== $target->post_status ) {
					$is_broken = true;
				}
			}
			if ( ! $is_broken ) { continue; }
			// Dedup by URL — we only need one entry per broken target to
			// surface it; the fix handler operates on all instances.
			if ( isset( $seen[ $url ] ) ) {
				$broken[ $seen[ $url ] ]['occurrences']++;
				continue;
			}
			$seen[ $url ] = count( $broken );
			$broken[] = array(
				'url'         => $url,
				'anchor_text' => '' === $anchor_text ? '(no anchor text)' : mb_substr( $anchor_text, 0, 80 ),
				'occurrences' => 1,
			);
		}
		if ( ! empty( $broken ) ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_BROKEN_LINK,
				'severity'    => self::SEV_ERROR,
				'auto_fixable'=> 0, // not auto-fixed by cron, but user can repair inline
				'message'     => sprintf( _n( '%d broken internal link', '%d broken internal links', count( $broken ), 'rankwriter-ai' ), count( $broken ) ),
				'context_json'=> wp_json_encode( array( 'broken' => array_slice( $broken, 0, 20 ) ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_BROKEN_LINK );
		}
	}

	protected function check_meta_description( WP_Post $post ) {
		$desc = $this->read_meta_description( $post->ID );
		if ( '' === trim( (string) $desc ) ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_NO_META_DESC,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 1,
				'message'     => __( 'No meta description set for this post.', 'rankwriter-ai' ),
				'context_json'=> wp_json_encode( array() ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_NO_META_DESC );
		}
	}

	protected function check_alt_text( WP_Post $post ) {
		$missing = 0;
		$samples = array();
		if ( preg_match_all( '#<img\b[^>]*>#is', $post->post_content, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( ! preg_match( '/\balt\s*=\s*"([^"]*)"/i', $tag, $alt ) || '' === trim( $alt[1] ) ) {
					$missing++;
					if ( count( $samples ) < 5 && preg_match( '/\bsrc\s*=\s*"([^"]+)"/i', $tag, $src ) ) {
						$samples[] = $src[1];
					}
				}
			}
		}
		if ( $missing > 0 ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_NO_ALT,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 1,
				'message'     => sprintf( _n( '%d image missing alt text', '%d images missing alt text', $missing, 'rankwriter-ai' ), $missing ),
				'context_json'=> wp_json_encode( array( 'missing_count' => $missing, 'src_samples' => $samples ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_NO_ALT );
		}
	}

	protected function check_orphan( WP_Post $post ) {
		// Only flag posts older than 30 days — fresh posts haven't had a
		// chance to accumulate inbound links yet.
		if ( strtotime( $post->post_date_gmt ) > time() - ( 30 * DAY_IN_SECONDS ) ) {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_ORPHAN );
			return;
		}
		global $wpdb;
		$slug = $post->post_name;
		if ( '' === $slug ) {
			return;
		}
		// Cheap heuristic: search post_content for the post slug / permalink in <a href=...>
		$permalink = get_permalink( $post->ID );
		$like1 = '%' . $wpdb->esc_like( $permalink ) . '%';
		$like2 = '%' . $wpdb->esc_like( '/' . $slug ) . '%';
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'post' AND post_status = 'publish'
			   AND ID <> %d
			   AND ( post_content LIKE %s OR post_content LIKE %s )",
			$post->ID, $like1, $like2
		) );
		if ( 0 === $count ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_ORPHAN,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 0,
				'message'     => __( 'No inbound internal links — this is an orphan page.', 'rankwriter-ai' ),
				'context_json'=> wp_json_encode( array() ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_ORPHAN );
		}
	}

	protected function check_thin_content( WP_Post $post, $threshold ) {
		$wc = str_word_count( wp_strip_all_tags( $post->post_content ) );
		if ( $wc < $threshold ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_THIN,
				'severity'    => $wc < ( $threshold / 2 ) ? self::SEV_ERROR : self::SEV_WARNING,
				'auto_fixable'=> 0,
				'message'     => sprintf( __( 'Only %1$d words (target ≥ %2$d).', 'rankwriter-ai' ), $wc, $threshold ),
				'context_json'=> wp_json_encode( array( 'word_count' => $wc, 'threshold' => $threshold ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_THIN );
		}
	}

	protected function check_duplicate_title( WP_Post $post ) {
		$cache = (array) get_option( self::OPTION_DUP_CACHE, array() );
		$titles = $cache['titles'] ?? array();
		$key = strtolower( trim( $post->post_title ) );
		if ( '' === $key ) { return; }
		if ( ! empty( $titles[ $key ] ) && (int) $titles[ $key ] > 1 ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_DUP_TITLE,
				'severity'    => self::SEV_ERROR,
				'auto_fixable'=> 0,
				'message'     => sprintf( __( 'Title shared with %d other post(s) on the site.', 'rankwriter-ai' ), (int) $titles[ $key ] - 1 ),
				'context_json'=> wp_json_encode( array( 'occurrences' => (int) $titles[ $key ] ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_DUP_TITLE );
		}
	}

	protected function check_duplicate_meta_desc( WP_Post $post ) {
		$cache = (array) get_option( self::OPTION_DUP_CACHE, array() );
		$descs = $cache['descs'] ?? array();
		$desc  = $this->read_meta_description( $post->ID );
		if ( '' === trim( (string) $desc ) ) {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_DUP_META_DESC );
			return;
		}
		$key = md5( strtolower( trim( $desc ) ) );
		if ( ! empty( $descs[ $key ] ) && (int) $descs[ $key ] > 1 ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_DUP_META_DESC,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 1,
				'message'     => sprintf( __( 'Meta description shared with %d other post(s).', 'rankwriter-ai' ), (int) $descs[ $key ] - 1 ),
				'context_json'=> wp_json_encode( array( 'occurrences' => (int) $descs[ $key ] ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_DUP_META_DESC );
		}
	}

	protected function check_schema( WP_Post $post ) {
		// If a major SEO plugin handles schema, treat as covered.
		if ( class_exists( 'RankWriter_AI_SEO_Integration' ) ) {
			$active = ( new RankWriter_AI_SEO_Integration() )->detect_plugin();
			if ( in_array( $active, array( 'rank-math', 'yoast', 'seopress' ), true ) ) {
				RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_NO_SCHEMA );
				return;
			}
		}
		$graph  = get_post_meta( $post->ID, '_rwai_schema_graph', true );
		$legacy = get_post_meta( $post->ID, '_rwai_schema_jsonld', true );
		if ( empty( $graph ) && empty( $legacy ) ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_NO_SCHEMA,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 1,
				'message'     => __( 'No JSON-LD schema payload generated for this post.', 'rankwriter-ai' ),
				'context_json'=> wp_json_encode( array() ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_NO_SCHEMA );
		}
	}

	protected function check_weak_headings( WP_Post $post ) {
		$h2 = preg_match_all( '#<h2\b#i', $post->post_content );
		$wc = str_word_count( wp_strip_all_tags( $post->post_content ) );
		// 800+ word posts should have at least 2 H2s.
		if ( $wc >= 800 && $h2 < 2 ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_WEAK_HEADINGS,
				'severity'    => self::SEV_WARNING,
				'auto_fixable'=> 0,
				'message'     => sprintf( __( 'Long post (%1$d words) with only %2$d H2 subheading(s) — adds skim risk.', 'rankwriter-ai' ), $wc, $h2 ),
				'context_json'=> wp_json_encode( array( 'word_count' => $wc, 'h2_count' => $h2 ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_WEAK_HEADINGS );
		}
	}

	protected function check_outdated_seo( WP_Post $post ) {
		$issues = array();
		// Title length
		if ( mb_strlen( $post->post_title ) > 70 ) {
			$issues[] = sprintf( __( 'Title is %d chars — will truncate in SERPs (target ≤ 60).', 'rankwriter-ai' ), mb_strlen( $post->post_title ) );
		}
		// Meta description length
		$desc = $this->read_meta_description( $post->ID );
		if ( '' !== trim( $desc ) ) {
			$dl = mb_strlen( $desc );
			if ( $dl < 110 ) {
				$issues[] = sprintf( __( 'Meta description is %d chars — short of the 110-160 sweet spot.', 'rankwriter-ai' ), $dl );
			} elseif ( $dl > 170 ) {
				$issues[] = sprintf( __( 'Meta description is %d chars — will truncate (target ≤ 160).', 'rankwriter-ai' ), $dl );
			}
		}
		if ( ! empty( $issues ) ) {
			RankWriter_AI_SEO_Healer_DB::upsert_issue( array(
				'post_id'     => $post->ID,
				'rule'        => self::RULE_OUTDATED_SEO,
				'severity'    => self::SEV_INFO,
				'auto_fixable'=> 0,
				'message'     => implode( ' · ', $issues ),
				'context_json'=> wp_json_encode( array( 'issues' => $issues ) ),
			) );
		} else {
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_OUTDATED_SEO );
		}
	}

	/* ============================ Duplicate caches ============================ */

	protected function refresh_duplicate_caches() {
		global $wpdb;
		$cache = array( 'titles' => array(), 'descs' => array(), 'generated_at' => time() );

		$title_rows = $wpdb->get_results(
			"SELECT LOWER(TRIM(post_title)) AS t, COUNT(*) AS c FROM {$wpdb->posts}
			 WHERE post_type = 'post' AND post_status = 'publish' AND post_title <> ''
			 GROUP BY LOWER(TRIM(post_title))
			 HAVING COUNT(*) > 1",
			ARRAY_A
		);
		foreach ( $title_rows as $r ) {
			$cache['titles'][ $r['t'] ] = (int) $r['c'];
		}

		// Meta descriptions live across multiple plugin keys. We sample the
		// common ones; user-customised keys are out of scope here.
		$keys = array( '_aioseo_description', '_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc', '_rwai_seo_meta_description' );
		foreach ( $keys as $key ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT MD5(LOWER(TRIM(meta_value))) AS h, COUNT(*) AS c FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value <> ''
				 GROUP BY MD5(LOWER(TRIM(meta_value)))
				 HAVING COUNT(*) > 1",
				$key
			), ARRAY_A );
			foreach ( $rows as $r ) {
				$cache['descs'][ $r['h'] ] = max( $cache['descs'][ $r['h'] ] ?? 0, (int) $r['c'] );
			}
		}

		update_option( self::OPTION_DUP_CACHE, $cache, false );
	}

	/* ============================ Cron — fix tick ============================ */

	public function fix_tick() {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( RankWriter_AI_SEO_Healer_DB::count_repairs_in_window( 24 ) >= (int) $settings['daily_fix_quota'] ) {
			return;
		}
		// One repair per tick — keeps cron fast. Prioritize alts (cheap)
		// before meta descriptions (Claude-call) before schema (cheap).
		$priority = array();
		if ( ! empty( $settings['auto_fix_schema'] ) )    { $priority[] = self::RULE_NO_SCHEMA; }
		if ( ! empty( $settings['auto_fix_alt'] ) )       { $priority[] = self::RULE_NO_ALT; }
		if ( ! empty( $settings['auto_fix_meta_desc'] ) ) { $priority[] = self::RULE_NO_META_DESC; }

		foreach ( $priority as $rule ) {
			$issues = RankWriter_AI_SEO_Healer_DB::open_issues( 1, array( 'rule' => $rule ) );
			if ( empty( $issues ) ) { continue; }
			$issue = $issues[0];
			$this->auto_fix_issue( (int) $issue['id'], 'auto', $settings );
			return; // exactly one repair per tick
		}
	}

	/**
	 * Apply the safe auto-fix for an issue. Returns array on success,
	 * WP_Error otherwise.
	 */
	public function auto_fix_issue( $issue_id, $source = 'manual', $settings = null ) {
		$issue = RankWriter_AI_SEO_Healer_DB::get_issue( $issue_id );
		if ( ! $issue ) {
			return new WP_Error( 'rwai_no_issue', __( 'Issue not found.', 'rankwriter-ai' ) );
		}
		if ( empty( $issue['auto_fixable'] ) ) {
			return new WP_Error( 'rwai_not_fixable', __( 'This issue is flagged as not auto-fixable. Resolve manually.', 'rankwriter-ai' ) );
		}
		$post = get_post( (int) $issue['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}
		$settings = $settings ?: $this->get_settings();

		switch ( $issue['rule'] ) {
			case self::RULE_NO_ALT:
				$result = $this->fix_missing_alts( $post, $settings );
				break;
			case self::RULE_NO_META_DESC:
				$result = $this->fix_missing_meta_desc( $post, $settings );
				break;
			case self::RULE_NO_SCHEMA:
				$result = $this->fix_missing_schema( $post );
				break;
			case self::RULE_DUP_META_DESC:
				$result = $this->fix_missing_meta_desc( $post, $settings, true );
				break;
			default:
				return new WP_Error( 'rwai_unsupported', __( 'No auto-fix available for this rule.', 'rankwriter-ai' ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		RankWriter_AI_SEO_Healer_DB::log_repair( array(
			'post_id'      => $post->ID,
			'rule'         => $issue['rule'],
			'source'       => $source,
			'before_value' => $result['before'] ?? '',
			'after_value'  => $result['after']  ?? '',
			'notes'        => $result['notes']  ?? '',
		) );

		RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, $issue['rule'] );
		// Re-scan the affected post so derived issues update instantly.
		$this->scan_post( $post->ID, $settings );
		return $result;
	}

	/* ============================ Auto-fixers ============================ */

	protected function fix_missing_alts( WP_Post $post, $settings ) {
		$content = $post->post_content;
		$before  = $content;
		$updated = $content;
		$fixed   = 0;

		$updated = preg_replace_callback( '#<img\b([^>]*)>#is', function ( $m ) use ( $post, $settings, &$fixed ) {
			$attrs = $m[1];
			if ( preg_match( '/\balt\s*=\s*"([^"]*)"/i', $attrs, $alt ) && '' !== trim( $alt[1] ) ) {
				return $m[0];
			}
			$src_match = preg_match( '/\bsrc\s*=\s*"([^"]+)"/i', $attrs, $src );
			$src_url = $src_match ? $src[1] : '';
			$alt_text = $this->generate_alt_text( $src_url, $post, $settings );
			if ( '' === $alt_text ) {
				return $m[0];
			}
			$alt_attr = ' alt="' . esc_attr( $alt_text ) . '"';
			$fixed++;
			// Replace if a (presumably empty) alt= exists, otherwise insert.
			if ( preg_match( '/\balt\s*=\s*"([^"]*)"/i', $attrs ) ) {
				$attrs = preg_replace( '/\balt\s*=\s*"[^"]*"/i', trim( $alt_attr ), $attrs );
			} else {
				$attrs .= $alt_attr;
			}
			return '<img' . $attrs . '>';
		}, $updated );

		if ( $fixed === 0 ) {
			return new WP_Error( 'rwai_no_changes', __( 'No alt-missing images found.', 'rankwriter-ai' ) );
		}

		wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $updated ) ) );
		return array(
			'before' => $before,
			'after'  => $updated,
			'notes'  => sprintf( _n( 'Added alt text to %d image.', 'Added alt text to %d images.', $fixed, 'rankwriter-ai' ), $fixed ),
		);
	}

	protected function generate_alt_text( $src_url, WP_Post $post, $settings ) {
		// Lightweight default: derive from filename + post title.
		$filename = $src_url ? basename( wp_parse_url( $src_url, PHP_URL_PATH ) ) : '';
		$filename = preg_replace( '/\.[a-z0-9]+$/i', '', $filename );
		$filename = trim( preg_replace( '/[-_]+/', ' ', $filename ) );

		// Skip Claude on cheap path. If the user opted in AND filename is
		// useless, ask Claude for a tight description.
		if ( empty( $settings['use_claude_for_fixes'] ) || ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			if ( $filename && strlen( $filename ) > 3 ) {
				return wp_trim_words( $filename . ' — ' . $post->post_title, 12, '' );
			}
			return wp_trim_words( $post->post_title, 12, '' );
		}

		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return $filename ? $filename . ' — ' . $post->post_title : $post->post_title;
		}
		$system = "Generate a single-line alt-text (max 12 words) for an image in a blog post. Return ONLY the alt text, no quotes, no JSON, no preamble. Be descriptive and concrete, not promotional. If you can't tell what the image is, describe what would visually accompany the article.";
		$user   = "Article title: " . $post->post_title . "\n\nImage filename: " . $filename . "\nImage URL: " . $src_url;
		$raw    = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $raw ) ) {
			return $filename ? $filename . ' — ' . $post->post_title : $post->post_title;
		}
		$text = trim( wp_strip_all_tags( (string) $raw ) );
		$text = preg_replace( '/^["\']+|["\']+$/', '', $text );
		return wp_trim_words( $text, 16, '' );
	}

	protected function fix_missing_meta_desc( WP_Post $post, $settings, $force_unique = false ) {
		$before = $this->read_meta_description( $post->ID );

		if ( empty( $settings['use_claude_for_fixes'] ) || ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			$candidate = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 28, '…' );
		} else {
			$client = new RankWriter_AI_Claude_Client();
			if ( ! $client->is_configured() ) {
				$candidate = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 28, '…' );
			} else {
				$system = "Write a single SEO meta description for the article below. STRICT: 130-160 characters, no surrounding quotes, no preamble, plain text only. Include the focus topic, the reader payoff, and end with a soft hook. Match the tone of the article body.";
				$body_snip = wp_trim_words( wp_strip_all_tags( $post->post_content ), 350, '' );
				$user   = "Title: " . $post->post_title . "\n\nBody (excerpt):\n" . $body_snip;
				$raw    = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
				$candidate = is_wp_error( $raw ) ? wp_trim_words( wp_strip_all_tags( $post->post_content ), 28, '…' ) : trim( wp_strip_all_tags( (string) $raw ) );
				$candidate = preg_replace( '/^["\']+|["\']+$/', '', $candidate );
			}
		}
		$candidate = mb_substr( $candidate, 0, 170 );

		$this->write_meta_description( $post->ID, $candidate );

		return array(
			'before' => $before,
			'after'  => $candidate,
			'notes'  => $force_unique
				? __( 'Generated a unique meta description to break the duplicate.', 'rankwriter-ai' )
				: __( 'Generated a meta description from post content.', 'rankwriter-ai' ),
		);
	}

	protected function fix_missing_schema( WP_Post $post ) {
		if ( ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			return new WP_Error( 'rwai_no_schema_engine', __( 'Schema Engine unavailable.', 'rankwriter-ai' ) );
		}
		$before = get_post_meta( $post->ID, '_rwai_schema_graph', true );
		( new RankWriter_AI_Schema_Engine() )->build_and_save( $post->ID );
		$after = get_post_meta( $post->ID, '_rwai_schema_graph', true );
		return array(
			'before' => is_array( $before ) ? wp_json_encode( $before ) : (string) $before,
			'after'  => is_array( $after )  ? wp_json_encode( $after )  : (string) $after,
			'notes'  => __( 'Rebuilt the JSON-LD schema graph.', 'rankwriter-ai' ),
		);
	}

	/* ============================ Per-link repair (broken links) ============================ */

	/**
	 * Rewrite every <a href="$old_url"> in the post to use $new_url. Returns
	 * a result array suitable for logging, or WP_Error.
	 */
	public function replace_link_in_post( $post_id, $old_url, $new_url ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}
		$old_url = (string) $old_url;
		$new_url = esc_url_raw( $new_url );
		if ( '' === $old_url || '' === $new_url ) {
			return new WP_Error( 'rwai_bad_url', __( 'Both the old and new URL are required.', 'rankwriter-ai' ) );
		}
		$before  = $post->post_content;
		$updated = preg_replace_callback(
			'#<a\b([^>]*\bhref\s*=\s*)(["\'])([^"\']+)(["\'])([^>]*)>#i',
			function ( $m ) use ( $old_url, $new_url ) {
				if ( html_entity_decode( $m[3], ENT_QUOTES ) === html_entity_decode( $old_url, ENT_QUOTES ) ) {
					return '<a' . $m[1] . $m[2] . esc_url( $new_url ) . $m[4] . $m[5] . '>';
				}
				return $m[0];
			},
			$before
		);
		if ( $updated === $before ) {
			return new WP_Error( 'rwai_no_match', __( 'No matching link was found in the post.', 'rankwriter-ai' ) );
		}
		wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $updated ) ) );
		clean_post_cache( $post->ID );

		RankWriter_AI_SEO_Healer_DB::log_repair( array(
			'post_id'      => $post->ID,
			'rule'         => self::RULE_BROKEN_LINK,
			'source'       => 'manual',
			'before_value' => $before,
			'after_value'  => $updated,
			'notes'        => sprintf( __( 'Replaced %1$s → %2$s', 'rankwriter-ai' ), $old_url, $new_url ),
		) );
		// Re-scan so the count updates immediately. (save_post will also
		// have fired, but call it explicitly to guarantee the issue list
		// reflects reality before the redirect.)
		$this->scan_post( $post->ID );
		return array( 'before' => $before, 'after' => $updated );
	}

	/**
	 * Strip every <a href="$old_url"> in the post and keep the anchor text
	 * (so reader-facing copy stays intact, the link just disappears).
	 */
	public function delete_link_in_post( $post_id, $old_url ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}
		$old_url = (string) $old_url;
		if ( '' === $old_url ) {
			return new WP_Error( 'rwai_bad_url', __( 'Old URL is required.', 'rankwriter-ai' ) );
		}
		$before  = $post->post_content;
		$updated = preg_replace_callback(
			'#<a\b([^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*)>(.*?)</a>#is',
			function ( $m ) use ( $old_url ) {
				if ( html_entity_decode( $m[2], ENT_QUOTES ) === html_entity_decode( $old_url, ENT_QUOTES ) ) {
					return $m[3]; // keep the anchor inner text/markup, drop the wrapping <a>
				}
				return $m[0];
			},
			$before
		);
		if ( $updated === $before ) {
			// The visible link is already gone (probably edited manually
			// in the post editor between the last scan and now). Clear the
			// stale issue so the user doesn't keep seeing the notification.
			RankWriter_AI_SEO_Healer_DB::clear_issue( $post->ID, self::RULE_BROKEN_LINK );
			$this->scan_post( $post->ID );
			return new WP_Error( 'rwai_already_clean', __( 'That link is no longer in the post — the issue has been cleared.', 'rankwriter-ai' ) );
		}
		wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $updated ) ) );
		clean_post_cache( $post->ID );

		RankWriter_AI_SEO_Healer_DB::log_repair( array(
			'post_id'      => $post->ID,
			'rule'         => self::RULE_BROKEN_LINK,
			'source'       => 'manual',
			'before_value' => $before,
			'after_value'  => $updated,
			'notes'        => sprintf( __( 'Removed broken link %s (anchor text kept).', 'rankwriter-ai' ), $old_url ),
		) );
		$this->scan_post( $post->ID );
		return array( 'before' => $before, 'after' => $updated );
	}

	/**
	 * User-driven safety valve: clear a specific open issue without
	 * touching the post. Used when the detector got something wrong, or
	 * when the user has fixed the content via another route.
	 */
	public function dismiss_issue( $issue_id ) {
		$issue = RankWriter_AI_SEO_Healer_DB::get_issue( $issue_id );
		if ( ! $issue ) {
			return new WP_Error( 'rwai_no_issue', __( 'Issue not found (it may have already been resolved).', 'rankwriter-ai' ) );
		}
		RankWriter_AI_SEO_Healer_DB::clear_issue( (int) $issue['post_id'], (string) $issue['rule'] );
		return true;
	}

	/* ============================ Rollback ============================ */

	public function rollback_repair( $log_id ) {
		$row = RankWriter_AI_SEO_Healer_DB::get_repair( $log_id );
		if ( ! $row ) {
			return new WP_Error( 'rwai_no_repair', __( 'Repair entry not found.', 'rankwriter-ai' ) );
		}
		if ( ! empty( $row['rolled_back_at'] ) ) {
			return new WP_Error( 'rwai_already_rolled', __( 'This repair has already been rolled back.', 'rankwriter-ai' ) );
		}
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post no longer exists.', 'rankwriter-ai' ) );
		}

		switch ( $row['rule'] ) {
			case self::RULE_NO_ALT:
			case self::RULE_BROKEN_LINK:
				// Restore the entire post_content snapshot.
				wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( (string) $row['before_value'] ) ) );
				break;
			case self::RULE_NO_META_DESC:
			case self::RULE_DUP_META_DESC:
				$this->write_meta_description( $post->ID, (string) $row['before_value'] );
				break;
			case self::RULE_NO_SCHEMA:
				$decoded = json_decode( (string) $row['before_value'], true );
				if ( is_array( $decoded ) && ! empty( $decoded ) ) {
					update_post_meta( $post->ID, '_rwai_schema_graph', $decoded );
				} else {
					delete_post_meta( $post->ID, '_rwai_schema_graph' );
				}
				break;
			default:
				return new WP_Error( 'rwai_no_rollback', __( 'Rollback not supported for this rule.', 'rankwriter-ai' ) );
		}

		RankWriter_AI_SEO_Healer_DB::mark_rolled_back( (int) $log_id );
		// Re-scan so the issue reappears if appropriate.
		$this->scan_post( $post->ID );
		return true;
	}

	/* ============================ Meta-description helpers ============================ */

	/**
	 * Read meta description from whichever SEO plugin is active, with a
	 * fallback to our own key. Order is consistent with how SEO_Integration
	 * detects.
	 */
	public function read_meta_description( $post_id ) {
		$keys = array( 'rank_math_description', '_yoast_wpseo_metadesc', '_aioseo_description', '_seopress_titles_desc', '_rwai_seo_meta_description' );
		foreach ( $keys as $k ) {
			$v = get_post_meta( $post_id, $k, true );
			if ( '' !== trim( (string) $v ) ) {
				return (string) $v;
			}
		}
		return '';
	}

	/**
	 * Write meta description to the active SEO plugin's key plus our own,
	 * so the fix sticks regardless of which renderer reads it.
	 */
	public function write_meta_description( $post_id, $value ) {
		$value = (string) $value;
		update_post_meta( $post_id, '_rwai_seo_meta_description', $value );
		$active = class_exists( 'RankWriter_AI_SEO_Integration' )
			? ( new RankWriter_AI_SEO_Integration() )->detect_plugin()
			: '';
		switch ( $active ) {
			case 'rank-math':
				update_post_meta( $post_id, 'rank_math_description', $value );
				break;
			case 'yoast':
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $value );
				break;
			case 'aioseo':
				update_post_meta( $post_id, '_aioseo_description', $value );
				break;
			case 'seopress':
				update_post_meta( $post_id, '_seopress_titles_desc', $value );
				break;
		}
	}

	/* ============================ Health score ============================ */

	public function health_score() {
		$totals  = RankWriter_AI_SEO_Healer_DB::severity_totals();
		$weights = array( self::SEV_CRITICAL => 8, self::SEV_ERROR => 4, self::SEV_WARNING => 1.5, self::SEV_INFO => 0.5 );
		$penalty = 0;
		foreach ( $totals as $sev => $count ) {
			$penalty += (int) $count * (float) ( $weights[ $sev ] ?? 1 );
		}
		// Normalize against total published posts: a site with 1000 posts
		// can absorb more open issues before the score tanks than a
		// 10-post site can.
		$total_posts = max( 10, (int) wp_count_posts( 'post' )->publish );
		$normalised = min( 100, ( $penalty / $total_posts ) * 100 );
		return max( 0, (int) round( 100 - $normalised ) );
	}
}
