<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combines:
 *   - the selected Category Profile
 *   - the persisted Blog Style Profile (from learning engine)
 *   - LIVE keyword / title / trending signals from search engines + competitors
 * into a Claude prompt and produces a draft article. Writes SEO meta into
 * whatever SEO plugin is active (Rank Math / Yoast / AIOSEO / SEOPress).
 */
class RankWriter_AI_Content_Generator {

	private $profiles;
	private $style;
	private $client;
	private $research;
	private $seo;
	private $linker;
	private $compliance;
	private $schema;
	private $images;

	public function __construct() {
		$this->profiles   = new RankWriter_AI_Category_Profiles();
		$this->style      = new RankWriter_AI_Style_Profile();
		$this->client     = new RankWriter_AI_Claude_Client();
		$this->research   = new RankWriter_AI_Keyword_Research();
		$this->seo        = new RankWriter_AI_SEO_Integration();
		$this->linker     = new RankWriter_AI_Internal_Linker();
		$this->compliance = new RankWriter_AI_Compliance();
		$this->schema     = new RankWriter_AI_Schema_Injector();
		$this->images     = new RankWriter_AI_Image_Sourcer();
	}

	/**
	 * Generate an article and save it as a WordPress post.
	 *
	 * @return int|WP_Error post ID on success.
	 */
	public function generate( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'profile_id'                 => 0,
				'topic'                      => '',
				'word_count'                 => 0,
				'extra_context'              => '',
				'desired_status'             => 'draft',
				'write_seo_meta'             => true,
				'country_override'           => '',
				'autopilot'                  => false,
				'override_wp_category_id'    => 0,
				'override_wp_category_new'   => '',
				'max_tags'                   => 0, // 0 = no cap
				'cluster_id'                 => 0,
				'cluster_topic_id'           => 0,
				'pse_context'                => '',
				'language'                   => 'en',
			)
		);

		if ( ! $args['autopilot'] && ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rwai_forbidden', __( 'Insufficient permissions.', 'rankwriter-ai' ) );
		}

		$profile_id = absint( $args['profile_id'] );
		$topic      = sanitize_text_field( $args['topic'] );
		if ( '' === $topic ) {
			return new WP_Error( 'rwai_no_topic', __( 'Topic is required.', 'rankwriter-ai' ) );
		}

		$profile = $this->profiles->get( $profile_id );
		if ( ! $profile ) {
			return new WP_Error( 'rwai_no_profile', __( 'Select a valid category profile.', 'rankwriter-ai' ) );
		}

		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured. Add it in RankWriter AI → Settings.', 'rankwriter-ai' ) );
		}

		$word_count = absint( $args['word_count'] );
		if ( $word_count <= 0 ) {
			$style = $this->style->get();
			$word_count = ! empty( $style['average_word_count'] ) ? (int) $style['average_word_count'] : (int) RankWriter_AI_Helpers::get_setting( 'default_word_count', 1500 );
		}

		$country = '' !== $args['country_override'] ? sanitize_text_field( $args['country_override'] ) : ( ! empty( $profile['target_country'] ) ? $profile['target_country'] : 'US' );
		$country_code = $this->country_code( $country );

		do_action( 'rwai_generation_step', 'Keyword research (Google Suggest + Trends + competitors)' );
		$competitors = $this->competitor_domains();
		$research    = $this->research->discover( $topic, $country_code, $competitors );
		if ( is_wp_error( $research ) ) {
			$research = array();
		}

		$cat_term_id = $this->resolve_wp_category( $profile, $args );
		$kw_tokens   = $this->topic_keywords( $topic, $research );
		$this->linker->set_target_language( (string) $args['language'] );
		$link_pool   = $this->linker->get_candidates( $cat_term_id, $kw_tokens, 12, (int) $args['cluster_id'] );

		$cluster_context = $this->cluster_prompt_context( (int) $args['cluster_id'] );

		// Detect search intent on the topic. Heuristic-first; falls back to
		// Claude for tiebreaking on ambiguous queries. Drives article shape,
		// CTA placement, monetization emphasis, headline style, and schema.
		do_action( 'rwai_generation_step', 'Search intent detection (heuristic + Claude tiebreak)' );
		$intent_detector = new RankWriter_AI_Intent_Detector();
		$intent          = $intent_detector->detect_with_ai( $topic );
		$intent_block    = RankWriter_AI_Intent_Detector::to_prompt_block( $intent );

		$lang_block    = $this->language_prompt_block( (string) $args['language'] );
		$system_prompt = $this->build_system_prompt( $profile_id, $word_count, $research, $link_pool, $cluster_context, $intent_block, (string) $args['pse_context'], $lang_block, (int) $cat_term_id );
		$user_prompt   = $this->build_user_prompt( $profile, $topic, $word_count, $args['extra_context'], $research );

		do_action( 'rwai_generation_step', 'Main Claude article generation (60-120s — the slow one)' );
		$text = $this->client->send(
			$system_prompt,
			array(
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			)
		);

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		do_action( 'rwai_generation_step', 'Parsing Claude response into title + body + meta' );
		$parsed = $this->parse_response( $text, $topic );

		// 1a) Humanize pass — opt-in via Settings. Second Claude call that
		// rewrites the draft to scrub AI tells while preserving facts,
		// numbers, HTML structure, and internal-link URLs. Delegates to
		// the AI Humanization Engine (strength + tone + persona + readability).
		if ( $this->should_humanize() && class_exists( 'RankWriter_AI_Humanizer' ) ) {
			do_action( 'rwai_generation_step', 'Humanizer Claude pass (60-120s — second slow call; toggle OFF in Settings to skip)' );
			$humanizer = new RankWriter_AI_Humanizer();
			$opts      = RankWriter_AI_Humanizer::default_options();
			$opts['topic']        = $topic;
			$opts['niche']        = isset( $profile['niche_description'] ) ? wp_trim_words( $profile['niche_description'], 25 ) : '';
			$opts['banned_terms'] = isset( $profile['banned_terms'] ) ? (string) $profile['banned_terms'] : '';
			$humanized = $humanizer->humanize( $parsed['content'], $opts );
			if ( ! empty( $humanized ) ) {
				$parsed['content'] = $humanized;
			}
		}

		// 1b) Auto-link any bare mentions of internal-post titles to real URLs.
		if ( ! empty( $link_pool ) ) {
			$parsed['content'] = $this->linker->auto_link( $parsed['content'], $link_pool, 5 );
		}

		// 2) Compliance: banned terms + AdSense + readability.
		$report = $this->compliance->check( $parsed['content'], $profile );
		if ( ! empty( $profile['banned_terms'] ) ) {
			$parsed['content'] = $this->compliance->redact_banned( $parsed['content'], $profile['banned_terms'] );
		}

		$status = in_array( $args['desired_status'], array( 'draft', 'publish', 'pending' ), true ) ? $args['desired_status'] : 'draft';
		// Downgrade auto-publish to draft if there are blocking compliance errors.
		if ( 'publish' === $status && ! $report['passed'] ) {
			$status = 'draft';
		}

		do_action( 'rwai_generation_step', 'Saving draft post to WordPress' );
		// wp_insert_post expects SLASHED values and unslashes internally.
		// Without wp_slash() here, any legitimate "\" in the body (e.g. a
		// stray "\n" Claude wrote inside content_html) would have its
		// backslash stripped, leaving the trailing letter as raw text
		// ("nn" between paragraphs is the visible symptom).
		$post_id = wp_insert_post(
			array(
				'post_title'   => wp_slash( $parsed['title'] ),
				'post_content' => wp_slash( $parsed['content'] ),
				'post_excerpt' => wp_slash( $parsed['meta_description'] ),
				'post_status'  => $status,
				'post_type'    => 'post',
				'post_author'  => $this->resolve_author_id( $args ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Tag handling. max_tags = 0 → use everything Claude returned (default).
		// max_tags > 0 → cap to first N. Autopilot defaults to 2 via its config.
		if ( ! empty( $parsed['tags'] ) ) {
			$tags = $parsed['tags'];
			$max  = (int) $args['max_tags'];
			if ( $max > 0 ) {
				$tags = array_slice( $tags, 0, $max );
			}
			if ( ! empty( $tags ) ) {
				wp_set_post_tags( $post_id, $tags );
			}
		}

		if ( $cat_term_id ) {
			wp_set_post_categories( $post_id, array( $cat_term_id ) );
		}

		// 3) Save compliance report (surfaced as admin notice on post edit screen).
		$this->compliance->save_report( $post_id, $report );

		// 4) Source a featured image biased by the category profile's image_style.
		do_action( 'rwai_generation_step', 'Sourcing featured image' );
		$image_style = ! empty( $profile['image_style'] ) ? $profile['image_style'] : (string) RankWriter_AI_Helpers::get_setting( 'default_image_style', 'realistic' );
		$image_query = $parsed['focus_keyword'] ? $parsed['focus_keyword'] : $topic;
		$this->images->source_and_attach( $post_id, $image_query, $image_style );

		// 5) Build + persist JSON-LD schema for the chosen schema_type.
		$this->schema->build_and_save( $post_id, $parsed['schema_type'], $parsed['content'], $parsed['focus_keyword'] );

		if ( ! empty( $args['write_seo_meta'] ) ) {
			$this->seo->write_meta( $post_id, array(
				'title'         => $parsed['seo_title'] ? $parsed['seo_title'] : $parsed['title'],
				'description'   => $parsed['meta_description'],
				'focus_keyword' => $parsed['focus_keyword'] ? $parsed['focus_keyword'] : $topic,
				'secondary'     => $parsed['secondary_keywords'],
				'og_title'      => $parsed['title'],
				'og_description'=> $parsed['meta_description'],
				'canonical'     => '',
				'schema_type'   => $parsed['schema_type'] ? $parsed['schema_type'] : 'Article',
			) );
		}

		update_post_meta( $post_id, '_rwai_generated', 1 );
		update_post_meta( $post_id, '_rwai_profile_id', $profile_id );
		update_post_meta( $post_id, '_rwai_topic', $topic );
		update_post_meta( $post_id, '_rwai_country', $country_code );
		if ( class_exists( 'RankWriter_AI_Language' ) ) {
			RankWriter_AI_Language::set_post_language( $post_id, $args['language'] );
		}
		if ( ! empty( $args['cluster_id'] ) ) {
			update_post_meta( $post_id, '_rwai_cluster_id', (int) $args['cluster_id'] );
		}
		if ( ! empty( $args['cluster_topic_id'] ) ) {
			update_post_meta( $post_id, RankWriter_AI_Cluster_Manager::META_TOPIC_ID, (int) $args['cluster_topic_id'] );
		}
		if ( ! empty( $intent['primary'] ) ) {
			update_post_meta( $post_id, '_rwai_intent', $intent['primary'] );
			update_post_meta( $post_id, '_rwai_intent_confidence', (int) $intent['confidence'] );
		}

		// Discover readiness snapshot — stored so the post compliance box
		// can show the four dimensions without re-scoring on every load.
		if ( class_exists( 'RankWriter_AI_Discover_Optimizer' ) ) {
			$discover = ( new RankWriter_AI_Discover_Optimizer() )->score_post( $post_id );
			update_post_meta( $post_id, '_rwai_discover_overall',  (int) $discover['overall'] );
			update_post_meta( $post_id, '_rwai_discover_mobile',   (int) $discover['mobile_engagement']['score'] );
			update_post_meta( $post_id, '_rwai_discover_fresh',    (int) $discover['freshness']['score'] );
			update_post_meta( $post_id, '_rwai_discover_emotion',  (int) $discover['emotional_engagement']['score'] );
			update_post_meta( $post_id, '_rwai_discover_image',    (int) $discover['image_readiness']['score'] );
		}

		// CPC opportunity score for the post — gives the editor a quick
		// monetization snapshot in the compliance meta box.
		if ( class_exists( 'RankWriter_AI_CPC_Scorer' ) ) {
			$cpc_country = $country_code ?: 'US';
			$hints       = array();
			if ( ! empty( $intent['primary'] ) ) {
				$hints['intent'] = $intent['primary'];
			}
			$cpc_row = ( new RankWriter_AI_CPC_Scorer() )->score( $topic, $cpc_country, $hints );
			update_post_meta( $post_id, '_rwai_cpc_tier',           $cpc_row['tier'] );
			update_post_meta( $post_id, '_rwai_cpc_estimated_usd',  $cpc_row['estimated_cpc_usd'] );
			update_post_meta( $post_id, '_rwai_rpm_estimated_usd',  $cpc_row['rpm_prediction_usd'] );
			update_post_meta( $post_id, '_rwai_monetization_score', $cpc_row['monetization_score'] );
			update_post_meta( $post_id, '_rwai_cpc_niche',          $cpc_row['niche'] );
			update_post_meta( $post_id, '_rwai_cpc_priority',       (int) $cpc_row['priority_niche'] );
		}
		update_post_meta( $post_id, '_rwai_research_snapshot', wp_json_encode( array(
			'merged_seed_pool'  => isset( $research['merged_seed_pool'] ) ? array_slice( $research['merged_seed_pool'], 0, 15 ) : array(),
			'trending_topics'   => isset( $research['trending_topics'] ) ? array_slice( $research['trending_topics'], 0, 10 ) : array(),
			'competitor_titles' => isset( $research['competitor_titles'] ) ? array_slice( $research['competitor_titles'], 0, 10 ) : array(),
			'fetched_at'        => isset( $research['fetched_at'] ) ? $research['fetched_at'] : '',
		) ) );

		// Heuristic fact-check on every freshly generated post. We skip the
		// Claude validation pass to keep generation cheap — the user can
		// run the deep review manually from the Fact Checker page.
		if ( class_exists( 'RankWriter_AI_Fact_Checker' ) ) {
			do_action( 'rwai_generation_step', 'Fact-checking heuristics' );
			( new RankWriter_AI_Fact_Checker() )->check_post( (int) $post_id, false );
		}

		// Risk + AdSense compliance scan on every generated post.
		if ( class_exists( 'RankWriter_AI_Risk_Detector' ) ) {
			do_action( 'rwai_generation_step', 'Risk + AdSense compliance scan' );
			( new RankWriter_AI_Risk_Detector() )->scan_post( (int) $post_id );
		}

		do_action( 'rwai_generation_step', 'Done' );
		return (int) $post_id;
	}

	private function competitor_domains() {
		$raw = (string) RankWriter_AI_Helpers::get_setting( 'competitor_domains', '' );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d ) {
				$out[] = $d;
			}
		}
		return $out;
	}

	private function country_code( $country ) {
		$country = trim( (string) $country );
		if ( 2 === strlen( $country ) ) {
			return strtoupper( $country );
		}
		$map = array(
			'united states' => 'US', 'usa' => 'US', 'america' => 'US',
			'united kingdom' => 'GB', 'uk' => 'GB', 'britain' => 'GB',
			'canada' => 'CA', 'australia' => 'AU', 'nigeria' => 'NG',
			'india' => 'IN', 'south africa' => 'ZA', 'kenya' => 'KE',
			'ghana' => 'GH', 'germany' => 'DE', 'france' => 'FR',
			'brazil' => 'BR', 'mexico' => 'MX', 'philippines' => 'PH',
			'pakistan' => 'PK',
		);
		$low = strtolower( $country );
		return isset( $map[ $low ] ) ? $map[ $low ] : 'US';
	}

	private function build_system_prompt( $profile_id, $word_count, array $research, array $link_pool = array(), $cluster_context = '', $intent_block = '', $pse_context = '', $lang_block = '', $cat_term_id = 0 ) {
		$style_block   = $this->style->to_prompt_context();
		$profile_block = $this->profiles->to_prompt_context( $profile_id );
		$fresh_block   = $this->fresh_signals_block( $research );
		$linker_block  = $this->linker->to_prompt_context( $link_pool );

		// Brand Voice memory — applies the calibrated tone + formatting
		// fingerprint + category-specific overrides. Highest-priority block
		// in the prompt so it overrides anything the Style Profile says.
		$voice_block = '';
		if ( class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			$voice_block = ( new RankWriter_AI_Voice_Memory() )->to_prompt_context( (int) $cat_term_id );
		}

		$profile     = $this->profiles->get( $profile_id );
		$niche_name  = ! empty( $profile['name'] ) ? $profile['name'] : 'this topic';
		$niche_short = ! empty( $profile['niche_description'] ) ? wp_trim_words( $profile['niche_description'], 25 ) : '';

		$header = "You are NOT a generic AI writing assistant. You are an experienced writer who has covered \"{$niche_name}\" for years. " . ( $niche_short ? "Your beat: {$niche_short} " : '' ) . "You have opinions, you have scars from things that went wrong, and you have specific knowledge that 90% of writers in this space don't. Write from THAT voice.\n\n"
			. "Your reputation depends on this article reading like a human practitioner wrote it — not an LLM. A skeptical reader on Twitter must not be able to tell it's AI-generated.\n\n"
			. $this->voice_rules_block()
			. "\n\n";

		$rules = "## Output rules\n"
			. "Return ONLY valid JSON with this exact shape:\n"
			. "{\n"
			. "  \"title\": \"<reader-facing H1, under 70 chars>\",\n"
			. "  \"seo_title\": \"<SEO title tag, 50-60 chars, includes focus keyword>\",\n"
			. "  \"meta_description\": \"<150-160 char meta description>\",\n"
			. "  \"focus_keyword\": \"<single primary keyword from the fresh signals>\",\n"
			. "  \"secondary_keywords\": [\"<kw1>\", \"<kw2>\", \"<kw3>\", \"<kw4>\"],\n"
			. "  \"schema_type\": \"Article | HowTo | FAQPage | Product | NewsArticle\",\n"
			. "  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"],\n"
			. "  \"content_html\": \"<full article in clean HTML>\"\n"
			. "}\n\n"
			. "CRITICAL formatting rules for content_html:\n"
			. "- The value of content_html must be a SINGLE valid JSON string. Inside that string, use proper HTML tags (<h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <thead>, <tbody>, <tr>, <th>, <td>) — never markdown, never raw newlines for layout.\n"
			. "- Do NOT write literal escape sequences like \\\\n or \\\\t inside content_html to force line breaks. Spacing between HTML tags is irrelevant to rendering — let the tags do the work.\n"
			. "- Inside a JSON string, you MAY use a real newline by writing the JSON escape \\n; but ONLY use it between block-level HTML tags (e.g. between </p> and <h2>). Do NOT use it inside paragraph text.\n"
			. "- Escape all literal double-quotes inside content_html using \\\" — never bare \".\n"
			. "- No markdown code fences around the JSON output. No \"```json\". Return raw JSON only.\n\n"
			. "Content constraints:\n"
			. "- Aim for ~{$word_count} words.\n"
			. "- Use H2/H3 hierarchy matching the blog's preferred formatting.\n"
			. "- Choose focus_keyword from the FRESH KEYWORD SIGNALS block — not from training data.\n"
			. "- Weave 3-4 secondary_keywords naturally into the prose.\n"
			. "- If the blog favors FAQs, include a 4-6 question FAQ section and set schema_type to FAQPage.\n"
			. "- If the article is step-by-step, set schema_type to HowTo.\n"
			. "- Never include placeholder text, 'as an AI', or invented statistics.\n"
			. "- Respect every banned term in the Category Profile.\n"
			. "- Cite numerical claims with the source domain inline.\n";

		$discover_block = class_exists( 'RankWriter_AI_Discover_Optimizer' )
			? RankWriter_AI_Discover_Optimizer::generator_rules_block()
			: '';

		return $header
			. ( $lang_block ? $lang_block . "\n\n" : '' )
			. ( $voice_block ? $voice_block . "\n\n" : '' )
			. ( $style_block ? $style_block . "\n\n" : '' )
			. ( $profile_block ? $profile_block . "\n\n" : '' )
			. ( $intent_block ? $intent_block . "\n\n" : '' )
			. ( $discover_block ? $discover_block . "\n\n" : '' )
			. ( $pse_context ? $pse_context . "\n\n" : '' )
			. ( $cluster_context ? $cluster_context . "\n\n" : '' )
			. ( $fresh_block ? $fresh_block . "\n\n" : '' )
			. ( $linker_block ? $linker_block . "\n\n" : '' )
			. $rules;
	}

	private function language_prompt_block( $lang ) {
		$lang = strtolower( (string) $lang );
		if ( '' === $lang || 'en' === $lang ) {
			return '';
		}
		if ( ! class_exists( 'RankWriter_AI_Language' ) ) {
			return '';
		}
		$cfg = RankWriter_AI_Language::language( $lang );
		if ( ! $cfg ) {
			return '';
		}
		$rtl = ! empty( $cfg['rtl'] ) ? " (right-to-left script)" : '';
		return "## Output language\nWrite the ENTIRE article (title, all prose, headings, lists, FAQ questions and answers, meta_description, tags) in {$cfg['name']}{$rtl}. Write as a native {$cfg['name']} writer — natural idioms, local examples, currency / units adapted to {$cfg['default_country']}. Never mix English filler phrases into the prose. The focus_keyword and secondary_keywords must also be in {$cfg['name']}.";
	}

	/**
	 * Compose the cluster-aware prompt block. Tells Claude this article is
	 * part of a topical authority cluster, names the pillar, lists sibling
	 * topics, and instructs the model to write the article as a supporting
	 * piece that links back to the pillar.
	 */
	private function cluster_prompt_context( $cluster_id ) {
		$cluster_id = (int) $cluster_id;
		if ( ! $cluster_id || ! class_exists( 'RankWriter_AI_Cluster_Manager' ) ) {
			return '';
		}
		$mgr     = new RankWriter_AI_Cluster_Manager();
		$cluster = $mgr->get( $cluster_id, true );
		if ( ! $cluster ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Topical authority cluster context';
		$lines[] = 'This article is a SUPPORTING piece inside the "' . $cluster['name'] . '" cluster. It must link back to the pillar and connect laterally to sibling topics.';

		if ( ! empty( $cluster['pillar_post_id'] ) ) {
			$pillar = get_post( (int) $cluster['pillar_post_id'] );
			if ( $pillar ) {
				$lines[] = '';
				$lines[] = '### Pillar article (link back to this once, with natural anchor text)';
				$lines[] = '- Title: ' . $pillar->post_title;
				$lines[] = '- URL:   ' . get_permalink( $pillar->ID );
			}
		} else {
			$lines[] = '';
			$lines[] = '(No pillar set yet — write this article as if a pillar titled "' . $cluster['name'] . '" exists, but do NOT invent a URL.)';
		}

		$sibling_lines = array();
		foreach ( (array) $cluster['topics'] as $t ) {
			if ( empty( $t['post_id'] ) ) {
				continue;
			}
			$sib = get_post( (int) $t['post_id'] );
			if ( $sib ) {
				$sibling_lines[] = '- "' . $sib->post_title . '" — ' . get_permalink( $sib->ID );
			}
		}
		if ( ! empty( $sibling_lines ) ) {
			$lines[] = '';
			$lines[] = '### Sibling articles in this cluster (link to 2-3 where contextually relevant)';
			$lines = array_merge( $lines, array_slice( $sibling_lines, 0, 8 ) );
		}

		if ( ! empty( $cluster['semantic_keywords'] ) ) {
			$lines[] = '';
			$lines[] = '### Semantic keyword pool for this cluster (weave naturally; do not stuff)';
			$lines[] = $cluster['semantic_keywords'];
		}

		$lines[] = '';
		$lines[] = 'Cluster-rules:';
		$lines[] = '- Include a 1-2 sentence reference to the pillar topic near the top of the article.';
		$lines[] = '- Use <a href="..."> with the pillar URL above for the pillar link.';
		$lines[] = '- Use natural anchor text for sibling links — never "click here" or "read more".';
		$lines[] = '- Do NOT duplicate ground already covered by sibling articles listed above.';

		return implode( "\n", $lines );
	}

	/**
	 * Extract relevant keyword tokens from the topic and research output for
	 * use as internal-link candidate search terms.
	 */
	/**
	 * Pick an author for the generated post. Manual generations use the
	 * current user; Autopilot (cron, no logged-in user) falls back to the
	 * first administrator on the site so posts never end up author-less.
	 */
	private function resolve_author_id( array $args ) {
		if ( empty( $args['autopilot'] ) ) {
			$uid = get_current_user_id();
			if ( $uid ) {
				return (int) $uid;
			}
		}
		if ( ! empty( $args['author_id'] ) ) {
			return (int) $args['author_id'];
		}
		$admins = get_users( array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		) );
		if ( ! empty( $admins ) ) {
			return (int) $admins[0];
		}
		return 1;
	}

	private function topic_keywords( $topic, array $research ) {
		$tokens = array_filter( array_map( 'trim', preg_split( '/\s+/', strtolower( $topic ) ) ), function ( $w ) {
			return strlen( $w ) >= 4;
		} );
		if ( ! empty( $research['merged_seed_pool'] ) ) {
			foreach ( array_slice( $research['merged_seed_pool'], 0, 8 ) as $kw ) {
				$tokens[] = $kw['keyword'];
			}
		}
		return array_values( array_unique( $tokens ) );
	}

	/**
	 * The hard voice rules. Embedded in every system prompt + the humanize
	 * pass. Distilled from the most common reader complaints about AI
	 * content (generic openings, hedging, parallel structure, hollow
	 * conclusions, banned filler phrases).
	 */
	private function voice_rules_block() {
		return <<<RULES
## Voice rules — non-negotiable

DO:
- Open with a concrete situation, a specific person, a real number, or a contradiction. Never with "In today's...", "In the world of...", "With X becoming more popular...", or any generic stage-setting.
- Have an opinion. Say "most guides get this wrong" when it's true. Pick sides on debated points. Name a single best option instead of listing 7 equivalents.
- Use specific names, dollar amounts, dates, percentages, ages, deadlines. Replace "many companies" with three real ones. Replace "various options" with the two that matter.
- Vary sentence length aggressively. Mix 3-word sentences with 25-word ones. Use fragments. For rhythm.
- Use contractions: it's, don't, you'll, can't, won't, you're.
- Use "you" when giving advice. Use "I" or "we" when sharing experience or making a judgment call.
- Address the reader's likely objection inline: "You might be thinking X. You're not wrong, but here's what changes that..."
- Vary how list items are written — never make every bullet the same length, same grammar, or same start word.
- End with a specific next action, a sharp opinion, or a real question the reader is now thinking. Never with a recap of what was just said.
- Use occasional one-line paragraphs for emphasis.
- Use <em> for tone and emphasis; <strong> for skim-readability of key terms — sparingly.

DELETE on sight (these phrases are the dead giveaways of AI prose):
- "In today's fast-paced world / digital age / competitive landscape / interconnected world"
- "It is important to note that / It's worth mentioning that / It's no secret that / It goes without saying"
- "Furthermore / Moreover / In conclusion / In summary / To sum up / All in all"
- "A tapestry of / a plethora of / a myriad of / a wealth of / a vast array of / an abundance of"
- "Whether you're a [X] or a [Y]" (cliché opener)
- "Look no further / Buckle up / Without further ado / Strap in"
- "Game-changer / paradigm shift / unlock the power of / harness the potential of"
- Generic adjectives without justification: "robust / comprehensive / cutting-edge / state-of-the-art / innovative / revolutionary / seamless / powerful"
- "Delve into / dive deep into / dive into / navigate the complexities of / journey through"
- Hedge stacks: "may potentially possibly help", "could possibly be"
- Section openings that just restate the H2 keyword: "When it comes to X, X is..."
- Closing paragraphs that summarize what the article already said.
- Bullet lists where every line starts with the same word or pattern.
- "Crucial / vital / essential / pivotal" used more than once per article.

PACING:
- Mix paragraph lengths. Some 1-2 sentences. Some 4-6. Never uniform.
- Drop in a rhetorical question every 3-4 sections — but only ones a real reader would ask.
- Concrete > abstract. If a sentence could appear in any article on any topic, rewrite it with niche-specific detail.

ANTI-PATTERNS that scream AI (do not produce):
- The "5-point bulleted bullet point" essay structure where every section is the same shape.
- A conclusion section that re-lists every prior section.
- Stating both sides of every issue without taking one.
- Defining basic terms the target audience already knows.
- "FAQ" sections where the questions are generic restatements of section headings.
RULES;
	}

	private function fresh_signals_block( array $research ) {
		if ( empty( $research ) ) {
			return '';
		}
		$lines = array();
		$lines[] = '## Fresh keyword signals (fetched ' . ( isset( $research['fetched_at'] ) ? $research['fetched_at'] : 'now' ) . ')';

		if ( ! empty( $research['merged_seed_pool'] ) ) {
			$lines[] = '### Ranked live keywords (use one as focus_keyword, weave 3-4 as secondary)';
			foreach ( array_slice( $research['merged_seed_pool'], 0, 20 ) as $kw ) {
				$lines[] = '- ' . $kw['keyword'] . ' (score ' . $kw['score'] . ')';
			}
		}
		if ( ! empty( $research['suggest_keywords'] ) ) {
			$lines[] = '';
			$lines[] = '### Google Suggest (autocomplete) right now';
			$lines[] = '- ' . implode( "\n- ", array_slice( $research['suggest_keywords'], 0, 15 ) );
		}
		if ( ! empty( $research['serpapi_related']['related_questions'] ) ) {
			$lines[] = '';
			$lines[] = '### People Also Ask (cover these in your FAQ section if relevant)';
			$lines[] = '- ' . implode( "\n- ", array_slice( $research['serpapi_related']['related_questions'], 0, 8 ) );
		}
		if ( ! empty( $research['serpapi_related']['organic_titles'] ) ) {
			$lines[] = '';
			$lines[] = '### Top-ranking competitor titles for this query (write something better)';
			foreach ( array_slice( $research['serpapi_related']['organic_titles'], 0, 8 ) as $r ) {
				$lines[] = '- ' . $r['title'];
			}
		}
		if ( ! empty( $research['competitor_titles'] ) ) {
			$lines[] = '';
			$lines[] = '### Recent posts from competitor blogs you supplied';
			foreach ( array_slice( $research['competitor_titles'], 0, 10 ) as $c ) {
				$lines[] = '- ' . $c['title'] . ' (' . $c['source'] . ')';
			}
		}
		if ( ! empty( $research['trending_topics'] ) ) {
			$lines[] = '';
			$lines[] = '### Country-level trending searches right now';
			foreach ( array_slice( $research['trending_topics'], 0, 8 ) as $t ) {
				$lines[] = '- ' . $t['title'];
			}
		}
		if ( ! empty( $research['dataforseo_volume'] ) ) {
			$lines[] = '';
			$lines[] = '### Search volume data (DataForSEO)';
			foreach ( array_slice( $research['dataforseo_volume'], 0, 10 ) as $r ) {
				$lines[] = '- ' . $r['keyword'] . ': ' . $r['search_volume'] . ' searches/mo, comp=' . $r['competition'];
			}
		}

		$lines[] = '';
		$lines[] = 'Treat these signals as ground truth about what readers ARE searching today. Do not invent keywords outside this block.';
		return implode( "\n", $lines );
	}

	private function build_user_prompt( array $profile, $topic, $word_count, $extra, array $research ) {
		$lines = array();
		$lines[] = 'Write a new article for the category "' . $profile['name'] . '".';
		$lines[] = 'Topic / working title: ' . $topic;
		$lines[] = 'Target length: ~' . $word_count . ' words.';
		if ( ! empty( $extra ) ) {
			$lines[] = 'Additional brief: ' . sanitize_textarea_field( $extra );
		}
		$lines[] = '';
		$lines[] = 'Use the FRESH KEYWORD SIGNALS block above to ground keyword choices in current search behavior.';
		$lines[] = 'Generate the article now. Return JSON only.';
		return implode( "\n", $lines );
	}

	/**
	 * Parse the assistant's response. Tries 4 progressively-looser strategies:
	 *   1. Strict json_decode on the trimmed text.
	 *   2. Largest "{...}" substring (handles trailing chatter / partial output).
	 *   3. Repaired JSON with literal newlines inside string values escaped.
	 *   4. Per-field regex extraction (survives truncated / incomplete JSON).
	 *
	 * On total failure, strips the JSON header up to "content_html":" and
	 * uses what's left as the post body — so truncated replies still save
	 * something usable instead of dumping raw JSON as the article body.
	 */
	private function parse_response( $text, $fallback_title ) {
		$text = trim( (string) $text );
		if ( 0 === strpos( $text, '```' ) ) {
			$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
			$text = preg_replace( '/\s*```$/', '', $text );
			$text = trim( $text );
		}

		$json = null;

		// 1. Strict.
		$cand = json_decode( $text, true );
		if ( is_array( $cand ) ) {
			$json = $cand;
		}

		// 2. Outermost {...} substring.
		if ( ! $json ) {
			$first = strpos( $text, '{' );
			$last  = strrpos( $text, '}' );
			if ( false !== $first && false !== $last && $last > $first ) {
				$cand = json_decode( substr( $text, $first, $last - $first + 1 ), true );
				if ( is_array( $cand ) ) {
					$json = $cand;
				}
			}
		}

		// 3. Repair: escape stray literal newlines / tabs inside string values.
		if ( ! $json ) {
			$repaired = preg_replace_callback(
				'/"((?:[^"\\\\]|\\\\.)*)"/s',
				function ( $m ) {
					return '"' . str_replace(
						array( "\r\n", "\n", "\r", "\t" ),
						array( '\\n',  '\\n', '\\n', '\\t' ),
						$m[1]
					) . '"';
				},
				$text
			);
			$cand = json_decode( $repaired, true );
			if ( is_array( $cand ) ) {
				$json = $cand;
			}
		}

		// 4. Per-field regex extraction (handles truncated responses).
		if ( ! $json ) {
			$json = $this->regex_extract_fields( $text );
		}

		if ( is_array( $json ) && ! empty( $json['content_html'] ) ) {
			$content = $this->normalize_content_html( (string) $json['content_html'] );
			return array(
				'title'              => sanitize_text_field( isset( $json['title'] ) ? $json['title'] : $fallback_title ),
				'seo_title'          => sanitize_text_field( isset( $json['seo_title'] ) ? $json['seo_title'] : '' ),
				'meta_description'   => sanitize_text_field( isset( $json['meta_description'] ) ? $json['meta_description'] : '' ),
				'focus_keyword'      => sanitize_text_field( isset( $json['focus_keyword'] ) ? $json['focus_keyword'] : '' ),
				'secondary_keywords' => array_map( 'sanitize_text_field', isset( $json['secondary_keywords'] ) && is_array( $json['secondary_keywords'] ) ? $json['secondary_keywords'] : array() ),
				'schema_type'        => sanitize_text_field( isset( $json['schema_type'] ) ? $json['schema_type'] : 'Article' ),
				'tags'               => array_map( 'sanitize_text_field', isset( $json['tags'] ) && is_array( $json['tags'] ) ? $json['tags'] : array() ),
				'content'            => wp_kses_post( $content ),
			);
		}

		// Last resort: strip the JSON header up to "content_html":" and keep the rest.
		$stripped = preg_replace( '/^.*?"content_html"\s*:\s*"/s', '', $text, 1, $count );
		if ( $count ) {
			$stripped = preg_replace( '/"\s*[,}]?\s*$/s', '', $stripped );
			$stripped = $this->normalize_content_html( $stripped );
			return array(
				'title'              => $fallback_title,
				'seo_title'          => '',
				'meta_description'   => '',
				'focus_keyword'      => '',
				'secondary_keywords' => array(),
				'schema_type'        => 'Article',
				'tags'               => array(),
				'content'            => wp_kses_post( $stripped ),
			);
		}

		return array(
			'title'              => $fallback_title,
			'seo_title'          => '',
			'meta_description'   => '',
			'focus_keyword'      => '',
			'secondary_keywords' => array(),
			'schema_type'        => 'Article',
			'tags'               => array(),
			'content'            => wp_kses_post( wpautop( $this->normalize_content_html( $text ) ) ),
		);
	}

	private function should_humanize() {
		// Default to OFF (0). The humanizer is a second Claude API call
		// that adds 60-120s to the critical path — on hosts with strict
		// PHP-FPM timeouts (most managed shared hosts) it's the single
		// step most likely to get killed mid-pipeline, leaving the user
		// with a stuck "running" job and burned API credit but no saved
		// post. The voice rules baked into the main generation prompt
		// already strip most AI tells; opt in to the second pass via
		// Settings → Humanize pass only if your host has 120s+ timeouts.
		return (int) RankWriter_AI_Helpers::get_setting( 'humanize_pass', 0 ) === 1;
	}

	/**
	 * Legacy passthrough. Real humanization lives in
	 * RankWriter_AI_Humanizer::humanize() — this stub just delegates so
	 * any older code paths still work.
	 */
	private function humanize_content( $html, array $profile, $topic ) {
		if ( ! class_exists( 'RankWriter_AI_Humanizer' ) ) {
			return null;
		}
		$humanizer = new RankWriter_AI_Humanizer();
		$opts      = RankWriter_AI_Humanizer::default_options();
		$opts['topic']        = $topic;
		$opts['niche']        = isset( $profile['niche_description'] ) ? wp_trim_words( $profile['niche_description'], 25 ) : '';
		$opts['banned_terms'] = isset( $profile['banned_terms'] ) ? (string) $profile['banned_terms'] : '';
		return $humanizer->humanize( $html, $opts );
	}

	/**
	 * Best-effort field extraction from a malformed / truncated JSON blob.
	 */
	private function regex_extract_fields( $text ) {
		$out = array();
		foreach ( array( 'title', 'seo_title', 'meta_description', 'focus_keyword', 'schema_type', 'content_html' ) as $k ) {
			$pattern = '/"' . preg_quote( $k, '/' ) . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s';
			if ( preg_match( $pattern, $text, $m ) ) {
				$out[ $k ] = stripcslashes( $m[1] );
			}
		}
		if ( preg_match( '/"secondary_keywords"\s*:\s*\[([^\]]*)\]/s', $text, $m ) ) {
			$out['secondary_keywords'] = $this->parse_string_array( $m[1] );
		}
		if ( preg_match( '/"tags"\s*:\s*\[([^\]]*)\]/s', $text, $m ) ) {
			$out['tags'] = $this->parse_string_array( $m[1] );
		}
		// If content_html wasn't matched (truncated mid-string), grab from the
		// opening quote to the end of the buffer.
		if ( empty( $out['content_html'] ) && preg_match( '/"content_html"\s*:\s*"(.+)$/s', $text, $m ) ) {
			$out['content_html'] = stripcslashes( preg_replace( '/"\s*[,}]?\s*$/s', '', $m[1] ) );
		}
		return $out;
	}

	private function parse_string_array( $inside ) {
		preg_match_all( '/"((?:[^"\\\\]|\\\\.)*)"/s', $inside, $m );
		if ( empty( $m[1] ) ) {
			return array();
		}
		return array_map( 'stripcslashes', $m[1] );
	}

	/**
	 * Repair leaked escape sequences before saving content_html.
	 *
	 * Symptom: "nn" appears between paragraphs and "n" before list items.
	 * Root cause: Claude sometimes writes "\n" / "\t" / "\"" as actual
	 * backslash + letter inside the JSON value. After json_decode that
	 * survives as a 2-char "\n" sequence, and then WordPress's internal
	 * data-unslashing inside wp_insert_post strips the backslash, leaving
	 * the letter rendered as raw text in the post body.
	 *
	 * Normalize those leaked escapes here, BEFORE the content reaches
	 * wp_insert_post (and we also wp_slash() at the insert site as a
	 * second layer of defense).
	 */
	private function normalize_content_html( $html ) {
		$html = (string) $html;
		$html = str_replace(
			array( "\\n", "\\t", "\\r", '\\"', "\\'" ),
			array( "\n",  "\t",  "\r",  '"',   "'" ),
			$html
		);
		// Strip any leading JSON-metadata lines that may have leaked into the
		// body when the parser fell back to header-stripping.
		$html = preg_replace( '/^(?:\s*"[a-z_]+"\s*:\s*"[^\n]*",?\s*\n)+/i', '', $html );
		return trim( $html );
	}

	private function ensure_category_term( $name ) {
		$existing = get_term_by( 'name', $name, 'category' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return (int) $existing->term_id;
		}
		$created = wp_insert_term( $name, 'category' );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * Decide which WP category this post belongs in. Resolution priority:
	 *   1. Per-call "create new" override (e.g. autopilot / generate form picker chose "+ Create new")
	 *   2. Per-call existing-term override
	 *   3. Profile's stored default WP category ID
	 *   4. Auto: create / reuse a WP category named after the profile
	 *
	 * This is what stops the plugin from spawning a fresh WP category for
	 * every Category Profile when the user just wants posts grouped under
	 * an existing hub.
	 */
	private function resolve_wp_category( array $profile, array $args ) {
		$new_name = isset( $args['override_wp_category_new'] ) ? trim( (string) $args['override_wp_category_new'] ) : '';
		if ( '' !== $new_name ) {
			return $this->ensure_category_term( $new_name );
		}

		$override_id = isset( $args['override_wp_category_id'] ) ? (int) $args['override_wp_category_id'] : 0;
		if ( $override_id > 0 ) {
			$term = get_term( $override_id, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		if ( ! empty( $profile['wp_category_id'] ) ) {
			$term = get_term( (int) $profile['wp_category_id'], 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		return $this->ensure_category_term( $profile['name'] );
	}
}
