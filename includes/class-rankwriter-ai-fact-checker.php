<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fact-checker engine.
 *
 * Two-stage validation:
 *
 *   1. Heuristic pass (free, fast) — regex-detect dates, deadlines, visa
 *      claims, salary figures, statistics, and outbound links. Flag obvious
 *      issues like "expired" dates / non-official sources.
 *   2. Optional Claude pass — when there are detected claims that the
 *      heuristics can't validate alone (e.g. "is this salary realistic for
 *      a junior dev in Berlin in 2026?"), ask Claude. Skipped if no API
 *      key is configured so the heuristic report is always available.
 *
 * Produces a structured report stored in post-meta. The Content Refresher
 * reads this report to decide which posts need updating.
 */
class RankWriter_AI_Fact_Checker {

	const META_REPORT      = '_rwai_fact_report';
	const META_FRESH_SCORE = '_rwai_freshness_score';
	const META_LAST_CHECK  = '_rwai_fact_last_check';

	const SEVERITY_ERROR   = 'error';
	const SEVERITY_WARNING = 'warning';
	const SEVERITY_INFO    = 'info';

	/**
	 * Run the full check for a single post. Returns the saved report.
	 */
	public function check_post( $post_id, $use_claude = true ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}

		$content = (string) $post->post_content;
		$title   = (string) $post->post_title;
		$age_days = max( 0, (int) floor( ( time() - strtotime( $post->post_modified_gmt ?: $post->post_date_gmt ) ) / DAY_IN_SECONDS ) );

		$checks = array(
			'dates'      => $this->check_dates( $content ),
			'deadlines'  => $this->check_deadlines( $content ),
			'visa'       => $this->check_visa( $content ),
			'salary'     => $this->check_salary( $content ),
			'statistics' => $this->check_statistics( $content ),
			'links'      => $this->check_links( $content, $title ),
		);

		$warnings = array();
		foreach ( $checks as $type => $result ) {
			foreach ( $result['warnings'] as $w ) {
				$w['category'] = $type;
				$warnings[] = $w;
			}
		}

		// Heuristic confidence: start at 100, deduct per warning by severity.
		$confidence = 100;
		foreach ( $warnings as $w ) {
			switch ( $w['severity'] ) {
				case self::SEVERITY_ERROR:   $confidence -= 12; break;
				case self::SEVERITY_WARNING: $confidence -= 5;  break;
				case self::SEVERITY_INFO:    $confidence -= 1;  break;
			}
		}
		$confidence = max( 0, min( 100, $confidence ) );

		// Freshness blends post age, expired-date warnings, and outdated-stat
		// flags. A 2-year-old post with no expired claims can still score
		// 70-ish; a 6-month post with expired scholarship deadlines tanks
		// to ~30. Tuneable thresholds, not magic.
		$freshness = $this->score_freshness( $age_days, $checks );

		$claude_review = array();
		if ( $use_claude && $this->should_run_claude( $checks ) ) {
			$claude_review = $this->run_claude_validation( $title, $content, $checks );
			if ( ! empty( $claude_review['warnings'] ) ) {
				foreach ( $claude_review['warnings'] as $w ) {
					$w['category'] = 'claude';
					$warnings[] = $w;
					if ( self::SEVERITY_ERROR === $w['severity'] ) {
						$confidence = max( 0, $confidence - 8 );
						$freshness  = max( 0, $freshness - 6 );
					} elseif ( self::SEVERITY_WARNING === $w['severity'] ) {
						$confidence = max( 0, $confidence - 3 );
						$freshness  = max( 0, $freshness - 2 );
					}
				}
			}
		}

		$report = array(
			'generated_at'          => current_time( 'mysql' ),
			'post_age_days'         => $age_days,
			'fact_confidence_score' => $confidence,
			'freshness_score'       => $freshness,
			'warnings'              => $warnings,
			'checks'                => $checks,
			'claude_review'         => $claude_review,
			'outdated'              => $freshness < 50 || $this->has_severity( $warnings, self::SEVERITY_ERROR ),
		);

		update_post_meta( $post_id, self::META_REPORT, $report );
		update_post_meta( $post_id, self::META_FRESH_SCORE, $freshness );
		update_post_meta( $post_id, self::META_LAST_CHECK, current_time( 'mysql' ) );

		return $report;
	}

	public function get_report( $post_id ) {
		$report = get_post_meta( $post_id, self::META_REPORT, true );
		return is_array( $report ) ? $report : array();
	}

	public function get_freshness( $post_id ) {
		$score = get_post_meta( $post_id, self::META_FRESH_SCORE, true );
		return '' === $score ? null : (int) $score;
	}

	/* ============================ Dates ============================ */

	public function check_dates( $content ) {
		$found    = array();
		$warnings = array();
		$text     = wp_strip_all_tags( $content );

		// Match dates in formats: "January 15, 2024", "15 Jan 2024", "Jan 15
		// 2024", "2024-01-15", "01/15/2024". Each capture is wide enough to
		// catch the most common variants without over-fitting.
		$patterns = array(
			'/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(\d{4})\b/i',
			'/\b(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+(\d{4})\b/i',
			'/\b(\d{4})-(\d{2})-(\d{2})\b/',
			'/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/',
		);

		$now      = time();
		$current_year = (int) current_time( 'Y' );

		foreach ( $patterns as $p ) {
			if ( preg_match_all( $p, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $m ) {
					$raw  = $m[0];
					$ts   = strtotime( $raw );
					if ( ! $ts ) { continue; }
					$year = (int) date( 'Y', $ts );
					if ( $year < 1990 || $year > $current_year + 10 ) {
						continue; // garbage match
					}
					$item = array(
						'raw'        => $raw,
						'timestamp'  => $ts,
						'iso'        => date( 'Y-m-d', $ts ),
						'is_past'    => $ts < $now,
						'years_old'  => max( 0, $current_year - $year ),
					);
					$found[] = $item;
				}
			}
		}

		// Dedup by ISO+context
		$seen = array();
		$dedup = array();
		foreach ( $found as $f ) {
			if ( isset( $seen[ $f['iso'] ] ) ) { continue; }
			$seen[ $f['iso'] ] = true;
			$dedup[] = $f;
		}
		$found = $dedup;

		// Warn about dates >= 3 years old or anchored to "in YYYY" with old
		// year — that's a tell of stale "trends" / "stats" content.
		foreach ( $found as $f ) {
			if ( $f['years_old'] >= 3 ) {
				$warnings[] = array(
					'type'       => 'old_date_reference',
					'severity'   => $f['years_old'] >= 5 ? self::SEVERITY_ERROR : self::SEVERITY_WARNING,
					'text'       => $f['raw'],
					'detail'     => sprintf( __( 'Date is %d years old. Statistics, trends, or guidance pinned to this date may be stale.', 'rankwriter-ai' ), $f['years_old'] ),
					'suggestion' => __( 'Verify the figure is still current. Replace with the latest year or remove the year anchor.', 'rankwriter-ai' ),
				);
			}
		}

		return array(
			'found'    => count( $found ),
			'items'    => $found,
			'warnings' => $warnings,
		);
	}

	/* ============================ Deadlines (scholarship / visa / grant) ============================ */

	public function check_deadlines( $content ) {
		$text     = wp_strip_all_tags( $content );
		$warnings = array();
		$items    = array();
		$now      = time();

		// Match deadline-flavoured cues followed by a date in any common
		// format. We grep for the cue word + a window of up to 80 chars.
		$cues = '(?:deadline|apply\s+by|applications?\s+close|closing\s+date|cut[-\s]?off|expires?(?:\s+on)?|valid\s+until|due\s+(?:date|by))';
		$dpat = '(?:[A-Z][a-z]+\s+\d{1,2},?\s+\d{4}|\d{1,2}\s+[A-Z][a-z]+\.?\s+\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4})';
		$pattern = '/(' . $cues . ')[^\n]{0,80}?(' . $dpat . ')/i';

		if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $i => $whole ) {
				$cue  = $matches[1][ $i ][0] ?? '';
				$date = $matches[2][ $i ][0] ?? '';
				$ts   = strtotime( $date );
				if ( ! $ts ) { continue; }
				$days_left = (int) floor( ( $ts - $now ) / DAY_IN_SECONDS );
				$items[] = array(
					'cue'        => trim( $cue ),
					'date'       => $date,
					'timestamp'  => $ts,
					'days_left'  => $days_left,
					'is_expired' => $ts < $now,
				);
				if ( $ts < $now ) {
					$warnings[] = array(
						'type'       => 'expired_deadline',
						'severity'   => self::SEVERITY_ERROR,
						'text'       => trim( $whole[0] ),
						'detail'     => sprintf( __( 'Deadline date %1$s has passed (%2$d days ago).', 'rankwriter-ai' ), $date, abs( $days_left ) ),
						'suggestion' => __( 'Update with the next opportunity cycle, or mark the post as historical / archived.', 'rankwriter-ai' ),
					);
				} elseif ( $days_left < 14 ) {
					$warnings[] = array(
						'type'       => 'imminent_deadline',
						'severity'   => self::SEVERITY_INFO,
						'text'       => trim( $whole[0] ),
						'detail'     => sprintf( __( 'Deadline is in %d days — readers are arriving close to the cutoff.', 'rankwriter-ai' ), $days_left ),
						'suggestion' => __( 'Consider adding an "applications still open" or "extended" callout if applicable.', 'rankwriter-ai' ),
					);
				}
			}
		}

		return array(
			'found'    => count( $items ),
			'items'    => $items,
			'warnings' => $warnings,
		);
	}

	/* ============================ Visa info ============================ */

	public function check_visa( $content ) {
		$text     = wp_strip_all_tags( $content );
		$warnings = array();
		$items    = array();

		// Common visa-class signals. These warn the user that the post
		// touches visa policy — fast-changing territory.
		$visa_terms = '(?:H-?1B|H-?2B|F-?1|F-?2|J-?1|L-?1|O-?1|EB-?\d|TN[\s-]?visa|Schengen|UK\s+Skilled\s+Worker|Tier\s+\d|Express\s+Entry|Permanent\s+Resident|Green\s+Card|visa|work\s+permit|residence\s+permit)';
		if ( preg_match_all( '/\b' . $visa_terms . '\b/i', $text, $hits ) ) {
			$found = array_unique( array_map( 'strtolower', $hits[0] ) );
			$items = $found;
			$warnings[] = array(
				'type'       => 'visa_topic_detected',
				'severity'   => self::SEVERITY_WARNING,
				'text'       => implode( ', ', array_slice( $found, 0, 6 ) ),
				'detail'     => __( 'Post discusses visa / immigration policy — rules change frequently. Heuristics cannot verify quotas, fees, or eligibility.', 'rankwriter-ai' ),
				'suggestion' => __( 'Link to the official government source (e.g. uscis.gov, gov.uk, canada.ca) and add a "policy last reviewed on" date.', 'rankwriter-ai' ),
			);
		}

		return array(
			'found'    => count( $items ),
			'items'    => array_values( $items ),
			'warnings' => $warnings,
		);
	}

	/* ============================ Salary figures ============================ */

	public function check_salary( $content ) {
		$text     = wp_strip_all_tags( $content );
		$warnings = array();
		$items    = array();

		// Pattern catches "$50,000", "$50K", "€45,000", "£40k", "USD 60,000",
		// "60,000 per year", "$50,000-$70,000".
		$pattern = '/(?:\$|£|€|USD\s|EUR\s|GBP\s)(\d{1,3}(?:,\d{3})+|\d{3,}(?:\.\d+)?(?:k|K)?)(?:\s*(?:-|to|–)\s*(?:\$|£|€)?(\d{1,3}(?:,\d{3})+|\d{3,}(?:\.\d+)?(?:k|K)?))?/';
		if ( preg_match_all( $pattern, $text, $hits ) ) {
			$items = array_unique( $hits[0] );
		}
		$pattern_pa = '/\b(\d{1,3}(?:,\d{3})+|\d{2,3}(?:\.\d+)?k?)\s+(?:per\s+year|per\s+annum|p\.a\.|annually|salary)\b/i';
		if ( preg_match_all( $pattern_pa, $text, $hits ) ) {
			$items = array_merge( $items, array_unique( $hits[0] ) );
		}

		if ( ! empty( $items ) && count( $items ) > 0 ) {
			$warnings[] = array(
				'type'       => 'salary_claim_detected',
				'severity'   => self::SEVERITY_INFO,
				'text'       => implode( ', ', array_slice( $items, 0, 5 ) ),
				'detail'     => sprintf( __( 'Post quotes %d salary figure(s). Pay bands drift year-over-year; this needs periodic refresh.', 'rankwriter-ai' ), count( $items ) ),
				'suggestion' => __( 'Cite a recent BLS, Glassdoor, or Levels.fyi snapshot with a visible "as of YYYY" anchor.', 'rankwriter-ai' ),
			);
		}

		return array(
			'found'    => count( $items ),
			'items'    => array_values( $items ),
			'warnings' => $warnings,
		);
	}

	/* ============================ Statistics ============================ */

	public function check_statistics( $content ) {
		$text     = wp_strip_all_tags( $content );
		$warnings = array();
		$items    = array();

		// "73% of", "1.2 million people", "studies show", "according to a
		// 2021 report" — all classic stat-anchor cues.
		if ( preg_match_all( '/\b\d{1,3}(?:\.\d+)?\s?%\s+of\s+[a-z]/i', $text, $h ) ) {
			$items = array_merge( $items, $h[0] );
		}
		if ( preg_match_all( '/\b\d{1,3}(?:\.\d+)?\s+(?:million|billion|trillion)\b/i', $text, $h ) ) {
			$items = array_merge( $items, $h[0] );
		}
		if ( preg_match_all( '/\b(?:according to|studies show|research finds?|data from|report by)\s+[a-zA-Z][^.]{3,80}/i', $text, $h ) ) {
			$items = array_merge( $items, array_slice( $h[0], 0, 10 ) );
		}

		// Flag old-year-anchored stats: "in 2019", "the 2020 report".
		$current_year = (int) current_time( 'Y' );
		if ( preg_match_all( '/\b(?:in|since|the)\s+(20\d{2})\b/i', $text, $year_hits ) ) {
			$old = array();
			foreach ( $year_hits[1] as $y ) {
				$y = (int) $y;
				if ( $y > 1990 && ( $current_year - $y ) >= 3 ) {
					$old[] = $y;
				}
			}
			$old = array_unique( $old );
			if ( ! empty( $old ) ) {
				$warnings[] = array(
					'type'       => 'stale_year_anchor',
					'severity'   => self::SEVERITY_WARNING,
					'text'       => implode( ', ', $old ),
					'detail'     => __( 'Content references statistics anchored to years ≥3y old.', 'rankwriter-ai' ),
					'suggestion' => __( 'Re-source the figures from the most recent available data and update the year.', 'rankwriter-ai' ),
				);
			}
		}

		if ( count( $items ) >= 1 ) {
			$warnings[] = array(
				'type'       => 'unverified_statistics',
				'severity'   => self::SEVERITY_INFO,
				'text'       => sprintf( _n( '%d statistical claim found', '%d statistical claims found', count( $items ), 'rankwriter-ai' ), count( $items ) ),
				'detail'     => __( 'Statistical claims should cite a primary source.', 'rankwriter-ai' ),
				'suggestion' => __( 'Add inline citations linking to the original study / dataset.', 'rankwriter-ai' ),
			);
		}

		return array(
			'found'    => count( $items ),
			'items'    => array_slice( array_values( array_unique( $items ) ), 0, 25 ),
			'warnings' => $warnings,
		);
	}

	/* ============================ Outbound links ============================ */

	public function check_links( $content, $title = '' ) {
		$warnings = array();
		$items    = array();

		if ( ! preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER ) ) {
			return array( 'found' => 0, 'items' => array(), 'warnings' => array() );
		}

		// Topic detection: visa / scholarship / government / health / finance
		// each "want" official-domain backing — flag if links point to
		// random .com instead.
		$wants_official = (bool) preg_match( '/(visa|immigration|scholarship|grant|tax|government|policy|healthcare|medicaid|medicare|fda|cdc|usda|nasa|federal)/i', $title . ' ' . wp_strip_all_tags( $content ) );

		$official_pat = '/\.(gov|edu|gov\.[a-z]{2}|ac\.[a-z]{2}|int|mil|nhs\.uk|canada\.ca|europa\.eu|un\.org|who\.int|imf\.org|worldbank\.org)(\/|$)/i';

		$non_official_count = 0;
		foreach ( $matches as $m ) {
			$url = trim( $m[1] );
			if ( '' === $url || '#' === $url || 0 === strpos( $url, '#' ) ) { continue; }
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! $host ) { continue; }
			$is_internal = ( $host === wp_parse_url( home_url(), PHP_URL_HOST ) );
			$is_official = (bool) preg_match( $official_pat, $host );
			$items[] = array(
				'url'         => $url,
				'host'        => $host,
				'is_internal' => $is_internal,
				'is_official' => $is_official,
			);
			if ( $wants_official && ! $is_internal && ! $is_official ) {
				$non_official_count++;
			}
		}

		if ( $wants_official && $non_official_count > 0 ) {
			$warnings[] = array(
				'type'       => 'non_official_sources',
				'severity'   => self::SEVERITY_WARNING,
				'text'       => sprintf( _n( '%d outbound link does not point to an official source', '%d outbound links do not point to official sources', $non_official_count, 'rankwriter-ai' ), $non_official_count ),
				'detail'     => __( 'Topic suggests this post should back claims with .gov / .edu / official-body links.', 'rankwriter-ai' ),
				'suggestion' => __( 'Add at least one citation from the relevant government / institutional site.', 'rankwriter-ai' ),
			);
		}

		return array(
			'found'    => count( $items ),
			'items'    => array_slice( $items, 0, 30 ),
			'warnings' => $warnings,
		);
	}

	/* ============================ Freshness score ============================ */

	protected function score_freshness( $age_days, array $checks ) {
		// Base: age-only decay. Fresh (≤90d) = 100, 1y ≈ 75, 2y ≈ 55, 3y ≈ 40.
		if ( $age_days <= 90 ) {
			$base = 100;
		} elseif ( $age_days <= 180 ) {
			$base = 90;
		} elseif ( $age_days <= 365 ) {
			$base = 75;
		} elseif ( $age_days <= 730 ) {
			$base = 55;
		} elseif ( $age_days <= 1095 ) {
			$base = 40;
		} else {
			$base = 25;
		}

		// Penalize content with expired deadlines hard — those are
		// objectively wrong regardless of post age.
		$expired = 0;
		foreach ( $checks['deadlines']['items'] ?? array() as $d ) {
			if ( ! empty( $d['is_expired'] ) ) { $expired++; }
		}
		$base -= min( 30, $expired * 10 );

		// Penalize stale year anchors and old date refs in statistics.
		foreach ( $checks['statistics']['warnings'] ?? array() as $w ) {
			if ( 'stale_year_anchor' === $w['type'] ) { $base -= 5; }
		}
		foreach ( $checks['dates']['warnings'] ?? array() as $w ) {
			if ( 'old_date_reference' === $w['type'] ) {
				$base -= ( self::SEVERITY_ERROR === $w['severity'] ) ? 8 : 4;
			}
		}

		return max( 0, min( 100, (int) $base ) );
	}

	/* ============================ Claude validation ============================ */

	protected function should_run_claude( array $checks ) {
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return false;
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return false;
		}
		// Only ping Claude when the heuristics found something worth
		// validating. No claims = no spend.
		$signals = (int) ( $checks['statistics']['found'] + $checks['salary']['found'] + $checks['visa']['found'] + count( $checks['deadlines']['items'] ?? array() ) );
		return $signals >= 2;
	}

	protected function run_claude_validation( $title, $content, array $checks ) {
		$client = new RankWriter_AI_Claude_Client();

		// Compact the claims into a short list — we don't send the whole post.
		$claims = array();
		foreach ( $checks['statistics']['items'] ?? array() as $s ) { $claims[] = 'STAT: ' . $s; }
		foreach ( $checks['salary']['items'] ?? array() as $s )     { $claims[] = 'SALARY: ' . $s; }
		foreach ( $checks['visa']['items'] ?? array() as $s )       { $claims[] = 'VISA TERM: ' . $s; }
		foreach ( $checks['deadlines']['items'] ?? array() as $d )  {
			$claims[] = 'DEADLINE: ' . ( $d['cue'] ?? '' ) . ' ' . ( $d['date'] ?? '' );
		}
		$claims = array_slice( $claims, 0, 30 );
		if ( empty( $claims ) ) {
			return array();
		}

		$system = "You are a fact-checking assistant. You will receive an article title and a list of factual claims extracted from it. " .
			"For each claim, judge: (a) is it plausibly current as of today's date? (b) does it sound generic / unverified? (c) is the year reference outdated? " .
			"Return ONLY valid JSON with this shape: " .
			'{"warnings":[{"severity":"error|warning|info","text":"the claim","detail":"why this is suspicious","suggestion":"what to verify or where to source it"}]} ' .
			"Today's date: " . current_time( 'Y-m-d' ) . ". " .
			"Be strict on dated statistics and policy claims; do not flag obviously evergreen statements.";

		$user = "Article title: " . $title . "\n\nClaims:\n- " . implode( "\n- ", $claims );

		$response = $client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );
		if ( is_wp_error( $response ) || '' === trim( (string) $response ) ) {
			return array();
		}

		$parsed = $this->parse_json( $response );
		if ( ! is_array( $parsed ) || empty( $parsed['warnings'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $parsed['warnings'] as $w ) {
			if ( empty( $w['severity'] ) || empty( $w['text'] ) ) { continue; }
			$sev = in_array( $w['severity'], array( 'error', 'warning', 'info' ), true ) ? $w['severity'] : 'info';
			$out[] = array(
				'type'       => 'claude_review',
				'severity'   => $sev,
				'text'       => (string) $w['text'],
				'detail'     => (string) ( $w['detail'] ?? '' ),
				'suggestion' => (string) ( $w['suggestion'] ?? '' ),
			);
		}
		return array(
			'warnings' => $out,
			'raw_count' => count( $out ),
		);
	}

	protected function parse_json( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return null; }
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) { return $decoded; }
		if ( preg_match( '/\{.*\}/s', $raw, $m ) ) {
			$decoded = json_decode( $m[0], true );
			if ( is_array( $decoded ) ) { return $decoded; }
		}
		return null;
	}

	/* ============================ Bulk inventory ============================ */

	/**
	 * Score N most recent posts. Pure heuristic (no Claude) — used for
	 * the stale-content inventory on the Refresher page.
	 */
	public function bulk_inventory( $limit = 50 ) {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 500, (int) $limit ) ),
			'orderby'        => 'modified',
			'order'          => 'ASC', // oldest first → stalest first
		) );
		$rows = array();
		foreach ( $posts as $p ) {
			$existing = $this->get_freshness( $p->ID );
			if ( null === $existing ) {
				$this->check_post( $p->ID, false );
				$existing = (int) $this->get_freshness( $p->ID );
			}
			$rows[] = array(
				'post_id'         => $p->ID,
				'title'           => $p->post_title,
				'modified'        => $p->post_modified,
				'age_days'        => (int) floor( ( time() - strtotime( $p->post_modified_gmt ?: $p->post_date_gmt ) ) / DAY_IN_SECONDS ),
				'freshness_score' => $existing,
			);
		}
		usort( $rows, function ( $a, $b ) {
			return $a['freshness_score'] - $b['freshness_score'];
		} );
		return $rows;
	}

	protected function has_severity( array $warnings, $severity ) {
		foreach ( $warnings as $w ) {
			if ( ( $w['severity'] ?? '' ) === $severity ) { return true; }
		}
		return false;
	}
}
