<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Viral Title Intelligence Engine.
 *
 *   analyze( $title )                            → emotional triggers,
 *                                                  power words, signals,
 *                                                  per-platform CTR score,
 *                                                  clickbait detection,
 *                                                  recommendations.
 *   generate_variants( $topic, $intent, $tier )  → 3 titles per style
 *                                                  (seo / viral / discover /
 *                                                  pinterest / social).
 *   score_ctr( $title, $platform )               → 0-100 platform CTR score.
 *   detect_emotional_triggers( $title )          → list of trigger types.
 *   detect_clickbait( $title )                   → { is_clickbait, hits, reason }
 *   compare( [ $titles ] )                       → side-by-side score table.
 *
 * Design notes:
 *   - Heuristic-first. Every analyze() call is pure PHP, < 1 ms, no API.
 *   - generate_variants() is the only path that hits Claude.
 *   - All clickbait detection is biased toward AdSense-policy safety:
 *     "trust + intrigue" wins over "shock + deceive".
 */
class RankWriter_AI_Title_Intelligence {

	const STYLE_SEO       = 'seo';
	const STYLE_VIRAL     = 'viral';
	const STYLE_DISCOVER  = 'discover';
	const STYLE_PINTEREST = 'pinterest';
	const STYLE_SOCIAL    = 'social';

	public static function styles() {
		return array( self::STYLE_SEO, self::STYLE_VIRAL, self::STYLE_DISCOVER, self::STYLE_PINTEREST, self::STYLE_SOCIAL );
	}

	public static function style_label( $style ) {
		$labels = array(
			self::STYLE_SEO       => __( 'SEO',              'rankwriter-ai' ),
			self::STYLE_VIRAL     => __( 'Viral',            'rankwriter-ai' ),
			self::STYLE_DISCOVER  => __( 'Google Discover',  'rankwriter-ai' ),
			self::STYLE_PINTEREST => __( 'Pinterest',        'rankwriter-ai' ),
			self::STYLE_SOCIAL    => __( 'Social media',     'rankwriter-ai' ),
		);
		return isset( $labels[ $style ] ) ? $labels[ $style ] : ucfirst( $style );
	}

	public static function style_optimal_range( $style ) {
		// [min, max] character lengths per platform.
		$ranges = array(
			self::STYLE_SEO       => array( 50, 60 ),
			self::STYLE_VIRAL     => array( 60, 80 ),
			self::STYLE_DISCOVER  => array( 50, 70 ),
			self::STYLE_PINTEREST => array( 60, 100 ),
			self::STYLE_SOCIAL    => array( 40, 70 ),
		);
		return isset( $ranges[ $style ] ) ? $ranges[ $style ] : array( 50, 70 );
	}

	/* ============================ Power words ============================ */

	private static function power_word_groups() {
		return array(
			'curiosity'  => array(
				'secret', 'secrets', 'hidden', 'mystery', 'unknown', 'surprising', 'truth',
				'truth about', 'what nobody', 'why nobody', 'what most', 'things you',
				'the truth', 'untold', 'little-known', 'overlooked', 'forgotten',
				'reveal', 'revealed', 'discover', 'discovered',
			),
			'urgency'    => array(
				'now', 'today', 'urgent', 'fast', 'quick', 'instantly', 'instant', 'asap',
				'last chance', 'before', 'deadline', 'expires', 'running out', 'limited',
				'don\'t miss', 'this week', 'this month', 'while you can',
			),
			'trust'      => array(
				'proven', 'science', 'scientific', 'research', 'study', 'expert', 'experts',
				'official', 'certified', 'verified', 'authoritative', 'evidence-based',
				'data-driven', 'tested', 'reviewed', 'backed by',
			),
			'practical'  => array(
				'easy', 'simple', 'quick', 'beginner', 'step-by-step', 'step by step',
				'guide', 'checklist', 'blueprint', 'roadmap', 'how to', 'how-to',
				'walkthrough', 'tutorial',
			),
			'positive'   => array(
				'ultimate', 'complete', 'comprehensive', 'definitive', 'master', 'masterclass',
				'perfect', 'brilliant', 'incredible', 'amazing', 'life-changing',
				'transformative', 'powerful', 'essential', 'must-have', 'best',
			),
			'negative'   => array(
				'worst', 'terrible', 'dangerous', 'deadly', 'mistake', 'mistakes',
				'regret', 'avoid', 'never', 'stop', 'costly', 'expensive', 'fail',
				'failure', 'wrong',
			),
			'specificity' => array(
				'\d+\s*(percent|%)', '\$\d+', '\d+x', '\d+\s*(million|thousand|billion)',
				'\d+\s*(minutes?|hours?|days?|weeks?|months?|years?)',
			),
		);
	}

	/* ============================ Public API ============================ */

	public function analyze( $title ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return $this->empty_result();
		}

		$signals  = $this->signals( $title );
		$triggers = $this->detect_emotional_triggers( $title );
		$powers   = $this->find_power_words( $title );
		$click    = $this->detect_clickbait( $title );

		$platform_scores = array();
		foreach ( self::styles() as $style ) {
			$platform_scores[ $style ] = $this->score_ctr( $title, $style, $signals, $triggers, $powers, $click );
		}

		$overall = (int) round( array_sum( $platform_scores ) / max( 1, count( $platform_scores ) ) );

		return array(
			'title'              => $title,
			'length'             => $signals['length'],
			'word_count'         => $signals['word_count'],
			'signals'            => $signals,
			'emotional_triggers' => $triggers,
			'power_words'        => $powers,
			'clickbait'          => $click,
			'platform_scores'    => $platform_scores,
			'overall_score'      => $overall,
			'recommendations'    => $this->recommendations( $signals, $triggers, $powers, $click ),
		);
	}

	public function analyze_bulk( array $titles ) {
		$out = array();
		foreach ( $titles as $t ) {
			$out[] = $this->analyze( (string) $t );
		}
		return $out;
	}

	/**
	 * Claude-powered title-variant generator. Returns 3 variants per style.
	 *
	 * @param string $topic       The article topic / working title.
	 * @param string $intent      Detected search intent (optional).
	 * @param string $cpc_tier    CPC tier (optional).
	 * @param int    $count       Variants per style.
	 * @return array|WP_Error     Variants grouped by style, each with analysis.
	 */
	public function generate_variants( $topic, $intent = '', $cpc_tier = '', $count = 3 ) {
		$topic = trim( (string) $topic );
		if ( '' === $topic ) {
			return new WP_Error( 'rwai_no_topic', __( 'Topic is required.', 'rankwriter-ai' ) );
		}
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return new WP_Error( 'rwai_no_client', __( 'Claude client missing.', 'rankwriter-ai' ) );
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$count = max( 1, min( 5, (int) $count ) );

		$system = "You are a top-tier headline writer who has produced viral posts that hold up to scrutiny. You write titles that are CTR-optimized but never misleading, never violate AdSense Publisher Policies, and never use cheap clickbait patterns (\"You won't believe\", \"Doctors hate\", \"shocking truth\", \"this one trick\"). Every title must deliver on its promise.\n\n"
			. "## Output rules\n"
			. "Return ONLY valid JSON with this exact shape:\n"
			. "{\n"
			. "  \"seo\":       [\"title 1\", \"title 2\", \"title 3\"],\n"
			. "  \"viral\":     [\"...\", \"...\", \"...\"],\n"
			. "  \"discover\":  [\"...\", \"...\", \"...\"],\n"
			. "  \"pinterest\": [\"...\", \"...\", \"...\"],\n"
			. "  \"social\":    [\"...\", \"...\", \"...\"]\n"
			. "}\n\n"
			. "Each array has exactly {$count} titles. No preamble, no markdown fences.\n\n"
			. "## Per-style rules\n"
			. "- **seo**: 50-60 chars. Includes the primary search term verbatim near the front. Clear, descriptive, evergreen.\n"
			. "- **viral**: 60-80 chars. Curiosity + specificity. Strong opening hook. Numbered when possible.\n"
			. "- **discover**: 50-70 chars. Human, story-like, current. Reads like a magazine headline. No SEO stuffing.\n"
			. "- **pinterest**: 60-100 chars. Descriptive + practical. Often \"How to\", \"X Tips\", \"X Ideas\". Includes search terms.\n"
			. "- **social**: 40-70 chars. Punchy, shareable, often a question or a strong claim. Optimized for X/Threads/Facebook.\n\n"
			. "## Banned patterns (NEVER use):\n"
			. "- \"You won't believe\", \"What happened next will shock\", \"This one trick\"\n"
			. "- \"Doctors hate\", \"They don't want you to know\", \"The secret X doesn't want\"\n"
			. "- Vague pronouns: \"This\", \"It\", \"The Answer\" without an antecedent\n"
			. "- Misleading promises (\"in 24 hours\" when not realistic)\n"
			. "- Excessive exclamation marks\n"
			. "- ALL CAPS (sentence case or title case only)\n\n"
			. "Every title must be defensible: a reader who clicks should get exactly what the title promised.";

		$user_lines = array();
		$user_lines[] = 'Topic: "' . $topic . '"';
		if ( $intent ) {
			$user_lines[] = 'Search intent: ' . $intent;
		}
		if ( $cpc_tier ) {
			$user_lines[] = 'CPC tier: ' . $cpc_tier . ' (lean toward titles that match the monetization expectations of this tier — commercial language for high CPC, informational for low)';
		}
		$user_lines[] = '';
		$user_lines[] = "Generate {$count} title variants for each of the 5 styles. Return JSON only.";
		$user = implode( "\n", $user_lines );

		$text = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$variants = $this->parse_variants( $text );
		if ( empty( $variants ) ) {
			return new WP_Error( 'rwai_bad_response', __( 'Could not parse title variants from the response.', 'rankwriter-ai' ) );
		}

		// Score every variant locally with the heuristic analyzer.
		$out = array();
		foreach ( self::styles() as $style ) {
			$titles = isset( $variants[ $style ] ) ? $variants[ $style ] : array();
			$out[ $style ] = array();
			foreach ( $titles as $title ) {
				$analysis           = $this->analyze( $title );
				$analysis['primary_style'] = $style;
				$out[ $style ][]    = $analysis;
			}
		}
		return $out;
	}

	/* ============================ Heuristic helpers ============================ */

	private function signals( $title ) {
		return array(
			'length'         => strlen( $title ),
			'word_count'     => str_word_count( $title ),
			'has_number'     => (bool) preg_match( '/\d/', $title ),
			'is_listicle'    => (bool) preg_match( '/^\s*\d+\s+(ways?|tips?|reasons?|tricks?|hacks?|things|secrets|mistakes|steps|ideas|examples|signs)\b/i', $title ),
			'is_how_to'      => (bool) preg_match( '/^\s*how to\b/i', $title ),
			'is_question'    => substr( trim( $title ), -1 ) === '?',
			'has_year'       => (bool) preg_match( '/\b20\d{2}\b/', $title ),
			'has_brackets'   => (bool) preg_match( '/[\[\(]/', $title ),
			'has_caps_word'  => (bool) preg_match( '/\b[A-Z]{3,}\b/', $title ),
			'exclamation'    => substr_count( $title, '!' ),
			'uses_you'       => (bool) preg_match( '/\b(you|your|you\'re|you\'ll)\b/i', $title ),
			'starts_with_you'=> (bool) preg_match( '/^\s*(you|your)\b/i', $title ),
		);
	}

	public function detect_emotional_triggers( $title ) {
		$low      = ' ' . strtolower( $title ) . ' ';
		$triggers = array();
		$groups   = self::power_word_groups();

		// Same groupings map to broad emotional triggers.
		$map = array(
			'curiosity' => 'curiosity',
			'urgency'   => 'urgency',
			'positive'  => 'excitement',
			'negative'  => 'fear',
			'trust'     => 'authority',
		);

		foreach ( $map as $group_key => $trigger_name ) {
			if ( ! isset( $groups[ $group_key ] ) ) {
				continue;
			}
			foreach ( $groups[ $group_key ] as $needle ) {
				if ( ! is_string( $needle ) ) {
					continue;
				}
				if ( strpos( $low, ' ' . strtolower( $needle ) . ' ' ) !== false ||
					 strpos( $low, ' ' . strtolower( $needle ) ) === 0 ||
					 preg_match( '/\b' . preg_quote( $needle, '/' ) . '\b/i', $title ) ) {
					$triggers[ $trigger_name ] = true;
					break;
				}
			}
		}

		// Specificity (numbers, percentages, money) is its own trigger.
		foreach ( $groups['specificity'] as $regex ) {
			if ( preg_match( '/' . $regex . '/i', $title ) ) {
				$triggers['specificity'] = true;
				break;
			}
		}

		return array_keys( $triggers );
	}

	public function find_power_words( $title ) {
		$found  = array();
		$groups = self::power_word_groups();
		foreach ( $groups as $group => $words ) {
			if ( 'specificity' === $group ) {
				continue;
			}
			foreach ( $words as $w ) {
				if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/i', $title, $m ) ) {
					$found[] = array( 'word' => $m[0], 'group' => $group );
				}
			}
		}
		return $found;
	}

	/**
	 * AdSense-policy-aware clickbait detection. Returns the patterns hit
	 * so the UI can show the editor WHY a title is flagged.
	 */
	public function detect_clickbait( $title ) {
		$patterns = array(
			'/\byou won.?t believe\b/i'                       => 'vague-promise',
			'/\bwhat happened next\b/i'                       => 'cliffhanger',
			'/\b(this|that|the)\s+one\s+(weird\s+)?trick\b/i' => 'one-trick',
			'/\bdoctors hate\b/i'                             => 'doctors-hate',
			'/\bthey don.?t want you to know\b/i'             => 'conspiracy',
			'/\bshocking (truth|secret|fact)\b/i'             => 'shock',
			'/\bwill (shock|amaze|stun|blow your mind)\b/i'   => 'will-shock',
			'/^\s*(this|that|it)\b/i'                         => 'vague-subject',
			'/\bthe answer (will|might) surprise you\b/i'     => 'answer-surprise',
			'/\bin (just )?(24|48) hours\b/i'                 => 'unrealistic-time',
			'/\$\d+,?\d+\s+in\s+\d+\s+(days?|hours?)\b/i'     => 'income-promise',
			'/\bguaranteed\b/i'                               => 'guarantee',
		);
		$hits = array();
		foreach ( $patterns as $regex => $reason ) {
			if ( preg_match( $regex, $title, $m ) ) {
				$hits[] = array( 'reason' => $reason, 'phrase' => $m[0] );
			}
		}
		$too_many_exclam = substr_count( $title, '!' ) >= 2;
		$has_excessive_caps = (bool) preg_match( '/[A-Z]{6,}/', $title ); // 6+ consecutive uppercase chars
		if ( $too_many_exclam ) {
			$hits[] = array( 'reason' => 'excessive-exclamation', 'phrase' => '!!' );
		}
		if ( $has_excessive_caps ) {
			$hits[] = array( 'reason' => 'all-caps', 'phrase' => 'ALL CAPS' );
		}
		return array(
			'is_clickbait' => ! empty( $hits ),
			'hits'         => $hits,
			'severity'     => count( $hits ) >= 2 ? 'high' : ( count( $hits ) === 1 ? 'medium' : 'none' ),
		);
	}

	/**
	 * Predicted CTR score 0-100 for a given platform.
	 */
	public function score_ctr( $title, $platform = self::STYLE_SEO, $signals = null, $triggers = null, $power_words = null, $click = null ) {
		if ( null === $signals )    { $signals    = $this->signals( $title ); }
		if ( null === $triggers )   { $triggers   = $this->detect_emotional_triggers( $title ); }
		if ( null === $power_words ){ $power_words= $this->find_power_words( $title ); }
		if ( null === $click )      { $click      = $this->detect_clickbait( $title ); }

		$score = 50;

		// Length scoring per platform.
		list( $min, $max ) = self::style_optimal_range( $platform );
		$length = $signals['length'];
		if ( $length >= $min && $length <= $max ) {
			$score += 15;
		} elseif ( $length >= $min - 10 && $length <= $max + 10 ) {
			$score += 5;
		} elseif ( $length < $min - 15 || $length > $max + 20 ) {
			$score -= 12;
		}

		// Trigger bonuses (capped).
		$trigger_bonus = min( 12, count( $triggers ) * 4 );
		$score += $trigger_bonus;

		// Power words (capped).
		$pw_bonus = min( 10, count( $power_words ) * 2 );
		$score += $pw_bonus;

		// Format bonuses, platform-tuned.
		if ( $signals['is_listicle'] ) {
			$score += ( self::STYLE_PINTEREST === $platform || self::STYLE_VIRAL === $platform ) ? 10 : 6;
		}
		if ( $signals['is_how_to'] ) {
			$score += ( self::STYLE_PINTEREST === $platform || self::STYLE_SEO === $platform ) ? 8 : 5;
		}
		if ( $signals['is_question'] ) {
			$score += ( self::STYLE_SOCIAL === $platform || self::STYLE_DISCOVER === $platform ) ? 8 : 4;
		}
		if ( $signals['has_year'] ) {
			$score += ( self::STYLE_SEO === $platform ) ? 6 : 3;
		}
		if ( $signals['has_number'] && ! $signals['is_listicle'] ) {
			$score += 4;
		}
		if ( $signals['uses_you'] ) {
			$score += ( self::STYLE_SOCIAL === $platform || self::STYLE_PINTEREST === $platform ) ? 5 : 3;
		}
		if ( $signals['has_brackets'] ) {
			$score += ( self::STYLE_SEO === $platform ) ? 3 : 1;
		}

		// Platform-specific shaping.
		if ( self::STYLE_SEO === $platform && in_array( 'excitement', $triggers, true ) && count( $triggers ) >= 3 ) {
			$score -= 5; // too emotional for pure SEO
		}
		if ( self::STYLE_DISCOVER === $platform && count( $power_words ) === 0 ) {
			$score -= 6; // Discover rewards narrative pull
		}

		// Clickbait penalties.
		if ( ! empty( $click['is_clickbait'] ) ) {
			$score -= ( 'high' === $click['severity'] ? 30 : 15 );
		}
		if ( $signals['exclamation'] >= 2 ) {
			$score -= 8;
		}
		if ( $signals['has_caps_word'] ) {
			$score -= 4;
		}

		return max( 0, min( 100, (int) round( $score ) ) );
	}

	private function recommendations( $signals, $triggers, $power_words, $click ) {
		$tips = array();
		if ( $signals['length'] < 40 ) {
			$tips[] = __( 'Title is short — add specificity (a number, year, or qualifier).', 'rankwriter-ai' );
		}
		if ( $signals['length'] > 95 ) {
			$tips[] = __( 'Title is long — Google may truncate it. Aim for under 70 characters for the SEO version.', 'rankwriter-ai' );
		}
		if ( empty( $triggers ) ) {
			$tips[] = __( 'No emotional triggers detected — consider adding a curiosity, trust, or specificity hook.', 'rankwriter-ai' );
		}
		if ( ! $signals['has_number'] ) {
			$tips[] = __( 'No number in the title — specific stats and listicle counts boost CTR.', 'rankwriter-ai' );
		}
		if ( ! $signals['uses_you'] && ! $signals['is_how_to'] ) {
			$tips[] = __( 'Consider direct address ("you" / "your") for engagement.', 'rankwriter-ai' );
		}
		if ( ! empty( $click['is_clickbait'] ) ) {
			$tips[] = __( 'Clickbait pattern detected — risky for AdSense and trust. Rewrite to deliver on the promise.', 'rankwriter-ai' );
		}
		if ( $signals['exclamation'] >= 2 ) {
			$tips[] = __( 'Multiple exclamation marks — drop them. Punctuation overuse hurts perceived trust.', 'rankwriter-ai' );
		}
		if ( $signals['has_caps_word'] ) {
			$tips[] = __( 'ALL CAPS word(s) — use title case or sentence case instead.', 'rankwriter-ai' );
		}
		return $tips;
	}

	private function empty_result() {
		return array(
			'title'              => '',
			'length'             => 0,
			'word_count'         => 0,
			'signals'            => array(),
			'emotional_triggers' => array(),
			'power_words'        => array(),
			'clickbait'          => array( 'is_clickbait' => false, 'hits' => array(), 'severity' => 'none' ),
			'platform_scores'    => array(),
			'overall_score'      => 0,
			'recommendations'    => array(),
		);
	}

	private function parse_variants( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$json = json_decode( $text, true );
		if ( ! is_array( $json ) ) {
			$first = strpos( $text, '{' );
			$last  = strrpos( $text, '}' );
			if ( false !== $first && false !== $last && $last > $first ) {
				$json = json_decode( substr( $text, $first, $last - $first + 1 ), true );
			}
		}
		if ( ! is_array( $json ) ) {
			return array();
		}
		$out = array();
		foreach ( self::styles() as $style ) {
			if ( ! empty( $json[ $style ] ) && is_array( $json[ $style ] ) ) {
				$cleaned = array();
				foreach ( $json[ $style ] as $t ) {
					if ( is_string( $t ) ) {
						$t = trim( $t );
						if ( '' !== $t ) {
							$cleaned[] = sanitize_text_field( $t );
						}
					}
				}
				$out[ $style ] = $cleaned;
			}
		}
		return $out;
	}
}
