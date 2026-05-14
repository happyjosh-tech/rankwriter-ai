<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-field AI auto-fill: every free-text field in the plugin (Category
 * Profile, Generate Article, Autopilot) can ask Claude for a suggestion
 * grounded in the Blog Style Profile + whatever the user has already typed
 * into sibling fields.
 *
 * The contract is simple: { context, field, payload } in, plain string out.
 * The frontend AJAX button drops the returned string straight into the
 * input/textarea/select.
 */
class RankWriter_AI_AI_Suggester {

	private $client;
	private $style;
	private $profiles;

	public function __construct() {
		$this->client   = new RankWriter_AI_Claude_Client();
		$this->style    = new RankWriter_AI_Style_Profile();
		$this->profiles = new RankWriter_AI_Category_Profiles();
	}

	/**
	 * @param string $context  category_profile | generate_article | autopilot
	 * @param string $field    field key (e.g. niche_description)
	 * @param array  $payload  already-entered sibling field values
	 * @return string|WP_Error suggested value (plain string)
	 */
	public function suggest( $context, $field, array $payload ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$context = sanitize_key( $context );
		$field   = sanitize_key( $field );

		$prompt_pair = $this->build_prompt( $context, $field, $payload );
		if ( ! is_array( $prompt_pair ) ) {
			return new WP_Error( 'rwai_bad_field', __( 'Unknown context or field for AI suggestion.', 'rankwriter-ai' ) );
		}
		list( $system, $user ) = $prompt_pair;

		$text = $this->client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$text = trim( (string) $text );
		// Strip stray surrounding quotes that Claude sometimes adds.
		if ( ( '"' === substr( $text, 0, 1 ) && '"' === substr( $text, -1 ) ) ||
		     ( "'" === substr( $text, 0, 1 ) && "'" === substr( $text, -1 ) ) ) {
			$text = substr( $text, 1, -1 );
		}

		return $this->post_process( $context, $field, $text );
	}

	/**
	 * Concise summary of the existing blog used as background for every
	 * suggestion. Lets Claude tailor outputs to the site's actual niche
	 * instead of generic advice.
	 */
	private function blog_summary() {
		$s = $this->style->get();
		if ( empty( $s ) ) {
			return '(no Blog Style Profile available yet — assume a generic content site)';
		}
		$bits = array();
		if ( ! empty( $s['summary'] ) ) {
			$bits[] = $s['summary'];
		}
		if ( ! empty( $s['preferred_tone'] ) ) {
			$bits[] = 'Tone: ' . $s['preferred_tone'];
		}
		if ( ! empty( $s['common_headline_style'] ) ) {
			$bits[] = 'Headline style: ' . $s['common_headline_style'];
		}
		if ( ! empty( $s['dominant_categories'] ) ) {
			$cats = array();
			foreach ( array_slice( $s['dominant_categories'], 0, 5 ) as $c ) {
				$cats[] = $c['name'];
			}
			$bits[] = 'Dominant categories: ' . implode( ', ', $cats );
		}
		if ( ! empty( $s['monetization_patterns']['dominant_strategy'] ) ) {
			$bits[] = 'Monetization: ' . $s['monetization_patterns']['dominant_strategy'];
		}
		if ( ! empty( $s['audience_intent']['dominant'] ) ) {
			$bits[] = 'Audience intent: ' . $s['audience_intent']['dominant'];
		}
		return implode( '. ', $bits );
	}

	private function build_prompt( $context, $field, array $payload ) {
		$blog_summary = $this->blog_summary();
		$system_base  = "You are RankWriter AI's smart field filler. You return ONLY the value that goes into the form field — no preamble, no explanation, no quotes, no markdown fences. If the field is a dropdown select, you return ONLY the single keyword that matches one of the allowed options.\n\n## Blog context\n" . $blog_summary . "\n";

		switch ( $context . ':' . $field ) {

			/* ====================== CATEGORY PROFILE ====================== */

			case 'category_profile:niche_description':
				$name = $this->v( $payload, 'profile_name' );
				return array(
					$system_base,
					"Write a 2-3 sentence niche description for a blog category called \"$name\". Be specific about what kind of content this category covers, what reader problem it solves, and what makes it distinct. Plain prose only.",
				);

			case 'category_profile:target_audience':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base,
					"Describe the target audience for the \"$name\" content category. " . ( $niche ? "Niche context: $niche. " : '' ) . "1-2 sentences. Mention age bracket, situation, and what they're hoping to get from this content.",
				);

			case 'category_profile:target_country':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				$audience = $this->v( $payload, 'target_audience' );
				return array(
					$system_base . "\nAllowed values: a 2-letter ISO country code (US, GB, CA, AU, NG, IN, ZA, KE, GH, DE, FR, BR, MX, PH, PK).",
					"Pick the best primary target country for \"$name\". " . ( $audience ? "Audience: $audience. " : '' ) . ( $niche ? "Niche: $niche. " : '' ) . "Return ONLY the 2-letter ISO code.",
				);

			case 'category_profile:article_tone':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base . "\nAllowed values: professional, conversational, authoritative, friendly, storytelling, how-to, journalistic.",
					"Pick the best article tone for the \"$name\" category. " . ( $niche ? "Niche: $niche. " : '' ) . "Return ONLY the single keyword.",
				);

			case 'category_profile:monetization_goal':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base . "\nAllowed values: adsense, affiliate, leadgen, ecommerce, mixed, none.",
					"Pick the best monetization strategy for \"$name\". " . ( $niche ? "Niche: $niche. " : '' ) . "Consider what works in this niche — high-CPM AdSense, affiliate (Amazon Associates etc.), lead generation, e-commerce, or mixed. Return ONLY the single keyword.",
				);

			case 'category_profile:article_structure':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				$tone = $this->v( $payload, 'article_tone' );
				return array(
					$system_base,
					"Suggest a 5-7 step article structure ideal for \"$name\" content. " . ( $tone ? "Tone: $tone. " : '' ) . ( $niche ? "Niche: $niche. " : '' ) . "Return as a numbered list (one item per line, e.g., \"1. Hook with...\"). No preamble.",
				);

			case 'category_profile:banned_terms':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base,
					"List 6-10 banned phrases or terms that should NEVER appear in articles for \"$name\". " . ( $niche ? "Niche: $niche. " : '' ) . "Focus on AdSense policy risks, ethically questionable claims, spammy phrases, and unsubstantiated guarantees specific to this niche. Return comma-separated, no numbering, no quotes.",
				);

			case 'category_profile:preferred_keywords':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				$country = $this->v( $payload, 'target_country' );
				return array(
					$system_base,
					"Suggest 8-10 SEO keyword starters for the \"$name\" category. " . ( $country ? "Country: $country. " : '' ) . ( $niche ? "Niche: $niche. " : '' ) . "Mix head terms with longer-tail phrases. Return comma-separated. No numbering, no quotes.",
				);

			case 'category_profile:prompt_instructions':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				$tone = $this->v( $payload, 'article_tone' );
				$audience = $this->v( $payload, 'target_audience' );
				return array(
					$system_base,
					"Write 3-4 sentences of additional Claude prompt instructions for articles in the \"$name\" category. " . ( $tone ? "Tone: $tone. " : '' ) . ( $audience ? "Audience: $audience. " : '' ) . ( $niche ? "Niche: $niche. " : '' ) . "Focus on voice, depth standards, fact-checking expectations, and what makes great content in this niche. Plain prose.",
				);

			case 'category_profile:linking_rules':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base,
					"Suggest 3-4 internal linking rules tailored to \"$name\" content. " . ( $niche ? "Niche: $niche. " : '' ) . "Return as a bullet list (one per line, each starting with \"- \"). No preamble.",
				);

			case 'category_profile:image_style':
				$name = $this->v( $payload, 'profile_name' );
				$niche = $this->v( $payload, 'niche_description' );
				return array(
					$system_base . "\nAllowed values: realistic, illustration, infographic, screenshot, cinematic, minimalist.",
					"Pick the best default image style for \"$name\". " . ( $niche ? "Niche: $niche. " : '' ) . "Return ONLY the single keyword.",
				);

			/* ====================== GENERATE ARTICLE ====================== */

			case 'generate_article:topic':
				$profile_id = isset( $payload['profile_id'] ) ? (int) $payload['profile_id'] : 0;
				$profile    = $this->profiles->get( $profile_id );
				$ctx        = $profile ? "Category: \"{$profile['name']}\". Niche: {$profile['niche_description']}." : '';
				$avoid      = $this->avoid_topics_hint();
				$year       = current_time( 'Y' );
				$month      = current_time( 'F' );
				$human_system = $system_base . "\n\n" . self::human_title_rules();
				return array(
					$human_system,
					"Suggest ONE article title a real human editor would publish in {$month} {$year}. {$ctx} {$avoid}\n\n"
					. "It must sound like a person wrote it — NOT like the standard AI-listicle template. Apply the human title rules above strictly. Return ONLY the title — one line, no quotes, no \"Topic:\" prefix.",
				);

			case 'generate_article:extra_context':
				$topic     = $this->v( $payload, 'topic' );
				$profile_id = isset( $payload['profile_id'] ) ? (int) $payload['profile_id'] : 0;
				$profile    = $this->profiles->get( $profile_id );
				$ctx        = $profile ? "Category: \"{$profile['name']}\". " : '';
				return array(
					$system_base,
					"Write a 2-3 sentence creative brief for an article on \"$topic\". $ctx Specify the angle, must-include points, audience pain to address, and any data/sources worth referencing. Plain prose.",
				);

			/* ========================= AUTOPILOT ========================== */

			case 'autopilot:seed_keywords':
				$profile_id = isset( $payload['profile_id'] ) ? (int) $payload['profile_id'] : 0;
				$profile    = $this->profiles->get( $profile_id );
				$ctx        = $profile ? "Category: \"{$profile['name']}\". Niche: {$profile['niche_description']}." : '';
				return array(
					$system_base,
					"Suggest 5-8 broad seed keywords for Autopilot to expand. $ctx Each seed should be a topic broad enough to spawn many long-tail articles but specific enough that the keyword pool stays on-niche. Return one seed per line. No numbering, no quotes, no preamble.",
				);

			case 'autopilot:country':
				$profile_id = isset( $payload['profile_id'] ) ? (int) $payload['profile_id'] : 0;
				$profile    = $this->profiles->get( $profile_id );
				$ctx        = $profile ? "Category: \"{$profile['name']}\". " . ( $profile['target_country'] ? "Profile target country: {$profile['target_country']}. " : '' ) : '';
				return array(
					$system_base . "\nAllowed values: a 2-letter ISO country code.",
					"Pick the best country for Autopilot keyword research. $ctx Return ONLY the 2-letter ISO code.",
				);
		}

		return null;
	}

	/**
	 * Trim / clamp the value depending on the field. Selects must match
	 * exactly; free-text fields just get whitespace cleanup.
	 */
	private function post_process( $context, $field, $text ) {
		$selects = array(
			'category_profile:article_tone'      => array( 'professional', 'conversational', 'authoritative', 'friendly', 'storytelling', 'how-to', 'journalistic' ),
			'category_profile:monetization_goal' => array( 'adsense', 'affiliate', 'leadgen', 'ecommerce', 'mixed', 'none' ),
			'category_profile:image_style'       => array( 'realistic', 'illustration', 'infographic', 'screenshot', 'cinematic', 'minimalist' ),
		);
		$key = $context . ':' . $field;

		if ( isset( $selects[ $key ] ) ) {
			$lower = strtolower( trim( $text ) );
			foreach ( $selects[ $key ] as $allowed ) {
				if ( false !== strpos( $lower, $allowed ) ) {
					return $allowed;
				}
			}
			return $selects[ $key ][0]; // fallback to first option
		}

		if ( in_array( $field, array( 'target_country', 'country' ), true ) ) {
			$code = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $text ), 0, 2 ) );
			return $code ?: 'US';
		}

		// Free text: collapse stray whitespace runs, cap length.
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		return mb_substr( $text, 0, 4000 );
	}

	private function v( array $payload, $key ) {
		if ( ! isset( $payload[ $key ] ) ) {
			return '';
		}
		return trim( wp_strip_all_tags( (string) $payload[ $key ] ) );
	}

	/**
	 * Shared human-title rules. Embedded into the topic auto-fill prompt
	 * (single suggestion) AND the Title Intelligence variant generator
	 * (5 styles × N variants) so both code paths stop producing the
	 * recognizable "Top 15 X for Y in 2026 (With Z)" AI template.
	 */
	public static function human_title_rules() {
		return <<<RULES
## Human title rules — non-negotiable

These titles must not pattern-match the typical AI listicle template. A reader scrolling a feed should think "a person wrote this" — not "this is the generic Top-15 SEO format."

DO:
- Vary the OPENING — never default to "Top N" / "Best N" / "Ultimate Guide to" every time. Use openers like "I tried…", "Why X is…", "The truth about…", "Closing in January:", "Before you apply for…", "Stop X — here's what actually works", "Inside the…", "Reading {topic} like a {persona}".
- Sound like editorial copy you'd see in The Atlantic, The Verge, Bloomberg, BuzzFeed, or a thoughtful Substack — not a generic SEO blog.
- Be specific about a date, a place, a person, a dollar amount, or a real constraint. "Closing in January" beats "With January Deadlines." "$50K stipend" beats "Fully funded."
- Use direct address ("you", "your") or first person ("I", "we") about half the time.
- Use natural punctuation — em dashes, colons, occasional questions. Skip parenthetical qualifiers like "(With January Deadlines)" — they read as SEO furniture.
- When a number is used, make it interesting (3, 7, 11, 17, 23) — not the round-marketing 5/10/15/20.

DELETE on sight:
- "Top N {plural noun} for {audience} in {year} (With {modifier})" — the single most over-used AI title template.
- "Ultimate Guide to X" / "Comprehensive Guide to X" / "Everything You Need to Know About X"
- "X: A Complete Guide" / "The Definitive Guide to X"
- Trailing parentheticals: "(With Deadlines)", "(Updated for 2026)", "(For Beginners)"
- "Unlock", "Master", "Discover", "Boost", "Skyrocket", "Maximize", "Game-changer", "Revolutionary"
- "You won't believe", "Shocking", "Doctors hate", "This one trick"
- Stuffing every keyword into one line ("Fully Funded Scholarships for International Students With Deadlines 2026")
- The year tacked on at the end as a marketing tag ("…in 2026")
- ALL CAPS words
- More than one exclamation mark; or any exclamation mark in a non-emotional topic

PATTERN VARIETY — rotate across these archetypes instead of repeating one:
- A specific claim:           "{Org} just opened {N} fully-funded {thing} — applications close {month}"
- A pointed question:         "Are fully-funded scholarships still worth the visa hassle?"
- A reframe:                  "Stop chasing every scholarship. Apply to these {N}."
- A first-person guide:       "I sent {N} scholarship applications last year. Here's what actually moved the needle."
- A list with attitude:       "{N} scholarships that pay tuition AND stipend (no fine print)"
- A deadline-led hook:        "Closing this {month}: {N} scholarships most {audience} miss"
- A contrarian take:          "The {adjective} scholarship advice that's quietly wrong"

GOOD vs BAD — calibrate to these:
BAD:   "Top 15 Fully Funded Scholarships for International Students in 2026 (With January Deadlines)"
GOOD:  "Closing this January: 11 fully-funded scholarships most international applicants miss"
GOOD:  "I tracked every January-deadline scholarship. These 11 are still worth applying to."
GOOD:  "The fully-funded scholarship list nobody's sharing — and the 11 closing first"
BAD:   "Ultimate Guide to Remote Jobs for Beginners in 2026"
GOOD:  "Remote-job advice for beginners that nobody tells you — from someone who's hired 40 of them"
RULES;
	}

	/**
	 * Tells the topic-suggester to avoid topics the blog has already covered.
	 */
	private function avoid_topics_hint() {
		$s = $this->style->get();
		if ( empty( $s ) ) {
			return '';
		}
		$avoid = array();
		if ( ! empty( $s['common_topics_covered']['bigrams'] ) ) {
			$avoid = array_slice( array_keys( $s['common_topics_covered']['bigrams'] ), 0, 8 );
		}
		if ( empty( $avoid ) && ! empty( $s['existing_post_titles'] ) ) {
			foreach ( array_slice( $s['existing_post_titles'], 0, 5 ) as $pt ) {
				$avoid[] = $pt['title'];
			}
		}
		if ( empty( $avoid ) ) {
			return '';
		}
		return 'Avoid duplicating these existing topics: ' . implode( ', ', $avoid ) . '.';
	}
}
