<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Discover Optimization Engine.
 *
 *   score_post( $post_id )         → 5 scores + per-dimension diagnostics
 *   score_content( $title, $html,  → same shape, for unsaved drafts
 *                  $img_url )
 *   recommend_hooks( $topic )      → Claude returns 4 emotional Discover hooks
 *   rewrite_intro( $intro, $topic) → Claude rewrites the first paragraph for
 *                                    Discover engagement
 *
 * Scoring dimensions (each 0-100):
 *   1. Mobile engagement   — paragraph & sentence length, sub-heading
 *                            frequency, list density, max-paragraph penalty
 *   2. Freshness           — post age, current-year mentions, date-aware
 *                            language
 *   3. Emotional engagement — first-paragraph hook strength, emotional
 *                            triggers, "you" usage, intro question, intro
 *                            length cap
 *   4. Image readiness     — featured image present, width ≥ 1200px,
 *                            aspect ratio (16:9 / 4:3 / 3:2), alt text
 *   5. Overall Discover score = weighted average of the four
 *      (mobile 30% · freshness 20% · emotional 30% · image 20%)
 *
 * Design notes:
 *   - All scoring is pure PHP, instant. No API calls inside score_post()
 *     or score_content(). The Claude calls are explicitly opt-in.
 *   - Image-readiness math uses the WP attachment metadata cached by
 *     wp_get_attachment_metadata(); no HTTP round-trips.
 */
class RankWriter_AI_Discover_Optimizer {

	/* ============================ Public API ============================ */

	/**
	 * Score a saved post.
	 *
	 * @return array Full diagnostic + score row.
	 */
	public function score_post( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return $this->empty_result();
		}
		$thumb_id  = get_post_thumbnail_id( $post_id );
		$image_meta = $this->resolve_image_meta( $thumb_id );

		$result = $this->compute(
			$post->post_title,
			$post->post_content,
			$image_meta,
			$post->post_date_gmt
		);
		$result['post_id'] = $post_id;
		return $result;
	}

	/**
	 * Score unsaved content (used by the admin tool + by the content
	 * generator at write-time).
	 */
	public function score_content( $title, $content_html, $featured_image_url = '', $published_at = '' ) {
		$image_meta = $this->resolve_image_meta_from_url( $featured_image_url );
		return $this->compute( $title, $content_html, $image_meta, $published_at );
	}

	/**
	 * @param string $topic
	 * @param string $intent
	 * @return array|WP_Error 4 hook variations.
	 */
	public function recommend_hooks( $topic, $intent = '' ) {
		$client = $this->client();
		if ( ! $client || ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$system = "You write opening hooks for Google Discover articles. Discover is a mobile feed where users SCROLL, they don't search — your only job is to stop the scroll with a concrete, emotionally resonant first sentence that delivers on its promise.\n\n"
			. "Hook rules (non-negotiable):\n"
			. "- Open with a specific person, number, scenario, or surprising fact. Never with \"In today's...\", \"In the world of...\", \"Have you ever...\".\n"
			. "- Maximum 25 words for the opening sentence. Punchy beats elaborate.\n"
			. "- One concrete detail in the first line: a real name, a real number, a real moment.\n"
			. "- Use \"you\" or a vivid third person — never generic plural.\n"
			. "- No clickbait. No \"You won't believe\". No \"Doctors hate\". No \"What happened next\".\n"
			. "- Promise must be deliverable in the article.";

		$user = "Topic: \"$topic\""
			. ( $intent ? "\nSearch intent: $intent" : '' )
			. "\n\nWrite 4 distinct opening hooks (one paragraph each, 2-4 sentences). Return ONLY a JSON array of strings. No preamble, no markdown.";

		$text = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_string_list( $text );
	}

	/**
	 * Rewrite an existing intro for Discover engagement.
	 */
	public function rewrite_intro( $current_intro, $topic ) {
		$client = $this->client();
		if ( ! $client || ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$current_intro = trim( wp_strip_all_tags( (string) $current_intro ) );
		if ( '' === $current_intro ) {
			return new WP_Error( 'rwai_no_intro', __( 'Empty intro.', 'rankwriter-ai' ) );
		}
		$system = "You rewrite article intros to maximize Google Discover engagement. Discover is a mobile feed. Your rewrite must:\n"
			. "- Start with a concrete person, number, or scenario in the first sentence.\n"
			. "- Keep the first paragraph under 90 words.\n"
			. "- Use short sentences (under 20 words).\n"
			. "- Use \"you\" or vivid third person.\n"
			. "- Tease the article's main payoff without giving it all away.\n"
			. "- Preserve all facts. Don't invent numbers or names.\n"
			. "- No clickbait phrases. Must be defensible to a critical reader.\n"
			. "Return ONLY the rewritten paragraph (plain text, no JSON, no markdown, no preamble).";

		$user = "Topic: \"$topic\"\n\nCurrent intro:\n$current_intro\n\nRewrite for Discover.";

		$text = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return trim( (string) $text );
	}

	/* ============================ Core scoring ============================ */

	private function compute( $title, $content_html, $image_meta, $published_at ) {
		$mobile    = $this->score_mobile_engagement( $content_html );
		$freshness = $this->score_freshness( $content_html, $published_at );
		$emotion   = $this->score_emotional_engagement( $title, $content_html );
		$image     = $this->score_image_readiness( $image_meta );

		$overall = (int) round(
			$mobile['score']    * 0.30 +
			$freshness['score'] * 0.20 +
			$emotion['score']   * 0.30 +
			$image['score']     * 0.20
		);

		return array(
			'overall'              => $overall,
			'band'                 => $this->band( $overall ),
			'mobile_engagement'    => $mobile,
			'freshness'            => $freshness,
			'emotional_engagement' => $emotion,
			'image_readiness'      => $image,
			'recommendations'      => $this->recommendations( $mobile, $freshness, $emotion, $image ),
		);
	}

	private function score_mobile_engagement( $html ) {
		$score   = 50;
		$reasons = array();

		// Paragraph-level stats.
		preg_match_all( '#<p\b[^>]*>(.+?)</p>#is', $html, $pmatches );
		$para_word_counts = array();
		$max_para         = 0;
		foreach ( (array) ( $pmatches[1] ?? array() ) as $p ) {
			$wc = str_word_count( wp_strip_all_tags( $p ) );
			if ( $wc > 0 ) {
				$para_word_counts[] = $wc;
				$max_para           = max( $max_para, $wc );
			}
		}
		$avg_para_words = $para_word_counts ? (int) round( array_sum( $para_word_counts ) / count( $para_word_counts ) ) : 0;

		// Average paragraph length.
		if ( $avg_para_words === 0 ) {
			$score -= 15;
			$reasons[] = __( 'No paragraphs detected — content may be empty or unstructured.', 'rankwriter-ai' );
		} elseif ( $avg_para_words <= 60 ) {
			$score += 25;
		} elseif ( $avg_para_words <= 100 ) {
			$score += 15;
			$reasons[] = __( 'Average paragraph length is OK — could be tighter for mobile.', 'rankwriter-ai' );
		} elseif ( $avg_para_words <= 150 ) {
			$score += 5;
			$reasons[] = __( 'Paragraphs are getting long — Discover users skim on mobile.', 'rankwriter-ai' );
		} else {
			$score -= 15;
			$reasons[] = __( 'Paragraphs are too long for mobile readers. Break them up.', 'rankwriter-ai' );
		}

		// Max paragraph penalty — any single paragraph >200 words is a brick of text.
		if ( $max_para > 200 ) {
			$score -= 10;
			$reasons[] = sprintf( __( 'A single paragraph hits %d words — split it for mobile.', 'rankwriter-ai' ), $max_para );
		}

		// Sub-heading frequency.
		$h2_count = preg_match_all( '/<h2[\s>]/i', $html );
		$h3_count = preg_match_all( '/<h3[\s>]/i', $html );
		$total_words = str_word_count( wp_strip_all_tags( $html ) );
		$headings_total = $h2_count + $h3_count;
		$ideal_headings = max( 1, (int) round( $total_words / 250 ) );
		if ( $headings_total >= $ideal_headings && $total_words > 400 ) {
			$score += 15;
		} elseif ( $total_words > 800 && $headings_total < 3 ) {
			$score -= 8;
			$reasons[] = __( 'Not enough sub-headings for a mobile reader to scan.', 'rankwriter-ai' );
		}

		// List density.
		$list_count = preg_match_all( '/<(ul|ol)[\s>]/i', $html );
		if ( $total_words > 600 && $list_count >= 1 ) {
			$score += 10;
		} elseif ( $total_words > 1200 && $list_count === 0 ) {
			$score -= 5;
			$reasons[] = __( 'Long article with zero lists — add at least one bulleted list.', 'rankwriter-ai' );
		}

		// Bold usage (skim aids).
		$bold_count = preg_match_all( '/<(strong|b)[\s>]/i', $html );
		if ( $bold_count >= 2 && $bold_count <= 12 ) {
			$score += 5;
		} elseif ( $bold_count > 20 ) {
			$score -= 5;
			$reasons[] = __( 'Too much bold text — emphasis loses meaning when overused.', 'rankwriter-ai' );
		}

		// Sentence length sampling on plain content.
		$plain      = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) );
		$sentences  = preg_split( '/(?<=[.!?])\s+/', $plain );
		$sent_words = array();
		foreach ( (array) $sentences as $s ) {
			$w = str_word_count( $s );
			if ( $w > 0 ) {
				$sent_words[] = $w;
			}
		}
		$avg_sent = $sent_words ? array_sum( $sent_words ) / count( $sent_words ) : 0;
		if ( $avg_sent > 0 && $avg_sent <= 20 ) {
			$score += 10;
		} elseif ( $avg_sent > 30 ) {
			$score -= 10;
			$reasons[] = sprintf( __( 'Average sentence length is %d words — Discover wants under 20.', 'rankwriter-ai' ), (int) $avg_sent );
		}

		return array(
			'score'              => max( 0, min( 100, (int) round( $score ) ) ),
			'avg_paragraph_words'=> $avg_para_words,
			'max_paragraph_words'=> $max_para,
			'avg_sentence_words' => (int) round( $avg_sent ),
			'headings_count'     => $headings_total,
			'list_count'         => (int) $list_count,
			'bold_count'         => (int) $bold_count,
			'reasons'            => $reasons,
		);
	}

	private function score_freshness( $html, $published_at ) {
		$score   = 30;
		$reasons = array();

		$age_days = 0;
		if ( $published_at ) {
			$ts = is_numeric( $published_at ) ? (int) $published_at : strtotime( $published_at );
			if ( $ts > 0 ) {
				$age_days = max( 0, (int) floor( ( time() - $ts ) / DAY_IN_SECONDS ) );
			}
		}

		if ( $age_days <= 7 ) {
			$score += 60;
		} elseif ( $age_days <= 30 ) {
			$score += 40;
		} elseif ( $age_days <= 90 ) {
			$score += 25;
			$reasons[] = __( 'Article is over a month old — Discover prefers fresh content.', 'rankwriter-ai' );
		} elseif ( $age_days <= 180 ) {
			$score += 10;
			$reasons[] = __( 'Article is over 3 months old — consider refreshing it.', 'rankwriter-ai' );
		} elseif ( $age_days <= 365 ) {
			$score -= 5;
			$reasons[] = __( 'Article is over 6 months old.', 'rankwriter-ai' );
		} else {
			$score -= 15;
			$reasons[] = __( 'Article is over a year old — likely needs a content refresh.', 'rankwriter-ai' );
		}

		// Current-year mention bonus.
		$current_year = (int) gmdate( 'Y' );
		if ( strpos( $html, (string) $current_year ) !== false ) {
			$score += 10;
		} elseif ( strpos( $html, (string) ( $current_year - 1 ) ) !== false ) {
			$score += 4;
			$reasons[] = sprintf( __( 'Content references %d but not %d — consider updating year references.', 'rankwriter-ai' ), $current_year - 1, $current_year );
		}

		// Date-aware language bonus.
		if ( preg_match( '/\b(currently|right now|as of|recently|this week|this month|this year)\b/i', $html ) ) {
			$score += 5;
		}

		return array(
			'score'    => max( 0, min( 100, (int) round( $score ) ) ),
			'age_days' => $age_days,
			'reasons'  => $reasons,
		);
	}

	private function score_emotional_engagement( $title, $html ) {
		$score   = 40;
		$reasons = array();

		// Extract the first paragraph (or first 200 words of plain content if no <p>).
		if ( preg_match( '#<p\b[^>]*>(.+?)</p>#is', $html, $m ) ) {
			$first_para = wp_strip_all_tags( $m[1] );
		} else {
			$first_para = wp_trim_words( wp_strip_all_tags( $html ), 200 );
		}
		$first_para = trim( preg_replace( '/\s+/', ' ', $first_para ) );
		$first_para_words = str_word_count( $first_para );

		// Hook strength: concrete opening (number, dollar sign, name-like proper noun, year)
		$has_number   = (bool) preg_match( '/\d/', substr( $first_para, 0, 80 ) );
		$has_money    = (bool) preg_match( '/\$\d/', $first_para );
		$has_proper   = (bool) preg_match( '/\b[A-Z][a-z]+\s+[A-Z][a-z]+/', substr( $first_para, 0, 120 ) );
		$starts_with_action = (bool) preg_match( '/^\s*(imagine|picture|when|every|most|the |a |you|i\b|on |it took)/i', $first_para );

		if ( $has_number || $has_money ) {
			$score += 20;
		}
		if ( $has_proper ) {
			$score += 10;
		}
		if ( $starts_with_action ) {
			$score += 8;
		}

		// Generic-opener penalty.
		if ( preg_match( '/^\s*(in today\'?s|in the world of|with .+ becoming|have you ever|are you tired)/i', $first_para ) ) {
			$score -= 20;
			$reasons[] = __( 'Generic opener detected — rewrite to start with a concrete situation, number, or person.', 'rankwriter-ai' );
		}

		// First-paragraph length cap (Discover intros should be punchy).
		if ( $first_para_words > 0 && $first_para_words <= 90 ) {
			$score += 10;
		} elseif ( $first_para_words > 130 ) {
			$score -= 10;
			$reasons[] = sprintf( __( 'First paragraph is %d words — keep it under 90 for Discover.', 'rankwriter-ai' ), $first_para_words );
		}

		// "you" / "your" in intro.
		if ( preg_match( '/\b(you|your)\b/i', $first_para ) ) {
			$score += 5;
		}

		// Emotional trigger count via Title Intelligence (if loaded).
		if ( class_exists( 'RankWriter_AI_Title_Intelligence' ) ) {
			$ti          = new RankWriter_AI_Title_Intelligence();
			$ti_analysis = $ti->analyze( $title );
			$triggers    = count( $ti_analysis['emotional_triggers'] );
			$score      += min( 15, $triggers * 4 );
			if ( ! empty( $ti_analysis['clickbait']['is_clickbait'] ) ) {
				$score -= 25;
				$reasons[] = __( 'Title is flagged as clickbait — Discover de-prioritizes misleading headlines.', 'rankwriter-ai' );
			}
		}

		// Intro question (engagement signal).
		if ( preg_match( '/\?/', $first_para ) ) {
			$score += 5;
		}

		// Title length sanity (Discover ranges 50-70 well).
		$title_len = strlen( $title );
		if ( $title_len >= 50 && $title_len <= 70 ) {
			$score += 8;
		} elseif ( $title_len < 35 || $title_len > 95 ) {
			$score -= 6;
			$reasons[] = sprintf( __( 'Title is %d chars — Discover prefers 50-70.', 'rankwriter-ai' ), $title_len );
		}

		return array(
			'score'             => max( 0, min( 100, (int) round( $score ) ) ),
			'first_paragraph_words' => $first_para_words,
			'title_length'      => $title_len,
			'has_concrete_open' => $has_number || $has_money || $has_proper,
			'reasons'           => $reasons,
		);
	}

	private function score_image_readiness( $image_meta ) {
		$score   = 0;
		$reasons = array();

		if ( empty( $image_meta['has_image'] ) ) {
			$reasons[] = __( 'No featured image — Discover almost never surfaces articles without a big image.', 'rankwriter-ai' );
			return array(
				'score'    => 0,
				'has_image'=> false,
				'reasons'  => $reasons,
			);
		}
		$score += 50;

		$w = (int) ( $image_meta['width']  ?? 0 );
		$h = (int) ( $image_meta['height'] ?? 0 );

		if ( $w >= 1200 ) {
			$score += 25;
		} elseif ( $w >= 900 ) {
			$score += 12;
			$reasons[] = sprintf( __( 'Featured image is %d px wide — Google recommends at least 1200 px for Discover.', 'rankwriter-ai' ), $w );
		} elseif ( $w > 0 ) {
			$score -= 10;
			$reasons[] = sprintf( __( 'Featured image is only %d px wide — too small for Discover.', 'rankwriter-ai' ), $w );
		}

		if ( $w > 0 && $h > 0 ) {
			$ratio = $w / $h;
			$ideal = array( 16 / 9, 4 / 3, 3 / 2 );
			$tolerant = false;
			foreach ( $ideal as $ir ) {
				if ( abs( $ratio - $ir ) < 0.15 ) {
					$tolerant = true;
					break;
				}
			}
			if ( $tolerant ) {
				$score += 15;
			} else {
				$score -= 5;
				$reasons[] = sprintf( __( 'Aspect ratio %s isn\'t a typical landscape ratio (16:9, 4:3, 3:2).', 'rankwriter-ai' ), number_format( $ratio, 2 ) );
			}
		}

		if ( ! empty( $image_meta['alt'] ) ) {
			$score += 10;
		} else {
			$reasons[] = __( 'Featured image has no alt text.', 'rankwriter-ai' );
		}

		return array(
			'score'     => max( 0, min( 100, (int) round( $score ) ) ),
			'has_image' => true,
			'width'     => $w,
			'height'    => $h,
			'alt'       => isset( $image_meta['alt'] ) ? (string) $image_meta['alt'] : '',
			'reasons'   => $reasons,
		);
	}

	private function recommendations( $mobile, $freshness, $emotion, $image ) {
		$tips = array();
		if ( $mobile['score'] < 60 ) {
			$tips[] = __( 'Tighten paragraphs (aim for 30-60 words) and add more sub-headings so mobile readers can scan.', 'rankwriter-ai' );
		}
		if ( $freshness['score'] < 50 ) {
			$tips[] = __( 'Refresh dated references, add current-year stats, or republish if substantially updated.', 'rankwriter-ai' );
		}
		if ( $emotion['score'] < 60 ) {
			$tips[] = __( 'Rewrite the first paragraph with a concrete person, number, or scenario in the opening line.', 'rankwriter-ai' );
		}
		if ( $image['score'] < 60 ) {
			if ( empty( $image['has_image'] ) ) {
				$tips[] = __( 'Add a featured image at least 1200 px wide in a 16:9 / 4:3 / 3:2 aspect ratio.', 'rankwriter-ai' );
			} elseif ( ! empty( $image['width'] ) && $image['width'] < 1200 ) {
				$tips[] = __( 'Upload a larger featured image — 1200 px wide minimum for Discover thumbnails.', 'rankwriter-ai' );
			}
		}
		return $tips;
	}

	/**
	 * Returns a prompt-ready block injected by the Content Generator.
	 */
	public static function generator_rules_block() {
		return "## Google Discover optimization rules\n"
			. "- First sentence: open with a concrete person, number, scenario, or surprising fact. Never with \"In today's...\", \"In the world of...\", \"Have you ever...\".\n"
			. "- First paragraph: under 90 words. Hook the reader before the fold on mobile.\n"
			. "- Paragraphs: aim for 30-60 words each. No paragraph over 150 words.\n"
			. "- Sentences: average under 20 words. Mix in short fragments for rhythm.\n"
			. "- Add a sub-heading roughly every 200-300 words so mobile readers can scan.\n"
			. "- Use at least one bulleted or numbered list per article over 600 words.\n"
			. "- Reference the current year explicitly when timely.\n"
			. "- Use \"you\" or vivid third person — never generic plural \"users\".\n"
			. "- Title: 50-70 characters, human, story-like (not keyword-stuffed).\n"
			. "- The article must deliver on every promise the title makes — Discover demotes misleading content.";
	}

	/* ============================ Helpers ============================ */

	private function band( $score ) {
		if ( $score >= 75 ) {
			return 'ok';
		}
		if ( $score >= 50 ) {
			return 'warn';
		}
		return 'bad';
	}

	private function client() {
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return null;
		}
		return new RankWriter_AI_Claude_Client();
	}

	private function resolve_image_meta( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return array( 'has_image' => false );
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		return array(
			'has_image' => true,
			'width'     => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'    => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'alt'       => $alt,
		);
	}

	private function resolve_image_meta_from_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return array( 'has_image' => false );
		}
		// Try to resolve as a local attachment first.
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			return $this->resolve_image_meta( $attachment_id );
		}
		// Unknown remote URL — assume image exists but skip dimensions.
		return array( 'has_image' => true, 'width' => 0, 'height' => 0, 'alt' => '' );
	}

	private function parse_string_list( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );
		$parsed = json_decode( $text, true );
		if ( ! is_array( $parsed ) && preg_match( '/\[.*\]/s', $text, $m ) ) {
			$parsed = json_decode( $m[0], true );
		}
		if ( ! is_array( $parsed ) ) {
			$parsed = array();
			foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
				$line = trim( $line, " \t\"'•-*0123456789.)" );
				if ( '' !== $line && strlen( $line ) <= 500 ) {
					$parsed[] = $line;
				}
			}
		}
		$out = array();
		foreach ( $parsed as $item ) {
			if ( is_string( $item ) ) {
				$item = trim( $item );
				if ( '' !== $item ) {
					$out[] = sanitize_text_field( $item );
				}
			}
		}
		return $out;
	}

	private function empty_result() {
		return array(
			'overall'              => 0,
			'band'                 => 'bad',
			'mobile_engagement'    => array( 'score' => 0, 'reasons' => array() ),
			'freshness'            => array( 'score' => 0, 'reasons' => array() ),
			'emotional_engagement' => array( 'score' => 0, 'reasons' => array() ),
			'image_readiness'      => array( 'score' => 0, 'has_image' => false, 'reasons' => array() ),
			'recommendations'      => array(),
		);
	}
}
