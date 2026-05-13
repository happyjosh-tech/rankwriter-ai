<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Humanization Engine.
 *
 *   humanize( $html, $options )      → rewritten HTML (Claude call)
 *   analyze_ai_tells( $html )        → heuristic 0-100 AI-detection score
 *                                      + per-pattern hit list (pure PHP,
 *                                      no API)
 *   strengths()                      → 3 strength configs
 *   tones()                          → 6 tone configs
 *   personalities()                  → 6 persona configs
 *
 * Design:
 *   - The Claude pass is opt-in and configurable. The heuristic analyzer
 *     is always cheap to run — used to score posts BEFORE deciding whether
 *     to spend an API call.
 *   - Every transformation rule (banned phrases, generic adjectives,
 *     hedging stacks, structural tells) is centralized in pattern_library()
 *     so both the analyzer and the prompt builder share the same source
 *     of truth.
 *   - Preservation rules are non-negotiable: facts, numbers, names,
 *     HTML tags, hrefs, FAQ structure all stay exactly as the source.
 */
class RankWriter_AI_Humanizer {

	const STRENGTH_LIGHT      = 'light';
	const STRENGTH_MEDIUM     = 'medium';
	const STRENGTH_AGGRESSIVE = 'aggressive';

	const TONE_PROFESSIONAL   = 'professional';
	const TONE_CONVERSATIONAL = 'conversational';
	const TONE_EDUCATIONAL    = 'educational';
	const TONE_EMOTIONAL      = 'emotional';
	const TONE_AUTHORITATIVE  = 'authoritative';
	const TONE_STORYTELLING   = 'storytelling';

	const READABILITY_OFF    = 'off';
	const READABILITY_LIGHT  = 'light';
	const READABILITY_STRONG = 'strong';

	/* ============================ Config tables ============================ */

	public static function strengths() {
		return array(
			self::STRENGTH_LIGHT => array(
				'label'      => __( 'Light — surface scrub', 'rankwriter-ai' ),
				'directive'  => 'Touch up obvious AI tells only. Preserve original phrasing where it already reads naturally. Rewrite at most 20-30% of sentences.',
				'rewrite_pct'=> 25,
			),
			self::STRENGTH_MEDIUM => array(
				'label'      => __( 'Medium — voice rewrite', 'rankwriter-ai' ),
				'directive'  => 'Rewrite roughly half the sentences. Vary structure aggressively, inject the persona voice, fix every pattern in the banned list, replace generic adjectives with concrete ones.',
				'rewrite_pct'=> 50,
			),
			self::STRENGTH_AGGRESSIVE => array(
				'label'      => __( 'Aggressive — full rebuild', 'rankwriter-ai' ),
				'directive'  => 'Rewrite every sentence. Restructure paragraphs for natural rhythm. Add rhetorical questions, occasional one-liner paragraphs, conversational asides where they fit the tone. Make it feel like the persona wrote it from scratch given the same facts.',
				'rewrite_pct'=> 95,
			),
		);
	}

	public static function tones() {
		return array(
			self::TONE_PROFESSIONAL => array(
				'label'    => __( 'Professional', 'rankwriter-ai' ),
				'guidance' => "Clear, authoritative, business-appropriate. Use precise language but don't sound stiff. Limited contractions. Industry-specific terms only when they earn their place. No casual slang.",
			),
			self::TONE_CONVERSATIONAL => array(
				'label'    => __( 'Conversational', 'rankwriter-ai' ),
				'guidance' => "Casual, direct, like talking to a smart friend over coffee. Heavy use of contractions and second-person. Rhetorical questions every few paragraphs. Sentence fragments are fine for emphasis. Light humor where it fits.",
			),
			self::TONE_EDUCATIONAL => array(
				'label'    => __( 'Educational', 'rankwriter-ai' ),
				'guidance' => "Patient explainer voice. Scaffold concepts: define first, then expand. Use analogies. Anticipate the reader's confusion (\"You might be wondering...\"). Avoid jargon unless defined inline.",
			),
			self::TONE_EMOTIONAL => array(
				'label'    => __( 'Emotional', 'rankwriter-ai' ),
				'guidance' => "Story-driven, empathetic, sensory. Use concrete imagery. Build an emotional arc — tension, release, payoff. Address the reader's underlying feeling (frustration, hope, fear) without naming it explicitly.",
			),
			self::TONE_AUTHORITATIVE => array(
				'label'    => __( 'Authoritative', 'rankwriter-ai' ),
				'guidance' => "Confident, opinionated, takes a stance. \"Most guides get this wrong.\" \"I think X — here's why.\" Cite specifics, name names, pick sides on debated points. Never both-sides every issue.",
			),
			self::TONE_STORYTELLING => array(
				'label'    => __( 'Storytelling', 'rankwriter-ai' ),
				'guidance' => "Narrative arc with characters and scenes. Show, don't tell. Lean on anecdotes — a specific person at a specific moment doing a specific thing. Pull the reader through with cause-and-effect, not bullet lists.",
			),
		);
	}

	public static function personalities() {
		return array(
			'experienced_practitioner' => array(
				'label'    => __( 'Experienced practitioner', 'rankwriter-ai' ),
				'guidance' => "A writer who has been DOING this for 10+ years. Has scars from things that went wrong. Comfortable saying \"the common advice is wrong because…\" and citing real cases. Speaks from earned experience, not theory.",
			),
			'investigative_journalist' => array(
				'label'    => __( 'Investigative journalist', 'rankwriter-ai' ),
				'guidance' => "Skeptical, follows the money, asks the hard questions. Cites sources inline (\"according to X's 2025 report\"). Doesn't take official claims at face value. Names specific companies and people.",
			),
			'first_person_blogger' => array(
				'label'    => __( 'First-person blogger', 'rankwriter-ai' ),
				'guidance' => "Personal, anecdote-led, openly opinionated. Uses \"I\" freely. Shares small specific stories from their own life. Acknowledges biases.",
			),
			'industry_expert' => array(
				'label'    => __( 'Industry expert', 'rankwriter-ai' ),
				'guidance' => "Data-driven, names trends and metrics, references industry reports. Uses precise terminology but defines anything readers won't know. Quietly confident, never showy.",
			),
			'friendly_coach' => array(
				'label'    => __( 'Friendly coach', 'rankwriter-ai' ),
				'guidance' => "Encouraging, action-oriented, second-person. \"Here's what I want you to do this week.\" Breaks big things into small steps. Never condescending.",
			),
			'skeptical_reviewer' => array(
				'label'    => __( 'Skeptical reviewer', 'rankwriter-ai' ),
				'guidance' => "Honest about flaws. Compares to alternatives by name. Lists what an option is NOT good for. Earns trust by being willing to say a popular thing is overrated.",
			),
		);
	}

	public static function readability_modes() {
		return array(
			self::READABILITY_OFF    => array( 'label' => __( 'Off',    'rankwriter-ai' ), 'guidance' => '' ),
			self::READABILITY_LIGHT  => array(
				'label' => __( 'Light', 'rankwriter-ai' ),
				'guidance' => "Aim for an 8th-grade Flesch reading level. Short paragraphs (under 80 words). Replace 3-syllable words with 1-2 syllable alternatives where meaning is preserved.",
			),
			self::READABILITY_STRONG => array(
				'label' => __( 'Strong', 'rankwriter-ai' ),
				'guidance' => "Aim for a 6th-grade Flesch reading level. Paragraphs under 50 words. Average sentence length under 15 words. Strip every adverb that doesn't add information. Use transition words at the start of every major paragraph.",
			),
		);
	}

	/**
	 * Centralized pattern library. The analyzer scores hits here; the prompt
	 * embeds the same list as banned phrases. Single source of truth.
	 */
	public static function pattern_library() {
		return array(
			'opener_phrases' => array(
				'/\bin today\'?s (fast-paced |digital |competitive |interconnected )?(world|age|landscape|society)\b/i',
				'/\bin the world of \w+/i',
				'/\bwith \w+ becoming (more|increasingly) (popular|important|common)/i',
				'/\bhave you ever wondered\b/i',
				'/\bare you tired of\b/i',
			),
			'filler_phrases' => array(
				'/\bit (is|\'s) (important|worth|crucial|essential|vital) to note that\b/i',
				'/\bit (goes|should go) without saying\b/i',
				'/\bit (is|\'s) no secret that\b/i',
				'/\b(furthermore|moreover|in conclusion|in summary|to sum up|all in all)\b/i',
				'/\b(when it comes to)\b/i',
				'/\b(needless to say|that being said)\b/i',
			),
			'flowery_filler' => array(
				'/\b(tapestry|plethora|myriad|wealth|vast array|abundance) of\b/i',
				'/\b(delve|dive deep|navigate the complexities|journey through) into\b/i',
				'/\bgame[- ]changer\b/i',
				'/\bparadigm shift\b/i',
				'/\bunlock the (power|potential|secret) of\b/i',
				'/\bharness the (power|potential) of\b/i',
			),
			'generic_adjectives' => array(
				'/\b(robust|comprehensive|cutting[- ]edge|state[- ]of[- ]the[- ]art|innovative|revolutionary|seamless|powerful|crucial|vital|essential|pivotal)\b/i',
			),
			'hedging_stacks' => array(
				'/\b(may|might|could) (potentially|possibly) (be|help|provide|offer)\b/i',
				'/\bsome(times|what) (may|might|could|can)\b/i',
			),
			'clickbait_tells' => array(
				'/\byou won.?t believe\b/i',
				'/\bwhat happened next\b/i',
				'/\bdoctors hate\b/i',
				'/\bthey don.?t want you to know\b/i',
				'/\bshocking (truth|secret|fact)\b/i',
				'/\bthe answer (will|might) surprise you\b/i',
			),
			'ai_self_reference' => array(
				'/\bas an ai (language model|assistant)\b/i',
				'/\bi cannot provide\b/i',
				'/\bi don\'?t have access to real[- ]time\b/i',
			),
		);
	}

	/* ============================ Heuristic analyzer ============================ */

	/**
	 * Detect AI-writing patterns in the content. Pure PHP, instant.
	 *
	 * Score is 0-100 where 100 = perfectly human-like:
	 *   100 - 4*(banned_phrase_hits) - paragraph_uniformity_penalty
	 *       - sentence_uniformity_penalty - missing_contractions_penalty
	 *       - missing_questions_penalty
	 */
	public function analyze_ai_tells( $html ) {
		$plain = wp_strip_all_tags( (string) $html );
		$plain = preg_replace( '/\s+/', ' ', $plain );
		$plain = trim( $plain );
		if ( '' === $plain ) {
			return $this->empty_analysis();
		}

		$word_count = str_word_count( $plain );
		$hits       = array();
		$total_hits = 0;
		foreach ( self::pattern_library() as $group => $patterns ) {
			$group_hits = array();
			foreach ( $patterns as $regex ) {
				if ( preg_match_all( $regex, $plain, $m ) ) {
					foreach ( $m[0] as $match ) {
						$group_hits[] = $match;
					}
				}
			}
			if ( ! empty( $group_hits ) ) {
				$hits[ $group ] = array_values( array_unique( $group_hits ) );
				$total_hits    += count( $group_hits );
			}
		}

		// Paragraph variance (if all paragraphs are similar length, it reads robotic).
		preg_match_all( '#<p\b[^>]*>(.+?)</p>#is', $html, $pmatches );
		$para_lengths = array();
		foreach ( (array) ( $pmatches[1] ?? array() ) as $p ) {
			$wc = str_word_count( wp_strip_all_tags( $p ) );
			if ( $wc > 0 ) {
				$para_lengths[] = $wc;
			}
		}
		$para_variance_penalty = 0;
		$avg_para = 0;
		$para_stddev = 0;
		if ( count( $para_lengths ) >= 3 ) {
			$avg_para  = array_sum( $para_lengths ) / count( $para_lengths );
			$variance  = 0;
			foreach ( $para_lengths as $l ) {
				$variance += ( $l - $avg_para ) ** 2;
			}
			$para_stddev = sqrt( $variance / count( $para_lengths ) );
			// stddev / mean — if very low, paragraphs are uniformly sized.
			$cv = $avg_para > 0 ? $para_stddev / $avg_para : 0;
			if ( $cv < 0.3 ) {
				$para_variance_penalty = 8;
			} elseif ( $cv < 0.5 ) {
				$para_variance_penalty = 4;
			}
		}

		// Sentence variance.
		$sentences = preg_split( '/(?<=[.!?])\s+/', $plain );
		$sent_lengths = array();
		foreach ( (array) $sentences as $s ) {
			$w = str_word_count( $s );
			if ( $w > 0 ) {
				$sent_lengths[] = $w;
			}
		}
		$sent_variance_penalty = 0;
		$avg_sentence = 0;
		if ( count( $sent_lengths ) >= 6 ) {
			$avg_sentence = array_sum( $sent_lengths ) / count( $sent_lengths );
			$variance     = 0;
			foreach ( $sent_lengths as $l ) {
				$variance += ( $l - $avg_sentence ) ** 2;
			}
			$sd = sqrt( $variance / count( $sent_lengths ) );
			$cv = $avg_sentence > 0 ? $sd / $avg_sentence : 0;
			if ( $cv < 0.35 ) {
				$sent_variance_penalty = 8;
			} elseif ( $cv < 0.55 ) {
				$sent_variance_penalty = 4;
			}
		}

		// Contractions check: less than 1 contraction per 200 words → flagged.
		$contraction_count = preg_match_all( "/\b\w+'(s|t|re|ll|ve|d|m)\b/i", $plain );
		$contractions_per_200 = $word_count > 0 ? ( $contraction_count / ( $word_count / 200 ) ) : 0;
		$contraction_penalty = 0;
		if ( $contractions_per_200 < 0.5 ) {
			$contraction_penalty = 6;
		} elseif ( $contractions_per_200 < 1.0 ) {
			$contraction_penalty = 3;
		}

		// Rhetorical questions — at least 1 per 600 words for engagement.
		$question_count = substr_count( $plain, '?' );
		$questions_per_600 = $word_count > 0 ? ( $question_count / ( $word_count / 600 ) ) : 0;
		$question_penalty = 0;
		if ( $word_count >= 800 && $questions_per_600 < 0.4 ) {
			$question_penalty = 4;
		}

		// Compose score.
		$score = 100
			- min( 60, $total_hits * 4 )
			- $para_variance_penalty
			- $sent_variance_penalty
			- $contraction_penalty
			- $question_penalty;
		$score = max( 0, min( 100, (int) round( $score ) ) );

		$band = $score >= 75 ? 'ok' : ( $score >= 50 ? 'warn' : 'bad' );

		return array(
			'score'               => $score,
			'band'                => $band,
			'word_count'          => $word_count,
			'total_pattern_hits'  => $total_hits,
			'hits_by_group'       => $hits,
			'paragraph_count'     => count( $para_lengths ),
			'avg_paragraph_words' => (int) round( $avg_para ),
			'paragraph_stddev'    => (int) round( $para_stddev ),
			'sentence_count'      => count( $sent_lengths ),
			'avg_sentence_words'  => (int) round( $avg_sentence ),
			'contractions_per_200'=> round( $contractions_per_200, 2 ),
			'questions_per_600'   => round( $questions_per_600, 2 ),
			'penalties'           => array(
				'pattern_hits'      => min( 60, $total_hits * 4 ),
				'paragraph_uniform' => $para_variance_penalty,
				'sentence_uniform'  => $sent_variance_penalty,
				'missing_contractions' => $contraction_penalty,
				'missing_questions' => $question_penalty,
			),
		);
	}

	private function empty_analysis() {
		return array(
			'score'              => 0,
			'band'               => 'bad',
			'word_count'         => 0,
			'total_pattern_hits' => 0,
			'hits_by_group'      => array(),
			'paragraph_count'    => 0,
			'avg_paragraph_words'=> 0,
			'paragraph_stddev'   => 0,
			'sentence_count'     => 0,
			'avg_sentence_words' => 0,
			'contractions_per_200' => 0,
			'questions_per_600'  => 0,
			'penalties'          => array(),
		);
	}

	/* ============================ Claude rewrite pass ============================ */

	/**
	 * Rewrite content using the configured strength + tone + persona.
	 *
	 * @param string $html
	 * @param array  $options {
	 *     @type string $strength    light|medium|aggressive
	 *     @type string $tone        one of TONE_*
	 *     @type string $personality persona key
	 *     @type string $readability off|light|strong
	 *     @type string $topic       Optional context for the editor
	 *     @type string $niche       Optional niche context
	 *     @type string $banned_terms Optional banned-term CSV (passed through)
	 * }
	 * @return string|null Rewritten HTML on success, null on failure.
	 */
	public function humanize( $html, array $options = array() ) {
		$html = (string) $html;
		if ( strlen( $html ) < 200 ) {
			return null;
		}
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return null;
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return null;
		}

		$strength_key    = self::normalize( $options['strength']    ?? self::STRENGTH_MEDIUM, self::strengths(),     self::STRENGTH_MEDIUM );
		$tone_key        = self::normalize( $options['tone']        ?? self::TONE_PROFESSIONAL, self::tones(),       self::TONE_PROFESSIONAL );
		$persona_key     = self::normalize( $options['personality'] ?? 'experienced_practitioner', self::personalities(), 'experienced_practitioner' );
		$readability_key = self::normalize( $options['readability'] ?? self::READABILITY_OFF, self::readability_modes(), self::READABILITY_OFF );

		$strength = self::strengths()[ $strength_key ];
		$tone     = self::tones()[ $tone_key ];
		$persona  = self::personalities()[ $persona_key ];
		$readability = self::readability_modes()[ $readability_key ];

		$topic   = isset( $options['topic'] ) ? sanitize_text_field( $options['topic'] ) : '';
		$niche   = isset( $options['niche'] ) ? sanitize_text_field( $options['niche'] ) : '';
		$banned  = isset( $options['banned_terms'] ) ? trim( (string) $options['banned_terms'] ) : '';

		$system = $this->build_system_prompt( $strength, $tone, $persona, $readability, $banned );
		$user   = $this->build_user_prompt( $html, $topic, $niche );

		$result = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $result ) || empty( $result ) ) {
			return null;
		}

		$result = trim( (string) $result );
		// Strip stray code fences.
		if ( 0 === strpos( $result, '```' ) ) {
			$result = preg_replace( '/^```(?:html)?\s*/i', '', $result );
			$result = preg_replace( '/\s*```$/', '', $result );
			$result = trim( $result );
		}

		// Sanity guards: must contain HTML, must not have shrunk dramatically.
		if ( false === strpos( $result, '<' ) || strlen( $result ) < ( strlen( $html ) * 0.4 ) ) {
			return null;
		}

		// Normalize leaked escape sequences (same defense as content generator).
		$result = str_replace(
			array( "\\n", "\\t", "\\r", '\\"', "\\'" ),
			array( "\n",  "\t",  "\r",  '"',   "'" ),
			$result
		);

		return wp_kses_post( $result );
	}

	private function build_system_prompt( $strength, $tone, $persona, $readability, $banned_terms ) {
		$banned_block = '';
		foreach ( self::pattern_library() as $group => $patterns ) {
			$banned_block .= "- " . self::pretty_group_name( $group ) . ":\n";
			foreach ( $patterns as $regex ) {
				// Strip regex chars to make it human-readable.
				$pretty = preg_replace( '/[\\\\\(\)\?:|\[\]\^\$\*\+]/', '', $regex );
				$pretty = trim( preg_replace( '#[/]#', '', $pretty ) );
				$pretty = preg_replace( '/\\\\b/', '', $pretty );
				if ( strlen( $pretty ) > 0 ) {
					$banned_block .= '    • ' . $pretty . "\n";
				}
			}
		}

		$readability_block = '';
		if ( ! empty( $readability['guidance'] ) ) {
			$readability_block = "## Readability mode: " . $readability['label'] . "\n" . $readability['guidance'] . "\n\n";
		}

		$banned_user_terms = '';
		if ( '' !== $banned_terms ) {
			$banned_user_terms = "## Additionally banned terms from the category profile (never use):\n" . $banned_terms . "\n\n";
		}

		return "You are a senior editor at a top-tier publication who specializes in stripping AI tells from drafts and rewriting them with a real human voice. Your only job is to take the article below and rewrite it so a critical reader on Twitter or Reddit could NOT tell it was AI-generated. You preserve every fact, number, name, date, dollar amount, and every HTML tag exactly — you only rewrite prose.\n\n"
			. "## Strength: " . $strength['label'] . "\n"
			. $strength['directive'] . "\n\n"
			. "## Tone: " . $tone['label'] . "\n"
			. $tone['guidance'] . "\n\n"
			. "## Persona: " . $persona['label'] . "\n"
			. $persona['guidance'] . "\n\n"
			. $readability_block
			. $banned_user_terms
			. "## Required transformations\n"
			. "- Vary sentence length aggressively: mix 3-word sentences with 25-word sentences. Use fragments. For rhythm.\n"
			. "- Vary paragraph length: some 1-2 sentences, some 4-6.\n"
			. "- Add 2-3 rhetorical questions per ~1000 words — but only ones a real reader would actually ask.\n"
			. "- Use contractions naturally: it's, don't, you'll, can't, won't, you're.\n"
			. "- Replace generic adjectives with concrete ones. \"Powerful tool\" → \"saved me 4 hours a week\". \"Comprehensive guide\" → cite the specific sections.\n"
			. "- Address the reader's objection inline where the topic warrants it.\n"
			. "- Use natural transitions instead of \"Furthermore\" / \"Moreover\" / \"In conclusion\".\n"
			. "- Cut hedging stacks (\"may potentially possibly\").\n"
			. "- One-line paragraphs are allowed and encouraged for emphasis.\n"
			. "- Open the article with a concrete situation, number, or named entity — NEVER with generic stage-setting.\n\n"
			. "## Banned phrasings (delete on sight; if any appears in the draft, rewrite the sentence)\n"
			. $banned_block . "\n"
			. "## Preservation rules — non-negotiable\n"
			. "- Every fact, number, name, dollar amount, date, percentage, age, deadline stays exactly as written. Do NOT invent new numbers or attribute new claims.\n"
			. "- Every HTML tag and attribute is preserved: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a href=\"...\">, <table>, <img>, etc.\n"
			. "- Every <a href> URL is preserved verbatim — only the anchor text inside the tag may be rewritten.\n"
			. "- FAQ Q&A pairs preserved as Q&A pairs (rewrite the prose, keep the structure).\n"
			. "- Section ordering preserved. You may rewrite heading TEXT, but not reorder sections.\n"
			. "- SEO keywords still appear naturally somewhere in each section they were in.\n\n"
			. "## Output\n"
			. "Return ONLY the rewritten HTML body. No JSON wrapper. No preamble like \"Here's the rewritten article:\". No markdown code fences. Just clean HTML.";
	}

	private function build_user_prompt( $html, $topic, $niche ) {
		$lines = array();
		if ( $topic ) { $lines[] = 'Article topic: ' . $topic; }
		if ( $niche ) { $lines[] = 'Niche: ' . $niche; }
		$lines[] = '';
		$lines[] = 'Rewrite this draft for human voice. Return only HTML.';
		$lines[] = '';
		$lines[] = '--- DRAFT ---';
		$lines[] = '';
		$lines[] = $html;
		return implode( "\n", $lines );
	}

	private static function pretty_group_name( $group ) {
		$map = array(
			'opener_phrases'      => 'Generic openers',
			'filler_phrases'      => 'Filler phrases',
			'flowery_filler'      => 'Flowery filler',
			'generic_adjectives'  => 'Generic adjectives without justification',
			'hedging_stacks'      => 'Hedging stacks',
			'clickbait_tells'     => 'Clickbait phrases',
			'ai_self_reference'   => 'AI self-reference',
		);
		return $map[ $group ] ?? ucwords( str_replace( '_', ' ', $group ) );
	}

	private static function normalize( $value, array $valid, $default ) {
		$value = strtolower( (string) $value );
		return isset( $valid[ $value ] ) ? $value : $default;
	}

	/* ============================ Settings helpers ============================ */

	public static function default_options() {
		return array(
			'strength'    => (string) RankWriter_AI_Helpers::get_setting( 'humanize_strength', self::STRENGTH_MEDIUM ),
			'tone'        => (string) RankWriter_AI_Helpers::get_setting( 'humanize_tone', self::TONE_PROFESSIONAL ),
			'personality' => (string) RankWriter_AI_Helpers::get_setting( 'humanize_personality', 'experienced_practitioner' ),
			'readability' => (string) RankWriter_AI_Helpers::get_setting( 'humanize_readability', self::READABILITY_OFF ),
		);
	}
}
