<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claude-powered translator. Turns one post into one or more sibling posts
 * in different languages, preserving SEO structure but rewriting prose for
 * natural readability and cultural fit.
 *
 *   translate_post( $post_id, $target_lang )       → new translated post ID
 *   translate_post_to_many( $post_id, [$codes] )   → batch
 *   ensure_translation_group( $post_id )           → links a post into a group
 *   localize_internal_links( $html, $target_lang ) → remap <a href> within
 *                                                    the site to existing
 *                                                    sibling translations
 *
 * Each translation is saved as its own WordPress post (different slug, own
 * permalink, own SEO meta) and joined to the source via the translation
 * group ID stored as post meta — that group is what powers hreflang +
 * the post-edit meta box.
 *
 * All existing post-generation systems still apply to the translated post:
 *   - SEO plugin meta write (translated title / description / focus keyword)
 *   - JSON-LD schema (in the target language)
 *   - Internal Linker post-pass (now language-aware)
 *   - Compliance check (with banned terms still enforced)
 *   - Featured image (carried over from source)
 */
class RankWriter_AI_Translator {

	/**
	 * Translate one post into one language. Returns the new post ID, or
	 * the existing translated post ID if one already exists in the group.
	 *
	 * @return int|WP_Error
	 */
	public function translate_post( $post_id, $target_lang, $args = array() ) {
		$post_id     = absint( $post_id );
		$target_lang = strtolower( (string) $target_lang );
		$source      = get_post( $post_id );
		if ( ! $source ) {
			return new WP_Error( 'rwai_no_post', __( 'Source post not found.', 'rankwriter-ai' ) );
		}
		$cfg = RankWriter_AI_Language::language( $target_lang );
		if ( ! $cfg ) {
			return new WP_Error( 'rwai_bad_lang', __( 'Unsupported target language.', 'rankwriter-ai' ) );
		}

		$source_lang = RankWriter_AI_Language::get_post_language( $post_id );
		if ( $source_lang === $target_lang ) {
			return new WP_Error( 'rwai_same_lang', __( 'Source and target languages are the same.', 'rankwriter-ai' ) );
		}

		// Don't re-translate — if a sibling already exists in target_lang, return it.
		$existing = $this->existing_translation( $post_id, $target_lang );
		if ( $existing ) {
			return $existing;
		}

		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return new WP_Error( 'rwai_no_client', __( 'Claude client missing.', 'rankwriter-ai' ) );
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$payload = $this->call_claude( $source, $source_lang, $target_lang, $cfg, $args );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		// Insert the translated post as a draft. wp_slash because
		// wp_insert_post unslashes internally.
		$new_id = wp_insert_post( array(
			'post_title'   => wp_slash( $payload['title'] ),
			'post_content' => wp_slash( $payload['content_html'] ),
			'post_excerpt' => wp_slash( $payload['meta_description'] ),
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_name'    => $payload['slug'] ?: sanitize_title( $payload['title'] ),
			'post_author'  => $source->post_author ?: 1,
		), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Carry category + tag taxonomy across.
		$cats = wp_get_post_categories( $post_id );
		if ( $cats ) { wp_set_post_categories( $new_id, $cats ); }
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		if ( $tags ) { wp_set_post_tags( $new_id, $tags ); }

		// Carry featured image.
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		// Language tagging + group join.
		RankWriter_AI_Language::set_post_language( $new_id, $target_lang );
		$group = RankWriter_AI_Language::get_translation_group( $post_id );
		// If source has no explicit group yet, give it one and reuse for the new post.
		if ( $group === (string) $post_id ) {
			$group = 'tg_' . wp_generate_password( 12, false, false );
			RankWriter_AI_Language::set_translation_group( $post_id, $group );
			RankWriter_AI_Language::set_post_language( $post_id, $source_lang );
		}
		RankWriter_AI_Language::set_translation_group( $new_id, $group );
		update_post_meta( $new_id, RankWriter_AI_Language::META_SOURCE, $post_id );
		update_post_meta( $new_id, RankWriter_AI_Language::META_COUNTRY, $cfg['default_country'] );

		// Localize internal links (rewrite any <a href> that points at a
		// sibling whose translation in this language now exists).
		$localized = $this->localize_internal_links( $payload['content_html'], $target_lang );
		if ( $localized !== $payload['content_html'] ) {
			wp_update_post( array( 'ID' => $new_id, 'post_content' => wp_slash( $localized ) ) );
		}

		// Tag the source post too so it sits in the same group.
		update_post_meta( $post_id, RankWriter_AI_Language::META_GROUP, $group );

		// Write SEO meta in the target language via the existing integration.
		if ( class_exists( 'RankWriter_AI_SEO_Integration' ) ) {
			$seo = new RankWriter_AI_SEO_Integration();
			$seo->write_meta( $new_id, array(
				'title'         => $payload['title'],
				'description'   => $payload['meta_description'],
				'focus_keyword' => $payload['focus_keyword'],
				'secondary'     => $payload['secondary_keywords'],
				'og_title'      => $payload['title'],
				'og_description'=> $payload['meta_description'],
				'schema_type'   => 'Article',
			) );
		}

		// JSON-LD schema in the translated content.
		if ( class_exists( 'RankWriter_AI_Schema_Injector' ) ) {
			( new RankWriter_AI_Schema_Injector() )->build_and_save( $new_id, 'Article', $localized, $payload['focus_keyword'] );
		}

		update_post_meta( $new_id, '_rwai_generated', 1 );
		update_post_meta( $new_id, '_rwai_translated_from', $post_id );
		update_post_meta( $new_id, '_rwai_translated_at',   current_time( 'mysql' ) );

		do_action( 'rwai_translation_created', $new_id, $post_id, $target_lang );

		return (int) $new_id;
	}

	public function translate_post_to_many( $post_id, array $target_codes ) {
		$results = array();
		foreach ( $target_codes as $code ) {
			$results[ $code ] = $this->translate_post( $post_id, $code );
		}
		return $results;
	}

	/**
	 * Already-existing sibling translation in the target language, or 0.
	 */
	public function existing_translation( $post_id, $target_lang ) {
		foreach ( RankWriter_AI_Language::get_translations( $post_id ) as $t ) {
			if ( $t['lang'] === $target_lang && $t['post_id'] !== (int) $post_id ) {
				return (int) $t['post_id'];
			}
		}
		return 0;
	}

	/**
	 * For every internal <a href> in the HTML, check if the linked post
	 * has a sibling translation in $target_lang and swap the URL.
	 * Anchor text is left alone (Claude already translated it).
	 */
	public function localize_internal_links( $html, $target_lang ) {
		if ( '' === trim( (string) $html ) ) {
			return $html;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$html = preg_replace_callback(
			'/<a\b([^>]*)href=("([^"]+)"|\'([^\']+)\')([^>]*)>/i',
			function ( $m ) use ( $host, $target_lang ) {
				$before = $m[1];
				$after  = $m[5];
				$href   = '' !== $m[3] ? $m[3] : ( isset( $m[4] ) ? $m[4] : '' );
				if ( '' === $href ) {
					return $m[0];
				}
				$h = wp_parse_url( $href, PHP_URL_HOST );
				if ( $h && $h !== $host ) {
					return $m[0]; // external link, leave it
				}
				$linked_id = url_to_postid( $href );
				if ( ! $linked_id ) {
					return $m[0];
				}
				foreach ( RankWriter_AI_Language::get_translations( $linked_id ) as $t ) {
					if ( $t['lang'] === $target_lang && $t['post_id'] !== $linked_id ) {
						return '<a' . $before . 'href="' . esc_url( $t['url'] ) . '"' . $after . '>';
					}
				}
				return $m[0];
			},
			$html
		);
		return $html;
	}

	/* ============================ Claude call ============================ */

	private function call_claude( $source, $source_lang, $target_lang, $target_cfg, $args ) {
		$source_cfg = RankWriter_AI_Language::language( $source_lang );
		$source_name = $source_cfg ? $source_cfg['name'] : $source_lang;
		$target_name = $target_cfg['name'];
		$rtl_note    = ! empty( $target_cfg['rtl'] ) ? "\n- The target language is right-to-left. Ensure proper RTL flow in punctuation and number direction." : '';

		$system = "You are a senior bilingual editor who translates {$source_name} → {$target_name} for a publication that targets {$target_cfg['default_country']}. You DO NOT do literal word-for-word translation. You rewrite each sentence so it sounds like an experienced {$target_name} writer wrote it from scratch, with cultural references, units, currencies, idioms, and examples adapted to the target audience.\n\n"
			. "## Hard rules\n"
			. "- Preserve EVERY HTML tag and its attributes exactly. <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a href=\"...\">, <table>, etc.\n"
			. "- Translate anchor text inside <a> tags but KEEP THE href URL exactly as it appears in the source. The plugin will remap internal links later.\n"
			. "- Preserve every fact, number, date, name, dollar amount. Convert currency / units only if there is a clean local equivalent (\$50 USD → about €46 EUR is acceptable; \"$50\" → \"50 €\" without context is not).\n"
			. "- Adapt examples: a US-specific anecdote should be reworded into a local-relevant scenario when the prose allows.\n"
			. "- Idioms: never translate idioms word-for-word. Use the natural {$target_name} equivalent.\n"
			. "- The translated text must feel native, not LLM-translated. A {$target_name} speaker on Twitter should not be able to tell this was machine-translated.\n"
			. "- Do NOT add or remove paragraphs. Same structure, translated content." . $rtl_note . "\n\n"
			. "## Output rules\n"
			. "Return ONLY valid JSON, no preamble, no markdown fences:\n"
			. "{\n"
			. "  \"title\":              \"<localized H1 title>\",\n"
			. "  \"slug\":               \"<URL-friendly slug in the target language, lowercase, hyphens>\",\n"
			. "  \"meta_description\":   \"<150-160 char localized meta description>\",\n"
			. "  \"focus_keyword\":      \"<single primary keyword in the target language>\",\n"
			. "  \"secondary_keywords\": [\"<kw1>\", \"<kw2>\", \"<kw3>\"],\n"
			. "  \"content_html\":       \"<full translated article HTML, same structure as the source>\"\n"
			. "}\n";

		$user = "Translate this article to {$target_name}.\n\n"
			. "--- SOURCE TITLE ---\n" . $source->post_title . "\n\n"
			. "--- SOURCE CONTENT (HTML) ---\n" . $source->post_content . "\n\n"
			. "Return JSON only.";

		$client = new RankWriter_AI_Claude_Client();
		$text   = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_response( $text );
	}

	private function parse_response( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );

		$json = json_decode( $text, true );
		if ( ! is_array( $json ) ) {
			if ( preg_match( '/\{.*\}/s', $text, $m ) ) {
				$json = json_decode( $m[0], true );
			}
		}
		if ( ! is_array( $json ) || empty( $json['content_html'] ) ) {
			return new WP_Error( 'rwai_bad_response', __( 'Could not parse the translation response.', 'rankwriter-ai' ) );
		}

		// Same normalize step the main content generator uses — strip leaked
		// \n / \t / \" escapes so wp_insert_post doesn't render them as text.
		$html = (string) $json['content_html'];
		$html = str_replace(
			array( "\\n", "\\t", "\\r", '\\"', "\\'" ),
			array( "\n",  "\t",  "\r",  '"',   "'" ),
			$html
		);

		return array(
			'title'              => sanitize_text_field( (string) ( $json['title'] ?? '' ) ),
			'slug'               => sanitize_title( (string) ( $json['slug'] ?? '' ) ),
			'meta_description'   => sanitize_text_field( (string) ( $json['meta_description'] ?? '' ) ),
			'focus_keyword'      => sanitize_text_field( (string) ( $json['focus_keyword'] ?? '' ) ),
			'secondary_keywords' => array_map( 'sanitize_text_field', array_filter( (array) ( $json['secondary_keywords'] ?? array() ), 'is_string' ) ),
			'content_html'       => wp_kses_post( $html ),
		);
	}
}
