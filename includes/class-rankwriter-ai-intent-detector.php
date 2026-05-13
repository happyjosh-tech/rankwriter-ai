<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-keyword Search Intent Detector.
 *
 *   detect( $keyword )      → { primary, confidence, scores, matched, label, template }
 *   detect_bulk( $list )    → array of detections
 *   group_by_intent( $list) → array keyed by intent → [detections]
 *
 * Strategy:
 *   1. Heuristic-first  → regex-based scoring against ~40 well-known search-
 *      intent signals. Runs in microseconds, no API call, results cached as
 *      transients keyed by keyword hash.
 *   2. Claude fallback  → opt-in. When `detect_with_ai()` is called AND the
 *      heuristic confidence is below `$min_confidence`, Claude breaks the
 *      tie and the result is cached.
 *
 * Each detected intent maps to a "template" — article structure, CTA
 * placement, monetization emphasis, headline style, schema type, and
 * recommended word count — which the content generator injects into its
 * Claude prompt to shape the article shape per intent.
 */
class RankWriter_AI_Intent_Detector {

	const INTENT_INFO  = 'informational';
	const INTENT_COMM  = 'commercial';
	const INTENT_TRANS = 'transactional';
	const INTENT_NAV   = 'navigational';

	const TRANSIENT_PREFIX = 'rwai_intent_';
	const CACHE_TTL        = WEEK_IN_SECONDS;
	const AI_MIN_CONFIDENCE = 55;

	public static function all_intents() {
		return array( self::INTENT_INFO, self::INTENT_COMM, self::INTENT_TRANS, self::INTENT_NAV );
	}

	/**
	 * Master pattern table. Each entry: [ regex, weight ].
	 *
	 * Patterns are roughly ordered strongest → weakest within each intent
	 * so the highest-weight match wins early when patterns overlap.
	 */
	private static function patterns() {
		return array(

			self::INTENT_INFO => array(
				// Question words at the start of a query — strongest signal.
				array( '/^\s*(what|why|how|when|where|who|which|is|are|can|does|do|should|will|could)\b/i', 3 ),
				array( '/\bhow to\b/i', 4 ),
				array( '/\b(guide|tutorial|explained|meaning|definition|overview)\b/i', 3 ),
				array( '/\b(history of|types of|examples of|facts about|reasons why|benefits of)\b/i', 2 ),
				array( '/\?\s*$/', 2 ),
				array( '/\b(learn|understand|introduction)\b/i', 2 ),
				array( '/\bstep[- ]by[- ]step\b/i', 2 ),
				array( '/\b(difference between|vs\.?)\b/i', 1 ),
			),

			self::INTENT_COMM => array(
				array( '/\bbest\b/i', 4 ),
				array( '/\btop\s*\d+\b/i', 4 ),
				array( '/\b(review|reviews)\b/i', 3 ),
				array( '/\b(versus|vs\.?)\b/i', 2 ),
				array( '/\b(compare|comparison|comparisons)\b/i', 3 ),
				array( '/\balternatives?\s+to\b/i', 3 ),
				array( '/\bpros and cons\b/i', 3 ),
				array( '/\bshould i (buy|get|choose|use|pick)\b/i', 4 ),
				array( '/\bis .{1,30} worth it\b/i', 3 ),
				array( '/\b(rated|ranking|ranked)\b/i', 2 ),
				array( '/\b(top|best) (laptops?|phones?|cameras?|headphones?|cars?|insurance|tools?)\b/i', 4 ),
			),

			self::INTENT_TRANS => array(
				// Action verbs at the START of a query — strongest transactional signal.
				array( '/^\s*(buy|get|download|apply|sign[- ]?up|subscribe|order|purchase|register|join|claim|book|reserve|hire)\b/i', 4 ),
				array( '/\bbuy .{1,40} online\b/i', 4 ),
				array( '/\b(coupon|discount|promo code|deal|sale|offer)\b/i', 3 ),
				array( '/\bnear me\b/i', 4 ),
				array( '/\bfor sale\b/i', 3 ),
				array( '/\bhow much .{1,40} cost\b/i', 2 ),
				array( '/\b(price|pricing)\b/i', 2 ),
				array( '/\b(cheap|affordable|free)\s+\w+/i', 2 ),
				array( '/\bapplication form\b/i', 3 ),
				array( '/\bregistration\b/i', 2 ),
				array( '/\bin\s+\d{4}\b/i', 1 ), // "buy X in 2026" tilts transactional
			),

			self::INTENT_NAV => array(
				array( '/\b(login|log in|sign in|signin)\b/i', 4 ),
				array( '/\b(official site|official website|homepage)\b/i', 4 ),
				array( '/\b(customer service|customer support|contact us|help center)\b/i', 4 ),
				array( '/\b(refund|return|cancel subscription|account settings|password reset)\b/i', 3 ),
				array( '/\b(app store|play store)\b/i', 2 ),
				array( '/\b(youtube|facebook|twitter|instagram|linkedin|tiktok|reddit)\b/i', 1 ),
				array( '/\b(near me|in [A-Z][a-z]+)\b/', 1 ), // "X near me" / "X in London"
			),
		);
	}

	/**
	 * @param string $keyword
	 * @return array Result row.
	 */
	public function detect( $keyword ) {
		$keyword = trim( (string) $keyword );
		if ( '' === $keyword ) {
			return $this->empty_result();
		}

		$cache_key = self::TRANSIENT_PREFIX . md5( strtolower( $keyword ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['primary'] ) ) {
			return $cached;
		}

		$result = $this->score( $keyword );

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Heuristic + optional Claude tiebreak. Use when the calling context
	 * tolerates an API roundtrip (e.g. one-off article generation).
	 *
	 * @param string $keyword
	 * @param int    $min_confidence Below this percent, ask Claude.
	 * @return array
	 */
	public function detect_with_ai( $keyword, $min_confidence = self::AI_MIN_CONFIDENCE ) {
		$result = $this->detect( $keyword );
		if ( (int) $result['confidence'] >= (int) $min_confidence ) {
			return $result;
		}
		$ai = $this->ai_resolve( $keyword, $result );
		if ( is_array( $ai ) ) {
			$cache_key = self::TRANSIENT_PREFIX . md5( strtolower( $keyword ) );
			set_transient( $cache_key, $ai, self::CACHE_TTL );
			return $ai;
		}
		return $result;
	}

	public function detect_bulk( array $keywords ) {
		$out = array();
		foreach ( $keywords as $kw ) {
			if ( is_array( $kw ) && isset( $kw['keyword'] ) ) {
				$kw = $kw['keyword'];
			}
			$out[] = $this->detect( (string) $kw );
		}
		return $out;
	}

	public function group_by_intent( array $keywords ) {
		$groups = array(
			self::INTENT_INFO  => array(),
			self::INTENT_COMM  => array(),
			self::INTENT_TRANS => array(),
			self::INTENT_NAV   => array(),
		);
		foreach ( $this->detect_bulk( $keywords ) as $r ) {
			$groups[ $r['primary'] ][] = $r;
		}
		return $groups;
	}

	private function score( $keyword ) {
		$scores = array(
			self::INTENT_INFO  => 0,
			self::INTENT_COMM  => 0,
			self::INTENT_TRANS => 0,
			self::INTENT_NAV   => 0,
		);
		$matched = array();

		foreach ( self::patterns() as $intent => $rules ) {
			foreach ( $rules as $rule ) {
				list( $regex, $weight ) = $rule;
				if ( preg_match( $regex, $keyword, $m ) ) {
					$scores[ $intent ] += $weight;
					$matched[]          = array(
						'intent'  => $intent,
						'pattern' => $regex,
						'matched' => $m[0],
						'weight'  => $weight,
					);
				}
			}
		}

		$total = array_sum( $scores );
		if ( 0 === $total ) {
			// No signals → default to informational with low confidence rather
			// than wrongly guessing navigational. Most ambiguous queries are
			// information-seeking.
			$scores[ self::INTENT_INFO ] = 1;
			$primary    = self::INTENT_INFO;
			$confidence = 25;
		} else {
			arsort( $scores );
			$primary    = key( $scores );
			$top_score  = reset( $scores );
			$confidence = (int) round( ( $top_score / $total ) * 100 );
			if ( $top_score >= 7 ) {
				$confidence = min( 100, $confidence + 10 );
			} elseif ( $top_score >= 4 ) {
				$confidence = min( 100, $confidence + 5 );
			}
		}

		return array(
			'keyword'    => $keyword,
			'primary'    => $primary,
			'confidence' => $confidence,
			'scores'     => $scores,
			'matched'    => $matched,
			'label'      => self::label( $primary ),
			'template'   => self::template_for( $primary ),
		);
	}

	private function ai_resolve( $keyword, $heuristic ) {
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return null;
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return null;
		}
		$system = "You classify the search intent of a single query into one of: informational, commercial, transactional, navigational. Respond with ONLY a JSON object: {\"intent\":\"...\",\"confidence\":INT 0-100,\"reason\":\"<one short sentence>\"}. No preamble, no markdown.";
		$user   = "Query: \"$keyword\"\n\nClassify the dominant search intent. If the searcher wants to learn → informational. If comparing options to buy later → commercial. If ready to act / buy / apply now → transactional. If navigating to a known site / brand → navigational.";

		$text = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return null;
		}
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$json = json_decode( $text, true );
		if ( ! is_array( $json ) || empty( $json['intent'] ) ) {
			return null;
		}
		$intent = strtolower( trim( $json['intent'] ) );
		if ( ! in_array( $intent, self::all_intents(), true ) ) {
			return null;
		}
		$confidence = isset( $json['confidence'] ) ? max( 0, min( 100, (int) $json['confidence'] ) ) : 75;

		$heuristic['primary']     = $intent;
		$heuristic['confidence']  = $confidence;
		$heuristic['label']       = self::label( $intent );
		$heuristic['template']    = self::template_for( $intent );
		$heuristic['ai_resolved'] = true;
		$heuristic['ai_reason']   = isset( $json['reason'] ) ? sanitize_text_field( $json['reason'] ) : '';
		return $heuristic;
	}

	private function empty_result() {
		return array(
			'keyword'    => '',
			'primary'    => self::INTENT_INFO,
			'confidence' => 0,
			'scores'     => array(),
			'matched'    => array(),
			'label'      => __( 'Unknown', 'rankwriter-ai' ),
			'template'   => self::template_for( self::INTENT_INFO ),
		);
	}

	public static function label( $intent ) {
		$labels = array(
			self::INTENT_INFO  => __( 'Informational', 'rankwriter-ai' ),
			self::INTENT_COMM  => __( 'Commercial',    'rankwriter-ai' ),
			self::INTENT_TRANS => __( 'Transactional', 'rankwriter-ai' ),
			self::INTENT_NAV   => __( 'Navigational',  'rankwriter-ai' ),
		);
		return isset( $labels[ $intent ] ) ? $labels[ $intent ] : __( 'Unknown', 'rankwriter-ai' );
	}

	public static function color( $intent ) {
		$colors = array(
			self::INTENT_INFO  => '#2271b1', // WP blue
			self::INTENT_COMM  => '#dba617', // WP yellow
			self::INTENT_TRANS => '#2a7e3b', // WP green
			self::INTENT_NAV   => '#787c82', // WP gray
		);
		return isset( $colors[ $intent ] ) ? $colors[ $intent ] : '#787c82';
	}

	/**
	 * Per-intent article template. Drives:
	 *   - article structure
	 *   - CTA placement + style
	 *   - monetization emphasis
	 *   - headline style
	 *   - JSON-LD schema type
	 *   - recommended word count
	 */
	public static function template_for( $intent ) {
		$templates = array(

			self::INTENT_INFO => array(
				'name'                  => 'Informational explainer',
				'structure'             => "1. Concrete hook — open with a real scenario, person, or surprising stat. Never with \"In today's...\"\n2. TL;DR: 2-sentence direct answer\n3. Deep explanation broken into H2 sections, each answering one sub-question\n4. Examples with specific numbers, names, or dates\n5. Related concepts so the reader's mental map is complete\n6. FAQ section (4-6 questions a real reader is now thinking)\n7. Soft \"Read more about X\" link out — never a hard sell",
				'cta_placement'         => 'bottom only',
				'cta_style'             => 'soft / discovery / educational',
				'monetization_emphasis' => 'AdSense (display ads have the best fit and CPM for educational content)',
				'headline_style'        => 'What is X / Why X / How X Works / Complete Guide to X',
				'schema_type'           => 'Article',
				'recommended_word_count'=> 1800,
			),

			self::INTENT_COMM => array(
				'name'                  => 'Commercial comparison / roundup',
				'structure'             => "1. Best-for callout — which option wins for which use case, named in the first sentence\n2. Quick verdict + overall winner above fold\n3. Comparison table (features, price, pros, cons) — readable on mobile\n4. Per-option deep dive: who it's for, key features, drawbacks, real-world performance\n5. Buying-guide section: what to look for, common mistakes\n6. FAQs\n7. \"Check current price\" / \"Read full review\" CTA inline with each option",
				'cta_placement'         => 'inline with each option + sticky on scroll',
				'cta_style'             => 'mid-pressure: \"Check current price\" / \"Read our full review\" / \"See on Amazon\"',
				'monetization_emphasis' => 'Affiliate (Amazon Associates, brand partner programs — highest EPC of all intents)',
				'headline_style'        => 'Best X for Y / Top N X / X vs Y / X Review',
				'schema_type'           => 'ItemList',
				'recommended_word_count'=> 2500,
			),

			self::INTENT_TRANS => array(
				'name'                  => 'Transactional action guide',
				'structure'             => "1. Eligibility / requirements upfront — don't make ready-to-act readers scroll\n2. Primary CTA above the fold\n3. Step-by-step walkthrough of the application / signup / purchase\n4. \"What you'll need\" checklist (documents, info, account, etc.)\n5. Common rejection reasons / pitfalls — save the reader a failed attempt\n6. Timeline + what to expect after\n7. FAQs\n8. Secondary CTA at the bottom",
				'cta_placement'         => 'above-fold + after eligibility + after FAQs',
				'cta_style'             => 'high-intent: \"Apply now\" / \"Get started\" / \"Buy now\" / \"Sign up free\"',
				'monetization_emphasis' => 'Direct affiliate / lead-gen (highest conversion intent — every reader is ready to act)',
				'headline_style'        => 'How to Apply for X / Buy X Online / Get X Now / X Sign Up Guide',
				'schema_type'           => 'HowTo',
				'recommended_word_count'=> 1500,
			),

			self::INTENT_NAV => array(
				'name'                  => 'Navigational anchor',
				'structure'             => "1. Direct answer to who / what the entity is — 1-2 sentences, above all else\n2. Quick-facts table (founded, HQ, key people, what they do)\n3. Most useful resources / official links the reader is probably looking for\n4. Brief context — recent news, mission, key product\n5. Related entities the reader might also need\n6. Soft \"Visit official site\" CTA",
				'cta_placement'         => 'inline links throughout — no hard CTAs',
				'cta_style'             => 'low-pressure / link-out',
				'monetization_emphasis' => 'AdSense only — navigational intent rarely converts on affiliate/direct',
				'headline_style'        => 'X Login / X Official Site / X: Everything You Need to Know / X Customer Service',
				'schema_type'           => 'Article',
				'recommended_word_count'=> 1000,
			),
		);

		return isset( $templates[ $intent ] ) ? $templates[ $intent ] : $templates[ self::INTENT_INFO ];
	}

	/**
	 * Render the template as a prompt-ready block for the content generator.
	 */
	public static function to_prompt_block( array $result ) {
		if ( empty( $result['primary'] ) ) {
			return '';
		}
		$t = $result['template'];
		$lines   = array();
		$lines[] = '## Detected search intent';
		$lines[] = 'Primary intent: ' . $result['label'] . ' (' . (int) $result['confidence'] . '% confidence)';
		if ( ! empty( $result['ai_resolved'] ) ) {
			$lines[] = 'AI tiebreak reason: ' . $result['ai_reason'];
		}
		$lines[] = '';
		$lines[] = 'Follow the "' . $t['name'] . '" template:';
		$lines[] = '';
		$lines[] = '### Structure';
		$lines[] = $t['structure'];
		$lines[] = '';
		$lines[] = '### CTA placement: ' . $t['cta_placement'];
		$lines[] = '### CTA style: '     . $t['cta_style'];
		$lines[] = '### Monetization emphasis: ' . $t['monetization_emphasis'];
		$lines[] = '### Headline style:  '       . $t['headline_style'];
		$lines[] = '### Schema type to set: '    . $t['schema_type'];
		$lines[] = '### Recommended word count: ~' . $t['recommended_word_count'] . ' words';
		$lines[] = '';
		$lines[] = 'CRITICAL: The template above OVERRIDES the generic structure suggested elsewhere in this prompt. Match the article shape to the intent, not to a one-size-fits-all template.';
		return implode( "\n", $lines );
	}
}
