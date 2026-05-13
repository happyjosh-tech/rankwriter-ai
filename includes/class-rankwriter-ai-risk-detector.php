<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Content Risk Detector.
 *
 * Sits alongside the existing Compliance class. Compliance enforces the
 * Category-Profile-level banned-terms list and Google's prohibited-content
 * top-level rules; this detector goes deeper:
 *
 *   - dangerous medical advice (dosages without disclaimer, "cures",
 *     "guaranteed treatment")
 *   - fake financial promises ("guaranteed returns", "double your money")
 *   - fake immigration guarantees ("100% visa approval")
 *   - copyright risk (long verbatim quotes without attribution, image
 *     missing licensing context)
 *   - plagiarism risk (near-duplicates of existing posts on the same site
 *     via shingle similarity)
 *   - spam / clickbait / AI-tell patterns
 *   - adult content beyond the AdSense top-level matcher
 *
 * Produces two scores:
 *   - risk_score              0 (safe) → 100 (high-risk)
 *   - adsense_compliance_score 0 (will-be-demonetized) → 100 (clean)
 *
 * Output is sectional: each issue stores the matched snippet so the UI
 * can highlight the offending passages. Pure heuristic / no API by
 * default; an optional Claude pass can deepen the "misleading claim"
 * check for ambiguous wording.
 */
class RankWriter_AI_Risk_Detector {

	const META_REPORT     = '_rwai_risk_report';
	const META_SCORE      = '_rwai_risk_score';
	const META_ADSENSE    = '_rwai_adsense_score';

	const SEV_CRITICAL = 'critical';
	const SEV_ERROR    = 'error';
	const SEV_WARNING  = 'warning';
	const SEV_INFO     = 'info';

	const RISK_THRESHOLD_BLOCK = 70; // ≥ this and warn-before-publish kicks in

	/* ============================ Public entry ============================ */

	public function scan_post( $post_id, $opts = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}
		$report = $this->scan_content( $post->post_title, $post->post_content, array(
			'compare_against_existing' => true,
			'self_post_id'             => (int) $post_id,
			'use_claude'               => ! empty( $opts['use_claude'] ),
		) );

		update_post_meta( $post_id, self::META_REPORT, $report );
		update_post_meta( $post_id, self::META_SCORE, $report['risk_score'] );
		update_post_meta( $post_id, self::META_ADSENSE, $report['adsense_compliance_score'] );
		return $report;
	}

	public function get_report( $post_id ) {
		$r = get_post_meta( $post_id, self::META_REPORT, true );
		return is_array( $r ) ? $r : array();
	}

	/* ============================ Core scan ============================ */

	/**
	 * Run every detector against (title + body). Returns a single report
	 * with composite scores and a flat warnings array for UI rendering.
	 */
	public function scan_content( $title, $html, $opts = array() ) {
		$plain = wp_strip_all_tags( $html );

		$warnings = array();
		$categories = array(
			'medical'      => $this->detect_medical( $plain ),
			'financial'    => $this->detect_financial( $plain ),
			'immigration'  => $this->detect_immigration( $plain ),
			'adult'        => $this->detect_adult( $plain ),
			'spam'         => $this->detect_spam_signals( $plain, $title ),
			'misleading'   => $this->detect_misleading( $plain ),
			'copyright'    => $this->detect_copyright_risk( $html, $plain ),
			'plagiarism'   => $this->detect_plagiarism( $plain, $opts ),
		);

		foreach ( $categories as $cat => $cat_warnings ) {
			foreach ( $cat_warnings as $w ) {
				$w['category'] = $cat;
				$warnings[] = $w;
			}
		}

		// Optional Claude deep-check for ambiguous medical / financial /
		// immigration phrasing the heuristics may have under-rated.
		if ( ! empty( $opts['use_claude'] ) && $this->should_run_claude( $categories ) ) {
			$claude = $this->run_claude_review( $title, $plain, $categories );
			foreach ( $claude as $w ) {
				$w['category'] = $w['category'] ?? 'claude';
				$warnings[] = $w;
			}
		}

		$risk_score    = $this->compute_risk_score( $warnings );
		$adsense_score = $this->compute_adsense_score( $warnings );

		return array(
			'generated_at'              => current_time( 'mysql' ),
			'risk_score'                => $risk_score,
			'adsense_compliance_score'  => $adsense_score,
			'risk_band'                 => $this->risk_band( $risk_score ),
			'should_block_publish'      => $risk_score >= self::RISK_THRESHOLD_BLOCK,
			'warnings'                  => $warnings,
			'category_counts'           => array_map( 'count', $categories ),
		);
	}

	/* ============================ Detectors ============================ */

	protected function detect_medical( $text ) {
		$out = array();
		$lower = strtolower( $text );

		// "Cures" / "guaranteed treatment" / "miracle" claims
		if ( preg_match_all( '/\b(cures?|miracle\s+(?:cure|drug|treatment)|guaranteed\s+(?:treatment|cure|relief)|reverses?\s+(?:diabetes|cancer|alzheimer))\b/i', $text, $m ) ) {
			foreach ( array_unique( $m[0] ) as $hit ) {
				$out[] = array(
					'severity'   => self::SEV_CRITICAL,
					'rule'       => 'medical_cure_claim',
					'text'       => $hit,
					'message'    => __( 'Claims about "curing" disease violate Google\'s YMYL standards and can trigger Helpful Content / medical-misinformation demotion.', 'rankwriter-ai' ),
					'suggestion' => __( 'Replace with "may help manage" or "studies suggest can support" and cite a medical authority (Mayo, NIH, WHO).', 'rankwriter-ai' ),
				);
			}
		}

		// Specific dosages without "consult your doctor"
		if ( preg_match( '/\b\d+\s*(mg|mcg|iu|grams?|tablets?)\b/i', $text ) && false === stripos( $lower, 'consult' ) && false === stripos( $lower, 'doctor' ) && false === stripos( $lower, 'physician' ) ) {
			$out[] = array(
				'severity'   => self::SEV_ERROR,
				'rule'       => 'medical_dosage_no_disclaimer',
				'text'       => '',
				'message'    => __( 'Post quotes specific dosages without a "consult your doctor" disclaimer.', 'rankwriter-ai' ),
				'suggestion' => __( 'Add a disclaimer near the dosage and link to the official patient leaflet.', 'rankwriter-ai' ),
			);
		}

		// Anti-vax / treatment-denial signals
		if ( preg_match( '/\b(vaccines?\s+(?:cause|caused)\s+autism|natural\s+immunity\s+is\s+better\s+than\s+vaccine|chemo\s+is\s+a\s+scam|big\s+pharma\s+hides|essential\s+oils?\s+cure)\b/i', $text, $m ) ) {
			$out[] = array(
				'severity'   => self::SEV_CRITICAL,
				'rule'       => 'medical_misinformation',
				'text'       => $m[0],
				'message'    => __( 'Post contains language that Google classifies as medical misinformation.', 'rankwriter-ai' ),
				'suggestion' => __( 'Remove the claim entirely or replace with the consensus position from a major health body.', 'rankwriter-ai' ),
			);
		}

		return $out;
	}

	protected function detect_financial( $text ) {
		$out = array();
		$patterns_critical = array(
			'/\bguaranteed?\s+(?:return|profit|income|earnings)\b/i',
			'/\bdouble\s+your\s+(?:money|investment)\b/i',
			'/\brisk[-\s]?free\s+(?:investment|trade|trading)\b/i',
			'/\bget\s+rich\s+quick\b/i',
			'/\bno\s+risk\s+(?:investment|return)\b/i',
			'/\bpassive\s+income\s+overnight\b/i',
			'/\b\$\d{3,}\s+a\s+day\s+from\s+home\b/i',
		);
		foreach ( $patterns_critical as $p ) {
			if ( preg_match( $p, $text, $m ) ) {
				$out[] = array(
					'severity'   => self::SEV_CRITICAL,
					'rule'       => 'financial_guarantee',
					'text'       => $m[0],
					'message'    => __( 'Investment guarantee language violates AdSense + FTC rules and exposes the publisher to fraud-claim liability.', 'rankwriter-ai' ),
					'suggestion' => __( 'Replace with "historically averaged" / "may yield" / "past performance" and add a "not financial advice" disclaimer.', 'rankwriter-ai' ),
				);
			}
		}
		// Crypto / forex pump signals
		if ( preg_match( '/\b(\d{2,3})x\s+(?:returns?|gains?|profits?)\b/i', $text, $m ) ) {
			$out[] = array(
				'severity'   => self::SEV_ERROR,
				'rule'       => 'financial_pump_claim',
				'text'       => $m[0],
				'message'    => __( 'Multiple-X return claims read as pump signals to AdSense reviewers.', 'rankwriter-ai' ),
				'suggestion' => __( 'Anchor the claim to a specific historical asset and date, with the source link.', 'rankwriter-ai' ),
			);
		}
		// Disclaimer absence — financial topic + no risk disclosure
		$lower = strtolower( $text );
		$is_finance = preg_match( '/\b(invest|stocks?|crypto|forex|trading|bitcoin|ethereum|portfolio|mutual\s+fund|401k|ira)\b/i', $text );
		$has_disclaimer = ( false !== strpos( $lower, 'not financial advice' ) )
			|| ( false !== strpos( $lower, 'consult a financial' ) )
			|| ( false !== strpos( $lower, 'past performance' ) )
			|| ( false !== strpos( $lower, 'do your own research' ) );
		if ( $is_finance && ! $has_disclaimer ) {
			$out[] = array(
				'severity'   => self::SEV_WARNING,
				'rule'       => 'financial_no_disclaimer',
				'text'       => '',
				'message'    => __( 'Financial / investing post lacks a "not financial advice" disclaimer.', 'rankwriter-ai' ),
				'suggestion' => __( 'Add a short disclaimer paragraph before or after the main advice.', 'rankwriter-ai' ),
			);
		}
		return $out;
	}

	protected function detect_immigration( $text ) {
		$out = array();
		$patterns = array(
			'/\b(?:100%|guaranteed)\s+visa\s+(?:approval|success)\b/i',
			'/\bguaranteed\s+(?:green\s+card|citizenship|residency|asylum)\b/i',
			'/\b(?:we|i)\s+can\s+(?:get|guarantee)\s+you\s+a\s+visa\b/i',
			'/\bvisa\s+(?:without|no)\s+(?:interview|paperwork|documents)\b/i',
			'/\bbuy\s+(?:a\s+)?(?:green\s+card|passport)\s+online\b/i',
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $text, $m ) ) {
				$out[] = array(
					'severity'   => self::SEV_CRITICAL,
					'rule'       => 'immigration_guarantee',
					'text'       => $m[0],
					'message'    => __( 'Guaranteed visa / immigration language is fraud-adjacent and a violation of AdSense\'s misrepresentative-content policy.', 'rankwriter-ai' ),
					'suggestion' => __( 'Frame as "eligibility criteria" / "how the process works" and link to the official government immigration site.', 'rankwriter-ai' ),
				);
			}
		}
		// Topic without official-source link
		if ( preg_match( '/\b(visa|immigration|green\s+card|asylum|h-?1b|f-?1)\b/i', $text ) ) {
			if ( ! preg_match( '/\b(uscis\.gov|gov\.uk|canada\.ca|state\.gov|ec\.europa\.eu|home\s+office)\b/i', $text ) ) {
				$out[] = array(
					'severity'   => self::SEV_WARNING,
					'rule'       => 'immigration_no_official_link',
					'text'       => '',
					'message'    => __( 'Immigration post has no citation to an official government source.', 'rankwriter-ai' ),
					'suggestion' => __( 'Add at least one link to the relevant immigration authority (uscis.gov, gov.uk, canada.ca, etc.).', 'rankwriter-ai' ),
				);
			}
		}
		return $out;
	}

	protected function detect_adult( $text ) {
		$out = array();
		// Heavier than the AdSense top-level matcher — this is the
		// section-level check.
		$patterns = array(
			'/\b(?:hardcore\s+)?porn(?:o|ography)?\b/i',
			'/\bxxx\s+(?:videos?|images?|content)\b/i',
			'/\bonlyfans\s+(?:leak|content)\b/i',
			'/\bexplicit\s+(?:nudity|sexual)\b/i',
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $text, $m ) ) {
				$out[] = array(
					'severity'   => self::SEV_CRITICAL,
					'rule'       => 'adult_explicit',
					'text'       => $m[0],
					'message'    => __( 'Explicit adult content is a Google Publisher Policy violation — pages with this language are not eligible for AdSense.', 'rankwriter-ai' ),
					'suggestion' => __( 'Remove the language entirely. If the topic is necessary (e.g. medical), use clinical terminology and add a content advisory.', 'rankwriter-ai' ),
				);
			}
		}
		return $out;
	}

	protected function detect_spam_signals( $text, $title = '' ) {
		$out = array();
		// Classic clickbait + AI-tell openers
		$clickbait = array(
			'/\bdoctors\s+hate\s+(?:this|him|her)\b/i',
			'/\b(?:shocking|insane|unbelievable)\s+(?:trick|secret|hack)\b/i',
			'/\byou\s+won\'t\s+believe\b/i',
			'/\bnumber\s+\d+\s+will\s+(?:shock|surprise)\s+you\b/i',
			'/\bone\s+weird\s+trick\b/i',
			'/\bthey\s+don\'t\s+want\s+you\s+to\s+know\b/i',
		);
		foreach ( $clickbait as $p ) {
			if ( preg_match( $p, $text . ' ' . $title, $m ) ) {
				$out[] = array(
					'severity'   => self::SEV_ERROR,
					'rule'       => 'spam_clickbait',
					'text'       => $m[0],
					'message'    => __( 'Clickbait phrasing triggers low-quality classifiers.', 'rankwriter-ai' ),
					'suggestion' => __( 'Rewrite into a specific, factual claim or benefit statement.', 'rankwriter-ai' ),
				);
			}
		}
		// Hype words density check — too many "ultimate / amazing / incredible" reads spammy
		$hype = preg_match_all( '/\b(ultimate|amazing|incredible|insane|mind[-\s]?blowing|game[-\s]?changing|revolutionary)\b/i', $text );
		$words = max( 1, str_word_count( $text ) );
		if ( $hype > 0 && ( $hype / $words ) > 0.01 ) {
			$out[] = array(
				'severity'   => self::SEV_WARNING,
				'rule'       => 'spam_hype_density',
				'text'       => sprintf( __( '%d hype words', 'rankwriter-ai' ), $hype ),
				'message'    => __( 'Density of hyperbole words is unusually high.', 'rankwriter-ai' ),
				'suggestion' => __( 'Replace half of these with specific factual claims.', 'rankwriter-ai' ),
			);
		}
		// Exit-pop / fake urgency
		if ( preg_match( '/\b(limited\s+time\s+only|act\s+now\s+before|only\s+\d+\s+left\s+today|prices?\s+go\s+up\s+at\s+midnight)\b/i', $text, $m ) ) {
			$out[] = array(
				'severity'   => self::SEV_WARNING,
				'rule'       => 'spam_fake_urgency',
				'text'       => $m[0],
				'message'    => __( 'Fake-urgency phrasing is flagged by both AdSense and FTC.', 'rankwriter-ai' ),
				'suggestion' => __( 'Remove the urgency unless the deadline is real and verifiable.', 'rankwriter-ai' ),
			);
		}
		return $out;
	}

	protected function detect_misleading( $text ) {
		$out = array();
		// "Proven by science" without citation
		if ( preg_match( '/\b(?:proven\s+(?:by\s+)?science|scientifically\s+proven|studies\s+have\s+proven)\b/i', $text, $m ) ) {
			if ( ! preg_match( '/\b(?:doi\.org|pubmed|nih\.gov|nature\.com|science\.org|jama|lancet|bmj)\b/i', $text ) ) {
				$out[] = array(
					'severity'   => self::SEV_WARNING,
					'rule'       => 'misleading_unsourced_proof',
					'text'       => $m[0],
					'message'    => __( '"Proven by science" / "scientifically proven" without a primary-source citation reads as a misleading claim.', 'rankwriter-ai' ),
					'suggestion' => __( 'Cite the actual paper (pubmed.gov, doi.org, nature.com) or soften to "research suggests".', 'rankwriter-ai' ),
				);
			}
		}
		// Absolutes
		if ( preg_match_all( '/\b(always|never|every|none|all)\s+\w+/i', $text, $m ) ) {
			$abs_count = count( $m[0] );
			if ( $abs_count > 8 ) {
				$out[] = array(
					'severity'   => self::SEV_INFO,
					'rule'       => 'misleading_absolutes',
					'text'       => sprintf( __( '%d absolute statements', 'rankwriter-ai' ), $abs_count ),
					'message'    => __( 'Article leans heavily on absolutes ("always", "never", "every"). Most real-world advice has exceptions.', 'rankwriter-ai' ),
					'suggestion' => __( 'Soften the strongest claims with "usually", "often", "in most cases".', 'rankwriter-ai' ),
				);
			}
		}
		return $out;
	}

	protected function detect_copyright_risk( $html, $text ) {
		$out = array();

		// Long verbatim block quotes (>40 words) — likely copyright sensitive
		if ( preg_match_all( '#<blockquote[^>]*>(.+?)</blockquote>#is', $html, $m ) ) {
			foreach ( $m[1] as $bq ) {
				$plain = wp_strip_all_tags( $bq );
				$wc = str_word_count( $plain );
				if ( $wc > 40 ) {
					// Check for citation cue
					$ctx = $plain;
					if ( ! preg_match( '/\b(source|—|via|from|according\s+to|—\s+\w+)\b/i', $ctx ) ) {
						$out[] = array(
							'severity'   => self::SEV_WARNING,
							'rule'       => 'copyright_long_unattributed_quote',
							'text'       => wp_trim_words( $plain, 12 ),
							'message'    => sprintf( __( 'A %d-word block quote appears without an inline attribution.', 'rankwriter-ai' ), $wc ),
							'suggestion' => __( 'Add the source publisher + URL inline, or shorten the quote to under 40 words.', 'rankwriter-ai' ),
						);
					}
				}
			}
		}

		// Images: external <img> from third-party hosts without alt + cite hint
		if ( preg_match_all( '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#is', $html, $im, PREG_SET_ORDER ) ) {
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			$external = 0;
			$first_external = '';
			foreach ( $im as $tag ) {
				$src = $tag[1];
				$host = wp_parse_url( $src, PHP_URL_HOST );
				if ( $host && $host !== $home && ! preg_match( '/(pexels|unsplash|wikimedia|pixabay|openverse)/i', $host ) ) {
					$external++;
					if ( '' === $first_external ) { $first_external = $host; }
				}
			}
			if ( $external > 0 ) {
				$out[] = array(
					'severity'   => self::SEV_WARNING,
					'rule'       => 'copyright_unknown_image_source',
					'text'       => $first_external,
					'message'    => sprintf( __( '%d image(s) loaded from an external host that is not a known stock source (Pexels / Unsplash / Wikimedia / Pixabay / Openverse).', 'rankwriter-ai' ), $external ),
					'suggestion' => __( 'Replace with a self-hosted image, a properly licensed stock image, or add a caption with the license + source.', 'rankwriter-ai' ),
				);
			}
		}

		// "Reprinted from" / "Originally appeared on" — surface so the user
		// adds a canonical link.
		if ( preg_match( '/\b(originally\s+(?:appeared|published)\s+on|reprinted\s+from|excerpted\s+from)\b/i', $text, $m ) ) {
			$out[] = array(
				'severity'   => self::SEV_INFO,
				'rule'       => 'copyright_reprint_signal',
				'text'       => $m[0],
				'message'    => __( 'Post reads like a reprint or partial republication.', 'rankwriter-ai' ),
				'suggestion' => __( 'Set rel=canonical to the original URL to avoid duplicate-content penalties.', 'rankwriter-ai' ),
			);
		}
		return $out;
	}

	protected function detect_plagiarism( $text, array $opts = array() ) {
		$out = array();
		if ( empty( $opts['compare_against_existing'] ) ) {
			return $out;
		}
		$self_id = (int) ( $opts['self_post_id'] ?? 0 );

		$shingles = $this->shingles( $text, 5 );
		if ( count( $shingles ) < 25 ) {
			return $out; // too short to be meaningfully tested
		}
		$sample = array_intersect_key( $shingles, array_flip( array_rand( $shingles, min( 200, count( $shingles ) ) ) ) );

		// Compare against last 50 posts (excluding self). We cap to keep
		// this lightweight.
		$others = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'exclude'        => array( $self_id ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		$worst = array( 'sim' => 0, 'post_id' => 0 );
		foreach ( $others as $p ) {
			$o_shingles = $this->shingles( wp_strip_all_tags( $p->post_content ), 5 );
			if ( count( $o_shingles ) < 25 ) {
				continue;
			}
			$intersect = count( array_intersect_key( $sample, $o_shingles ) );
			$union     = max( 1, count( $sample ) );
			$jaccard   = $intersect / $union;
			if ( $jaccard > $worst['sim'] ) {
				$worst = array( 'sim' => $jaccard, 'post_id' => (int) $p->ID, 'title' => $p->post_title );
			}
		}

		if ( $worst['sim'] >= 0.35 ) {
			$out[] = array(
				'severity'   => $worst['sim'] >= 0.55 ? self::SEV_CRITICAL : self::SEV_ERROR,
				'rule'       => 'plagiarism_internal_near_duplicate',
				'text'       => $worst['title'] ?? '',
				'message'    => sprintf(
					/* translators: 1: percent similarity, 2: title */
					__( '%1$d%% shingle overlap with "%2$s" — this post is at risk of being a duplicate of your own existing content.', 'rankwriter-ai' ),
					(int) round( $worst['sim'] * 100 ),
					$worst['title'] ?? ''
				),
				'suggestion' => __( 'Either consolidate the two posts into one canonical version, or substantively rewrite the new piece so the overlap drops below 25%.', 'rankwriter-ai' ),
				'related_post_id' => $worst['post_id'],
			);
		}
		return $out;
	}

	/* ============================ Claude validation (optional) ============================ */

	protected function should_run_claude( array $cats ) {
		// Trigger only when heuristics found at least 2 categories with hits
		// and Claude is configured — keeps API spend tight.
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) { return false; }
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) { return false; }
		$hot = 0;
		foreach ( $cats as $arr ) { if ( ! empty( $arr ) ) { $hot++; } }
		return $hot >= 2;
	}

	protected function run_claude_review( $title, $text, array $categories ) {
		$client = new RankWriter_AI_Claude_Client();
		$system = "You review article drafts for content-risk. " .
			"Your job is to flag claims that are LIKELY to violate AdSense's misrepresentative-content policy, Google YMYL standards, or expose the publisher to fraud/medical-liability claims. " .
			"Be specific. If a claim is fine, do NOT flag it — false positives are worse than false negatives. " .
			"Return ONLY valid JSON: " .
			'{"warnings":[{"severity":"critical|error|warning","rule":"short_machine_rule_id","text":"<exact quoted phrase from the text>","message":"why this is risky","suggestion":"how to fix it"}]}';

		// Send a trimmed version of the text — first 3500 words is plenty.
		$words = preg_split( '/\s+/', wp_strip_all_tags( $text ) );
		$snippet = implode( ' ', array_slice( $words, 0, 3500 ) );

		$user = "TITLE: " . $title . "\n\nDRAFT BODY (first 3500 words):\n" . $snippet;

		$raw = $client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );
		if ( is_wp_error( $raw ) || '' === trim( (string) $raw ) ) {
			return array();
		}
		$parsed = $this->parse_json( $raw );
		if ( ! is_array( $parsed ) || empty( $parsed['warnings'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $parsed['warnings'] as $w ) {
			if ( empty( $w['severity'] ) || empty( $w['message'] ) ) { continue; }
			$sev = in_array( $w['severity'], array( 'critical', 'error', 'warning', 'info' ), true ) ? $w['severity'] : 'warning';
			$out[] = array(
				'severity'   => $sev,
				'rule'       => 'claude_' . sanitize_key( $w['rule'] ?? 'review' ),
				'text'       => (string) ( $w['text'] ?? '' ),
				'message'    => (string) $w['message'],
				'suggestion' => (string) ( $w['suggestion'] ?? '' ),
				'category'   => 'claude_review',
			);
		}
		return $out;
	}

	/* ============================ Scoring ============================ */

	protected function compute_risk_score( array $warnings ) {
		$score = 0;
		foreach ( $warnings as $w ) {
			switch ( $w['severity'] ?? '' ) {
				case self::SEV_CRITICAL: $score += 25; break;
				case self::SEV_ERROR:    $score += 10; break;
				case self::SEV_WARNING:  $score += 4;  break;
				case self::SEV_INFO:     $score += 1;  break;
			}
		}
		return min( 100, $score );
	}

	protected function compute_adsense_score( array $warnings ) {
		$adsense_categories = array( 'medical', 'financial', 'immigration', 'adult', 'spam', 'misleading' );
		$score = 100;
		foreach ( $warnings as $w ) {
			$cat = $w['category'] ?? '';
			if ( ! in_array( $cat, $adsense_categories, true ) && 'claude_review' !== $cat ) {
				continue;
			}
			switch ( $w['severity'] ?? '' ) {
				case self::SEV_CRITICAL: $score -= 45; break;
				case self::SEV_ERROR:    $score -= 18; break;
				case self::SEV_WARNING:  $score -= 6;  break;
				case self::SEV_INFO:     $score -= 1;  break;
			}
		}
		return max( 0, $score );
	}

	protected function risk_band( $score ) {
		if ( $score >= 70 ) return 'high';
		if ( $score >= 35 ) return 'medium';
		if ( $score >= 10 ) return 'low';
		return 'safe';
	}

	/* ============================ Helpers ============================ */

	protected function shingles( $text, $n = 5 ) {
		$text = strtolower( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		$words = preg_split( '/\s+/', $text );
		$out = array();
		$count = count( $words );
		for ( $i = 0; $i + $n <= $count; $i++ ) {
			$out[ implode( ' ', array_slice( $words, $i, $n ) ) ] = 1;
		}
		return $out;
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

	/* ============================ Bulk audit ============================ */

	public function bulk_audit( $limit = 30 ) {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 200, (int) $limit ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		$rows = array();
		foreach ( $posts as $p ) {
			$existing = (int) get_post_meta( $p->ID, self::META_SCORE, true );
			if ( ! get_post_meta( $p->ID, self::META_REPORT, true ) ) {
				$this->scan_post( $p->ID );
				$existing = (int) get_post_meta( $p->ID, self::META_SCORE, true );
			}
			$rows[] = array(
				'post_id'   => $p->ID,
				'title'     => $p->post_title,
				'risk'      => $existing,
				'adsense'   => (int) get_post_meta( $p->ID, self::META_ADSENSE, true ),
				'modified'  => $p->post_modified,
			);
		}
		usort( $rows, function ( $a, $b ) { return $b['risk'] - $a['risk']; } );
		return $rows;
	}
}
