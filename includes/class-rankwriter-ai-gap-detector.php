<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Gap Detector — composite audit engine that finds where the site
 * is topically thin compared to competitors, the keyword universe, and its
 * own clusters.
 *
 *   run_audit( $force = false )      → full audit, persisted to options
 *   get_last_audit()                 → cached previous audit
 *   schedule_recurring()             → weekly cron
 *   tick()                           → cron handler
 *
 * Composition (each method does one job — all results bundled by run_audit):
 *   competitor_keyword_gaps()        Compare competitor RSS titles +
 *                                    Google-Suggest keywords vs existing
 *                                    blog title corpus. Surfaces topics
 *                                    competitors cover that we don't.
 *   category_coverage_gaps()         WP categories whose post counts are
 *                                    under their cluster's target.
 *   cluster_gaps()                   Per-cluster topical gaps from the
 *                                    existing Cluster Analyzer.
 *   internal_link_weak_spots()       Orphan posts + posts with < 2
 *                                    outbound internal links.
 *   underperforming_topics()         Bottom-quartile posts by engagement
 *                                    (top_performing_posts data, inverted).
 *   rank_opportunities()             Composite score per gap (CPC tier +
 *                                    intent + priority niche + competition
 *                                    + long-tail bonus + recency bonus).
 *
 * Cached at the option level. Runs are idempotent — same input = same
 * output, so a forced re-run is cheap. No new DB tables.
 *
 * Anti-copying guarantee: this engine surfaces TOPIC SIGNALS only —
 * keywords, titles, category names. It never fetches or stores the body
 * content of competitor pages. When the user clicks "Generate article"
 * on a gap, the existing Content Generator pipeline (with its anti-AI
 * voice rules, freshness rules, and originality guards) handles the
 * actual writing — competitor URLs never enter the article.
 */
class RankWriter_AI_Gap_Detector {

	const OPTION_LAST_AUDIT = 'rwai_gap_last_audit';
	const CRON_HOOK         = 'rwai_gap_audit_run';

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'tick' ) );
	}

	public function schedule_recurring() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function tick() {
		$this->run_audit( true );
	}

	public function get_last_audit() {
		$saved = get_option( self::OPTION_LAST_AUDIT, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/* ============================ Master run ============================ */

	/**
	 * Run all sub-audits, rank opportunities, persist.
	 *
	 * @return array Full audit payload.
	 */
	public function run_audit( $force = false ) {
		if ( ! $force ) {
			$cached = $this->get_last_audit();
			if ( ! empty( $cached['generated_at'] ) && ( time() - strtotime( $cached['generated_at'] ) ) < DAY_IN_SECONDS ) {
				return $cached;
			}
		}

		$competitor = $this->competitor_keyword_gaps();
		$category   = $this->category_coverage_gaps();
		$cluster    = $this->cluster_gaps();
		$linking    = $this->internal_link_weak_spots();
		$underperf  = $this->underperforming_topics();

		$ranked = $this->rank_opportunities( $competitor, $category, $cluster );

		$summary = array(
			'competitor_gap_count' => count( $competitor ),
			'category_gap_count'   => count( $category ),
			'cluster_gap_count'    => count( $cluster ),
			'orphan_count'         => count( $linking['orphan_posts'] ?? array() ),
			'sparse_count'         => count( $linking['sparse_posts'] ?? array() ),
			'underperf_count'      => count( $underperf ),
			'top_opportunity'      => ! empty( $ranked ) ? (int) $ranked[0]['opportunity_score'] : 0,
			'priority_count'       => count( array_filter( $ranked, function ( $r ) { return ! empty( $r['priority_niche'] ); } ) ),
		);

		$payload = array(
			'generated_at'           => current_time( 'mysql' ),
			'summary'                => $summary,
			'top_opportunities'      => array_slice( $ranked, 0, 25 ),
			'competitor_keyword_gaps'=> $competitor,
			'category_coverage_gaps' => $category,
			'cluster_gaps'           => $cluster,
			'internal_link_gaps'     => $linking,
			'underperforming'        => $underperf,
		);

		update_option( self::OPTION_LAST_AUDIT, $payload, false );
		return $payload;
	}

	/* ============================ Competitor keyword gaps ============================ */

	/**
	 * Pull recent competitor RSS titles + Google-Suggest expansions of
	 * the user's seed keywords, then surface any whose token signature
	 * is missing from our own title corpus.
	 */
	public function competitor_keyword_gaps() {
		$gaps = array();

		$our_tokens = $this->our_title_tokens();
		if ( empty( $our_tokens ) ) {
			return $gaps;
		}

		$signals = $this->gather_competitor_signals();
		if ( empty( $signals ) ) {
			return $gaps;
		}

		$detector = class_exists( 'RankWriter_AI_Intent_Detector' ) ? new RankWriter_AI_Intent_Detector() : null;
		$scorer   = class_exists( 'RankWriter_AI_CPC_Scorer' ) ? new RankWriter_AI_CPC_Scorer() : null;
		$country  = (string) RankWriter_AI_Helpers::get_setting( 'default_country', 'US' );

		$seen = array();
		foreach ( $signals as $sig ) {
			$kw  = trim( (string) $sig['keyword'] );
			$key = strtolower( $kw );
			if ( '' === $kw || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$tokens = $this->tokenize( $kw );
			if ( empty( $tokens ) ) {
				continue;
			}

			// Coverage test: at least 60% of the tokens must be missing
			// from our existing title corpus for this to count as a gap.
			$missing = 0;
			foreach ( $tokens as $t ) {
				if ( empty( $our_tokens[ $t ] ) ) {
					$missing++;
				}
			}
			$coverage_miss = count( $tokens ) ? ( $missing / count( $tokens ) ) : 0;
			if ( $coverage_miss < 0.6 ) {
				continue;
			}

			$intent = $detector ? $detector->detect( $kw ) : array( 'primary' => 'informational', 'confidence' => 0 );
			$cpc    = $scorer ? $scorer->score( $kw, $country, array( 'intent' => $intent['primary'] ) ) : array(
				'tier' => 'medium', 'estimated_cpc_usd' => 0, 'rpm_prediction_usd' => 0, 'monetization_score' => 50, 'priority_niche' => false, 'competition_level' => 'medium', 'niche' => 'general',
			);

			$gap = array(
				'keyword'          => $kw,
				'source'           => $sig['source'],
				'first_seen'       => $sig['first_seen'] ?? '',
				'token_count'      => count( $tokens ),
				'coverage_miss'    => round( $coverage_miss, 2 ),
				'intent'           => $intent['primary'],
				'intent_label'     => isset( $intent['label'] ) ? $intent['label'] : ucfirst( $intent['primary'] ),
				'cpc_tier'         => $cpc['tier'],
				'estimated_cpc'    => $cpc['estimated_cpc_usd'],
				'rpm'              => $cpc['rpm_prediction_usd'],
				'monetization'     => $cpc['monetization_score'],
				'competition'      => $cpc['competition_level'],
				'priority_niche'   => ! empty( $cpc['priority_niche'] ),
				'niche'            => $cpc['niche'],
				'category'         => 'competitor_keyword',
			);
			$gaps[] = $gap;
		}
		return $gaps;
	}

	/**
	 * Build the tokenized index of every existing post title on the site.
	 * Cached per request — this is what gap detection compares against.
	 */
	private function our_title_tokens() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		global $wpdb;
		$titles = $wpdb->get_col(
			"SELECT post_title FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' LIMIT 500"
		);
		$index = array();
		foreach ( (array) $titles as $t ) {
			foreach ( $this->tokenize( $t ) as $tok ) {
				$index[ $tok ] = true;
			}
		}
		$cache = $index;
		return $cache;
	}

	/**
	 * Collect candidate competitor / external signals to compare against.
	 * Pulls from the Keyword Research persisted pool, the Blog Style
	 * Profile's competitor_titles snapshot, and any recent Keyword
	 * Research history rows.
	 */
	private function gather_competitor_signals() {
		$out = array();

		// 1. Cached keyword-research pool.
		$pool = get_option( 'rwai_keyword_pool', array() );
		if ( is_array( $pool ) ) {
			foreach ( array_slice( $pool, 0, 8 ) as $entry ) {
				$seed = isset( $entry['seed'] ) ? $entry['seed'] : '';
				if ( ! empty( $entry['titles'] ) ) {
					foreach ( $entry['titles'] as $row ) {
						if ( ! empty( $row['title'] ) ) {
							$out[] = array(
								'keyword'    => $row['title'],
								'source'     => isset( $row['source'] ) ? $row['source'] : 'competitor_rss',
								'first_seen' => isset( $row['date'] ) ? $row['date'] : ( isset( $entry['fetched_at'] ) ? $entry['fetched_at'] : '' ),
							);
						}
					}
				}
				if ( ! empty( $entry['keywords'] ) ) {
					foreach ( $entry['keywords'] as $kw ) {
						if ( ! empty( $kw['keyword'] ) ) {
							$out[] = array(
								'keyword'    => $kw['keyword'],
								'source'     => 'suggest:' . $seed,
								'first_seen' => isset( $entry['fetched_at'] ) ? $entry['fetched_at'] : '',
							);
						}
					}
				}
				if ( ! empty( $entry['trending'] ) ) {
					foreach ( $entry['trending'] as $tr ) {
						if ( ! empty( $tr['title'] ) ) {
							$out[] = array(
								'keyword'    => $tr['title'],
								'source'     => 'trending',
								'first_seen' => isset( $tr['date'] ) ? $tr['date'] : '',
							);
						}
					}
				}
			}
		}

		// 2. Fall back to Blog Style Profile's competitor + topic data.
		if ( empty( $out ) && class_exists( 'RankWriter_AI_Style_Profile' ) ) {
			$style = ( new RankWriter_AI_Style_Profile() )->get();
			if ( ! empty( $style['top_keywords'] ) ) {
				foreach ( array_slice( array_keys( $style['top_keywords'] ), 0, 20 ) as $kw ) {
					$out[] = array( 'keyword' => $kw, 'source' => 'blog_keyword_pool', 'first_seen' => '' );
				}
			}
		}

		return $out;
	}

	/* ============================ Category coverage gaps ============================ */

	public function category_coverage_gaps() {
		$gaps = array();
		$cats = get_terms( array(
			'taxonomy'   => 'category',
			'orderby'    => 'count',
			'order'      => 'ASC',
			'hide_empty' => false,
			'number'     => 100,
		) );
		if ( is_wp_error( $cats ) || empty( $cats ) ) {
			return $gaps;
		}

		// Cluster-aware target: if a cluster is mapped to this category's
		// name, use the cluster's target_supporting_count. Otherwise 8.
		$cluster_targets = array();
		if ( class_exists( 'RankWriter_AI_Cluster_Manager' ) ) {
			foreach ( ( new RankWriter_AI_Cluster_Manager() )->get_all() as $c ) {
				$cluster_targets[ strtolower( $c['name'] ) ] = (int) $c['target_supporting_count'];
			}
		}

		foreach ( $cats as $cat ) {
			if ( in_array( $cat->slug, array( 'uncategorized' ), true ) ) {
				continue;
			}
			$target = isset( $cluster_targets[ strtolower( $cat->name ) ] ) ? $cluster_targets[ strtolower( $cat->name ) ] : 8;
			$count  = (int) $cat->count;
			if ( $count >= $target ) {
				continue;
			}
			$gaps[] = array(
				'term_id'  => (int) $cat->term_id,
				'category' => $cat->name,
				'slug'     => $cat->slug,
				'count'    => $count,
				'target'   => $target,
				'shortfall'=> $target - $count,
				'category_type' => 'category_coverage',
			);
		}
		usort( $gaps, function ( $a, $b ) { return $b['shortfall'] - $a['shortfall']; } );
		return $gaps;
	}

	/* ============================ Cluster gaps ============================ */

	public function cluster_gaps() {
		$out = array();
		if ( ! class_exists( 'RankWriter_AI_Cluster_Manager' ) || ! class_exists( 'RankWriter_AI_Cluster_Analyzer' ) ) {
			return $out;
		}
		$manager  = new RankWriter_AI_Cluster_Manager();
		$analyzer = new RankWriter_AI_Cluster_Analyzer();
		foreach ( $manager->get_all( array( 'limit' => 100 ) ) as $cluster ) {
			$gaps = $analyzer->find_topical_gaps( $cluster['id'] );
			if ( is_wp_error( $gaps ) ) {
				continue;
			}
			if ( empty( $gaps['missing_intents'] ) && empty( $gaps['orphan_posts'] ) ) {
				continue;
			}
			$out[] = array(
				'cluster_id'      => (int) $cluster['id'],
				'cluster_name'    => $cluster['name'],
				'missing_intents' => isset( $gaps['missing_intents'] ) ? $gaps['missing_intents'] : array(),
				'orphan_posts'    => isset( $gaps['orphan_posts'] ) ? $gaps['orphan_posts'] : array(),
				'completion'      => (int) $manager->completion_score( $cluster ),
			);
		}
		return $out;
	}

	/* ============================ Internal linking weak spots ============================ */

	public function internal_link_weak_spots() {
		$out = array(
			'orphan_posts' => array(),
			'sparse_posts' => array(),
		);
		if ( class_exists( 'RankWriter_AI_Style_Profile' ) ) {
			$style = ( new RankWriter_AI_Style_Profile() )->get();
			if ( ! empty( $style['raw_signals']['orphan_posts'] ) ) {
				$out['orphan_posts'] = array_slice( $style['raw_signals']['orphan_posts'], 0, 15 );
			}
		}

		// Sparse posts: scan recent posts for outbound internal-link count.
		$recent = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 40,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $recent as $p ) {
			$ilink_count = 0;
			if ( preg_match_all( '/<a\b[^>]*href=("([^"]+)"|\'([^\']+)\')/i', $p->post_content, $m ) ) {
				foreach ( $m[2] as $i => $href ) {
					$href = '' !== $href ? $href : ( isset( $m[3][ $i ] ) ? $m[3][ $i ] : '' );
					if ( '' === $href ) {
						continue;
					}
					$h = wp_parse_url( $href, PHP_URL_HOST );
					if ( ! $h || $h === $home_host ) {
						$ilink_count++;
					}
				}
			}
			if ( $ilink_count < 2 ) {
				$out['sparse_posts'][] = array(
					'id'             => (int) $p->ID,
					'title'          => $p->post_title,
					'internal_links' => $ilink_count,
				);
			}
			if ( count( $out['sparse_posts'] ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	/* ============================ Underperforming topics ============================ */

	public function underperforming_topics() {
		$out = array();
		if ( ! class_exists( 'RankWriter_AI_Style_Profile' ) ) {
			return $out;
		}
		$style = ( new RankWriter_AI_Style_Profile() )->get();
		$top   = $style['top_performing_posts'] ?? array();
		if ( empty( $top ) ) {
			// No analytics signal — fall back to "thin posts" from the analyzer.
			$thin = $style['raw_signals']['thin_posts'] ?? array();
			foreach ( array_slice( $thin, 0, 10 ) as $t ) {
				$out[] = array(
					'post_id' => (int) $t['id'],
					'title'   => $t['title'],
					'reason'  => sprintf( '%d words — thin content', (int) $t['words'] ),
				);
			}
			return $out;
		}

		// Find the bottom 25% of the "top performers" list (paradoxically,
		// the weakest entries IN the top list are still the under-performers
		// relative to their peers).
		$views = array();
		foreach ( $top as $r ) {
			$views[] = isset( $r['views'] ) ? (int) $r['views'] : ( isset( $r['comments'] ) ? (int) $r['comments'] : 0 );
		}
		if ( empty( $views ) ) {
			return $out;
		}
		sort( $views );
		$threshold = $views[ (int) floor( count( $views ) * 0.25 ) ];
		foreach ( $top as $r ) {
			$metric = isset( $r['views'] ) ? (int) $r['views'] : ( isset( $r['comments'] ) ? (int) $r['comments'] : 0 );
			if ( $metric <= $threshold ) {
				$out[] = array(
					'post_id' => isset( $r['id'] ) ? (int) $r['id'] : 0,
					'title'   => isset( $r['title'] ) ? $r['title'] : '',
					'reason'  => sprintf( 'Bottom 25%% engagement (%d %s)', $metric, isset( $r['views'] ) ? 'views' : 'comments' ),
				);
			}
		}
		return $out;
	}

	/* ============================ Composite opportunity ranking ============================ */

	/**
	 * Score every gap on a 0-100 opportunity scale and return the
	 * highest-priority ones across all gap categories.
	 *
	 * Formula favors: high CPC + priority niche + commercial/transactional
	 * intent + low competition + long-tail (5+ word) keywords.
	 */
	public function rank_opportunities( array $competitor, array $category, array $cluster ) {
		$opps = array();

		foreach ( $competitor as $g ) {
			$opps[] = $this->score_competitor_gap( $g );
		}
		foreach ( $category as $g ) {
			$opps[] = $this->score_category_gap( $g );
		}
		foreach ( $cluster as $g ) {
			foreach ( $g['missing_intents'] as $mi ) {
				$opps[] = $this->score_cluster_intent_gap( $g, $mi );
			}
		}

		usort( $opps, function ( $a, $b ) {
			return $b['opportunity_score'] - $a['opportunity_score'];
		} );
		return $opps;
	}

	private function score_competitor_gap( $g ) {
		$score = 40;
		switch ( $g['cpc_tier'] ?? 'low' ) {
			case 'extreme': $score += 40; break;
			case 'high':    $score += 25; break;
			case 'medium':  $score += 10; break;
		}
		if ( ! empty( $g['priority_niche'] ) ) {
			$score += 15;
		}
		if ( in_array( $g['intent'] ?? '', array( 'commercial', 'transactional' ), true ) ) {
			$score += 12;
		}
		if ( ( $g['competition'] ?? 'medium' ) === 'low' ) {
			$score += 8;
		} elseif ( ( $g['competition'] ?? 'medium' ) === 'high' ) {
			$score -= 8;
		}
		if ( ( $g['token_count'] ?? 0 ) >= 5 ) {
			$score += 6;
		}
		return array_merge( $g, array(
			'type'              => 'competitor_keyword_gap',
			'topic'             => $g['keyword'],
			'opportunity_score' => max( 0, min( 100, (int) round( $score ) ) ),
		) );
	}

	private function score_category_gap( $g ) {
		$score = 35 + min( 40, (int) ( $g['shortfall'] * 4 ) );
		return array_merge( $g, array(
			'type'              => 'category_coverage_gap',
			'topic'             => 'Build out: ' . $g['category'],
			'opportunity_score' => max( 0, min( 100, $score ) ),
		) );
	}

	private function score_cluster_intent_gap( $cluster, $missing_intent ) {
		$intent_label = $missing_intent;
		if ( 0 === strpos( $missing_intent, 'intent:' ) ) {
			$intent_label = substr( $missing_intent, 7 );
		}
		$score = 55;
		if ( in_array( $intent_label, array( 'commercial', 'transactional' ), true ) ) {
			$score += 20;
		}
		// Lower completion → higher opportunity.
		$score += max( 0, 30 - ( (int) $cluster['completion'] / 3 ) );
		return array(
			'type'              => 'cluster_gap',
			'topic'             => $cluster['cluster_name'] . ' → ' . $intent_label,
			'cluster_id'        => (int) $cluster['cluster_id'],
			'cluster_name'      => $cluster['cluster_name'],
			'missing_intent'    => $missing_intent,
			'completion'        => (int) $cluster['completion'],
			'opportunity_score' => max( 0, min( 100, (int) round( $score ) ) ),
		);
	}

	/* ============================ helpers ============================ */

	private function tokenize( $text ) {
		if ( class_exists( 'RankWriter_AI_Helpers' ) ) {
			return RankWriter_AI_Helpers::tokenize( $text );
		}
		$text = strtolower( wp_strip_all_tags( (string) $text ) );
		$text = preg_replace( '/[^a-z0-9\s]/', ' ', $text );
		$parts = preg_split( '/\s+/', trim( (string) $text ) );
		return array_values( array_filter( (array) $parts, function ( $t ) { return strlen( $t ) >= 4; } ) );
	}
}
