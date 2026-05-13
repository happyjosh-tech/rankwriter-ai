<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Memory + Brand Voice System.
 *
 * Sits on top of the existing Blog Analyzer (which captures sentence-length
 * + readability signals), the Humanizer (tone / personality / readability),
 * and the Category Profiles (per-niche overrides), unifying them into a
 * single Brand Voice document that:
 *
 *   1. Composes a coherent voice from those underlying signals
 *   2. Tracks formatting + tone drift across each newly published post
 *      (lightweight rolling-window memory)
 *   3. Exports a compact system-prompt block the Content Generator can
 *      inline so every new article inherits the brand voice
 *
 * Storage: a single option (`rwai_voice_profile`). That's the entire
 * footprint — one row, one read, no custom tables.
 */
class RankWriter_AI_Voice_Memory {

	const OPTION_PROFILE   = 'rwai_voice_profile';
	const META_POST_TONE   = '_rwai_voice_tone';      // detected tone of a post
	const META_POST_FMT    = '_rwai_voice_fmt';       // captured fmt metrics
	const TONE_HISTORY_LEN = 20;

	const TONE_PROFESSIONAL  = 'professional';
	const TONE_CONVERSATIONAL= 'conversational';
	const TONE_EMOTIONAL     = 'emotional';
	const TONE_EDUCATIONAL   = 'educational';
	const TONE_AUTHORITY     = 'authority';
	const TONE_STORYTELLING  = 'storytelling';

	public function register_hooks() {
		// Update formatting + tone memory when a post is published.
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 20, 3 );
	}

	public static function supported_tones() {
		return array(
			self::TONE_PROFESSIONAL   => array(
				'label'    => __( 'Professional', 'rankwriter-ai' ),
				'summary'  => __( 'Polished, neutral, third-person — fits B2B / finance / corporate.', 'rankwriter-ai' ),
			),
			self::TONE_CONVERSATIONAL => array(
				'label'    => __( 'Conversational', 'rankwriter-ai' ),
				'summary'  => __( 'Warm, direct, second-person — fits lifestyle / how-to / blogs.', 'rankwriter-ai' ),
			),
			self::TONE_EMOTIONAL      => array(
				'label'    => __( 'Emotional', 'rankwriter-ai' ),
				'summary'  => __( 'Vivid, personal, evocative — fits personal essays / wellness.', 'rankwriter-ai' ),
			),
			self::TONE_EDUCATIONAL    => array(
				'label'    => __( 'Educational', 'rankwriter-ai' ),
				'summary'  => __( 'Clear, structured, examples-first — fits tutorials / explainers.', 'rankwriter-ai' ),
			),
			self::TONE_AUTHORITY      => array(
				'label'    => __( 'Authority-based', 'rankwriter-ai' ),
				'summary'  => __( 'Citation-heavy, expert framing, data-led — fits research / medical / legal.', 'rankwriter-ai' ),
			),
			self::TONE_STORYTELLING   => array(
				'label'    => __( 'Storytelling', 'rankwriter-ai' ),
				'summary'  => __( 'Narrative arc, scene-setting, characters — fits case studies / brand journalism.', 'rankwriter-ai' ),
			),
		);
	}

	/* ============================ Voice presets ============================ */

	/**
	 * Each preset bundles tone + structural defaults. Applying a preset
	 * writes the package into the profile (and into the Humanizer settings
	 * for downstream compatibility).
	 */
	public static function presets() {
		return array(
			self::TONE_PROFESSIONAL => array(
				'tone'              => self::TONE_PROFESSIONAL,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'medium',
				'readability'       => 'off',
				'fmt'               => array(
					'avg_paragraph_words' => 70,
					'avg_sentence_words'  => 20,
					'list_usage_rate'     => 0.25,
					'first_person_rate'   => 0.04,
				),
			),
			self::TONE_CONVERSATIONAL => array(
				'tone'              => self::TONE_CONVERSATIONAL,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'strong',
				'readability'       => 'simple',
				'fmt'               => array(
					'avg_paragraph_words' => 45,
					'avg_sentence_words'  => 14,
					'list_usage_rate'     => 0.35,
					'first_person_rate'   => 0.12,
				),
			),
			self::TONE_EMOTIONAL => array(
				'tone'              => self::TONE_EMOTIONAL,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'strong',
				'readability'       => 'off',
				'fmt'               => array(
					'avg_paragraph_words' => 55,
					'avg_sentence_words'  => 16,
					'list_usage_rate'     => 0.2,
					'first_person_rate'   => 0.18,
				),
			),
			self::TONE_EDUCATIONAL => array(
				'tone'              => self::TONE_EDUCATIONAL,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'medium',
				'readability'       => 'simple',
				'fmt'               => array(
					'avg_paragraph_words' => 60,
					'avg_sentence_words'  => 18,
					'list_usage_rate'     => 0.5,
					'first_person_rate'   => 0.06,
				),
			),
			self::TONE_AUTHORITY => array(
				'tone'              => self::TONE_AUTHORITY,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'medium',
				'readability'       => 'off',
				'fmt'               => array(
					'avg_paragraph_words' => 80,
					'avg_sentence_words'  => 22,
					'list_usage_rate'     => 0.2,
					'first_person_rate'   => 0.02,
				),
			),
			self::TONE_STORYTELLING => array(
				'tone'              => self::TONE_STORYTELLING,
				'personality'       => 'experienced_practitioner',
				'humanize_strength' => 'strong',
				'readability'       => 'off',
				'fmt'               => array(
					'avg_paragraph_words' => 75,
					'avg_sentence_words'  => 19,
					'list_usage_rate'     => 0.15,
					'first_person_rate'   => 0.15,
				),
			),
		);
	}

	/* ============================ Get / save / reset ============================ */

	public function get_profile() {
		$defaults = array(
			'generated_at'        => '',
			'brand_tagline'       => '',
			'brand_pillars'       => '', // newline list, user-editable
			'brand_avoid'         => '', // words/topics to avoid
			'primary_tone'        => self::TONE_PROFESSIONAL,
			'secondary_tone'      => '',
			'tone_history'        => array(), // [{post_id,tone,detected_at}, ...]
			'fmt'                 => array(
				'avg_paragraph_words' => 0,
				'avg_sentence_words'  => 0,
				'list_usage_rate'     => 0,
				'h2_per_article'      => 0,
				'h3_per_article'      => 0,
				'first_person_rate'   => 0,
				'samples_observed'    => 0,
			),
			'category_overrides'  => array(), // [cat_id => ['tone'=>..., 'note'=>...]]
			'applied_preset'      => '',
			'last_calibrated'     => '',
			'auto_learn'          => 1, // update memory on every new post
		);
		$saved = get_option( self::OPTION_PROFILE, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public function save_profile( array $profile ) {
		update_option( self::OPTION_PROFILE, $profile, false );
		return $profile;
	}

	public function reset() {
		delete_option( self::OPTION_PROFILE );
	}

	public function apply_preset( $preset_id ) {
		$presets = self::presets();
		if ( ! isset( $presets[ $preset_id ] ) ) {
			return new WP_Error( 'rwai_bad_preset', __( 'Unknown preset.', 'rankwriter-ai' ) );
		}
		$p = $presets[ $preset_id ];
		$profile = $this->get_profile();
		$profile['primary_tone']    = $p['tone'];
		$profile['fmt']             = wp_parse_args( $p['fmt'], $profile['fmt'] );
		$profile['applied_preset']  = $preset_id;
		$profile['last_calibrated'] = current_time( 'mysql' );

		// Mirror compatible fields back into the Humanizer settings so any
		// generator path that reads them directly stays in sync.
		if ( class_exists( 'RankWriter_AI_Helpers' ) ) {
			$humanizer_patch = array(
				'humanize_tone'        => $p['tone'],
				'humanize_personality' => $p['personality'],
				'humanize_strength'    => $p['humanize_strength'],
				'humanize_readability' => $p['readability'],
			);
			$current = RankWriter_AI_Helpers::get_settings();
			RankWriter_AI_Helpers::update_settings( array_merge( $current, $humanizer_patch ) );
		}

		$this->save_profile( $profile );
		return $profile;
	}

	/* ============================ Calibration from existing posts ============================ */

	/**
	 * Re-build the Voice Profile by sampling up to N recent posts. This is
	 * what the user clicks "Calibrate now" to run — it averages formatting
	 * metrics, detects dominant tone, and seeds the memory from the entire
	 * history rather than relying on incremental updates alone.
	 */
	public function calibrate( $sample_size = 25 ) {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 5, min( 200, (int) $sample_size ) ),
		) );
		if ( empty( $posts ) ) {
			return new WP_Error( 'rwai_no_posts', __( 'No published posts to calibrate against.', 'rankwriter-ai' ) );
		}

		$fmt_totals = array(
			'paragraph_words' => array(),
			'sentence_words'  => array(),
			'list_usage'      => 0,
			'list_count'      => 0,
			'h2_count'        => array(),
			'h3_count'        => array(),
			'first_person'    => array(),
		);
		$tone_counts = array();

		foreach ( $posts as $p ) {
			$metrics = $this->measure_formatting( $p->post_content );
			$fmt_totals['paragraph_words'][] = $metrics['avg_paragraph_words'];
			$fmt_totals['sentence_words'][]  = $metrics['avg_sentence_words'];
			$fmt_totals['list_count']        += $metrics['has_list'] ? 1 : 0;
			$fmt_totals['list_usage']        += $metrics['list_count'];
			$fmt_totals['h2_count'][]        = $metrics['h2_count'];
			$fmt_totals['h3_count'][]        = $metrics['h3_count'];
			$fmt_totals['first_person'][]    = $metrics['first_person_rate'];

			$tone = $this->detect_tone( $p->post_content );
			$tone_counts[ $tone ] = ( $tone_counts[ $tone ] ?? 0 ) + 1;
			update_post_meta( $p->ID, self::META_POST_TONE, $tone );
			update_post_meta( $p->ID, self::META_POST_FMT, $metrics );
		}

		arsort( $tone_counts );
		$primary  = (string) ( key( $tone_counts ) ?: self::TONE_PROFESSIONAL );
		next( $tone_counts );
		$secondary = (string) ( key( $tone_counts ) ?: '' );

		$profile = $this->get_profile();
		$profile['primary_tone']    = $primary;
		$profile['secondary_tone']  = $secondary === $primary ? '' : $secondary;
		$profile['fmt'] = array(
			'avg_paragraph_words' => (int) round( $this->mean( $fmt_totals['paragraph_words'] ) ),
			'avg_sentence_words'  => (int) round( $this->mean( $fmt_totals['sentence_words'] ) ),
			'list_usage_rate'     => round( count( $posts ) ? ( $fmt_totals['list_count'] / count( $posts ) ) : 0, 2 ),
			'h2_per_article'      => (int) round( $this->mean( $fmt_totals['h2_count'] ) ),
			'h3_per_article'      => (int) round( $this->mean( $fmt_totals['h3_count'] ) ),
			'first_person_rate'   => round( $this->mean( $fmt_totals['first_person'] ), 3 ),
			'samples_observed'    => count( $posts ),
		);
		$profile['last_calibrated'] = current_time( 'mysql' );
		$profile['generated_at']    = current_time( 'mysql' );
		return $this->save_profile( $profile );
	}

	/* ============================ Incremental learning ============================ */

	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}
		$profile = $this->get_profile();
		if ( empty( $profile['auto_learn'] ) ) {
			return;
		}
		$this->ingest_post( $post );
	}

	/**
	 * Roll a single post into the memory. Uses an exponential moving
	 * average so the profile updates gracefully without rescanning history.
	 */
	public function ingest_post( WP_Post $post ) {
		$metrics = $this->measure_formatting( $post->post_content );
		$tone    = $this->detect_tone( $post->post_content );
		update_post_meta( $post->ID, self::META_POST_TONE, $tone );
		update_post_meta( $post->ID, self::META_POST_FMT, $metrics );

		$profile = $this->get_profile();
		$alpha = 0.18; // smoothing factor — new post is 18% of the weight
		$fmt = $profile['fmt'];
		$fmt['avg_paragraph_words'] = (int) round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['avg_paragraph_words'] + $alpha * $metrics['avg_paragraph_words'] ) : $metrics['avg_paragraph_words'] );
		$fmt['avg_sentence_words']  = (int) round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['avg_sentence_words']  + $alpha * $metrics['avg_sentence_words'] )  : $metrics['avg_sentence_words'] );
		$fmt['list_usage_rate']     = round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['list_usage_rate'] + $alpha * ( $metrics['has_list'] ? 1 : 0 ) ) : ( $metrics['has_list'] ? 1 : 0 ), 2 );
		$fmt['h2_per_article']      = (int) round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['h2_per_article'] + $alpha * $metrics['h2_count'] ) : $metrics['h2_count'] );
		$fmt['h3_per_article']      = (int) round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['h3_per_article'] + $alpha * $metrics['h3_count'] ) : $metrics['h3_count'] );
		$fmt['first_person_rate']   = round( $fmt['samples_observed'] ? ( ( 1 - $alpha ) * $fmt['first_person_rate'] + $alpha * $metrics['first_person_rate'] ) : $metrics['first_person_rate'], 3 );
		$fmt['samples_observed']    = (int) $fmt['samples_observed'] + 1;
		$profile['fmt'] = $fmt;

		// Tone history — keep a rolling window.
		$profile['tone_history'][] = array(
			'post_id'     => $post->ID,
			'tone'        => $tone,
			'detected_at' => current_time( 'mysql' ),
		);
		$profile['tone_history'] = array_slice( $profile['tone_history'], -self::TONE_HISTORY_LEN );

		// If primary_tone hasn't been explicitly chosen, drift toward the
		// most-recent dominant tone. Once a preset is applied the user's
		// choice sticks.
		if ( empty( $profile['applied_preset'] ) ) {
			$tone_counts = array();
			foreach ( $profile['tone_history'] as $h ) {
				$tone_counts[ $h['tone'] ] = ( $tone_counts[ $h['tone'] ] ?? 0 ) + 1;
			}
			arsort( $tone_counts );
			$profile['primary_tone'] = (string) ( key( $tone_counts ) ?: $profile['primary_tone'] );
		}

		$this->save_profile( $profile );
	}

	/* ============================ Formatting measurement ============================ */

	public function measure_formatting( $html ) {
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );

		// Paragraphs from <p> blocks first; fallback to double newline.
		$paragraphs = array();
		if ( preg_match_all( '#<p\b[^>]*>(.+?)</p>#is', $html, $m ) ) {
			foreach ( $m[1] as $p ) {
				$t = trim( wp_strip_all_tags( $p ) );
				if ( '' !== $t ) { $paragraphs[] = $t; }
			}
		}
		if ( empty( $paragraphs ) ) {
			foreach ( preg_split( "/\n{2,}/", $text ) as $p ) {
				$p = trim( $p );
				if ( '' !== $p ) { $paragraphs[] = $p; }
			}
		}
		$para_lengths = array_map( function( $p ) { return str_word_count( $p ); }, $paragraphs );
		$avg_paragraph_words = $para_lengths ? array_sum( $para_lengths ) / max( 1, count( $para_lengths ) ) : 0;

		// Sentences — naive split on . ! ?
		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		$sentences = array_filter( array_map( 'trim', $sentences ) );
		$sent_lengths = array_map( 'str_word_count', $sentences );
		$avg_sentence_words = $sent_lengths ? array_sum( $sent_lengths ) / max( 1, count( $sent_lengths ) ) : 0;

		$h2_count = preg_match_all( '#<h2\b#i', $html );
		$h3_count = preg_match_all( '#<h3\b#i', $html );
		$ul_count = preg_match_all( '#<ul\b#i', $html );
		$ol_count = preg_match_all( '#<ol\b#i', $html );

		$first_person_hits = preg_match_all( "/\b(I|we|our|us|me|my)\b/i", $text );
		$total_words = str_word_count( $text );
		$first_person_rate = $total_words > 0 ? $first_person_hits / $total_words : 0;

		return array(
			'avg_paragraph_words' => round( $avg_paragraph_words, 1 ),
			'avg_sentence_words'  => round( $avg_sentence_words, 1 ),
			'h2_count'            => (int) $h2_count,
			'h3_count'            => (int) $h3_count,
			'list_count'          => (int) ( $ul_count + $ol_count ),
			'has_list'            => ( $ul_count + $ol_count ) > 0,
			'first_person_rate'   => round( $first_person_rate, 3 ),
			'total_words'         => (int) $total_words,
		);
	}

	/* ============================ Tone detection ============================ */

	/**
	 * Heuristic tone detection — scores six known tone patterns against the
	 * text and returns the leader. Cheap, deterministic, no API.
	 */
	public function detect_tone( $html ) {
		$text = strtolower( wp_strip_all_tags( $html ) );
		$len  = max( 1, str_word_count( $text ) );

		$scores = array_fill_keys( array_keys( self::supported_tones() ), 0 );

		// Conversational: heavy "you / your", contractions, short sentences
		$you_rate = preg_match_all( '/\b(you|your|yours)\b/i', $text ) / $len;
		$contr_rate = preg_match_all( "/\b\w+'(t|re|ve|ll|s|d|m)\b/i", $text ) / $len;
		$scores[ self::TONE_CONVERSATIONAL ] += $you_rate * 800;
		$scores[ self::TONE_CONVERSATIONAL ] += $contr_rate * 600;

		// Emotional: feeling words, exclamations, first-person heavy
		$feel = preg_match_all( '/\b(love|hate|amazing|incredible|heartbreaking|joy|sad|fear|hope|dream|cried|moved)\b/i', $text );
		$excl = substr_count( $text, '!' );
		$first_p = preg_match_all( '/\b(i|me|my|mine)\b/i', $text ) / $len;
		$scores[ self::TONE_EMOTIONAL ] += $feel * 6;
		$scores[ self::TONE_EMOTIONAL ] += $excl * 4;
		$scores[ self::TONE_EMOTIONAL ] += $first_p * 600;

		// Educational: "step", "example", "let's", "first/second/finally"
		$edu_terms = preg_match_all( '/\b(step\s?\d|example|for instance|let\'s|first,|second,|third,|finally,|in other words|that means)\b/i', $text );
		$scores[ self::TONE_EDUCATIONAL ] += $edu_terms * 4;

		// Authority: citations, "according to", "study", "research"
		$auth_terms = preg_match_all( '/\b(according to|study|studies|research|data shows?|cited|cited in|peer-reviewed|et al|published)\b/i', $text );
		$scores[ self::TONE_AUTHORITY ] += $auth_terms * 6;

		// Storytelling: narrative time markers + scene words
		$story = preg_match_all( '/\b(years? ago|one day|that morning|the moment|i remember|stood there|walked into|the story (?:of|behind))\b/i', $text );
		$scores[ self::TONE_STORYTELLING ] += $story * 8;

		// Professional: longer sentences, lower contractions, third-person.
		$prof_score = 0;
		// Sentences avg > 18 words gets points
		$sent_lengths = array_map( 'str_word_count', array_filter( array_map( 'trim', preg_split( '/(?<=[.!?])\s+/', wp_strip_all_tags( $html ) ) ) ) );
		$avg_sent = $sent_lengths ? array_sum( $sent_lengths ) / max( 1, count( $sent_lengths ) ) : 0;
		if ( $avg_sent >= 18 ) { $prof_score += 12; }
		if ( $contr_rate < 0.01 ) { $prof_score += 6; }
		$scores[ self::TONE_PROFESSIONAL ] = $prof_score;

		arsort( $scores );
		$top   = key( $scores );
		$top_s = current( $scores );
		// Default to professional if nothing has any signal.
		return $top_s > 2 ? $top : self::TONE_PROFESSIONAL;
	}

	/* ============================ Category overrides ============================ */

	public function get_category_override( $category_id ) {
		$profile = $this->get_profile();
		return $profile['category_overrides'][ (int) $category_id ] ?? array();
	}

	public function set_category_override( $category_id, $tone, $note = '' ) {
		$profile = $this->get_profile();
		$cid = (int) $category_id;
		if ( '' === $tone ) {
			unset( $profile['category_overrides'][ $cid ] );
		} else {
			$profile['category_overrides'][ $cid ] = array(
				'tone'  => $tone,
				'note'  => sanitize_textarea_field( $note ),
			);
		}
		return $this->save_profile( $profile );
	}

	public function effective_tone( $category_id = 0 ) {
		$profile = $this->get_profile();
		$cid = (int) $category_id;
		if ( $cid > 0 && ! empty( $profile['category_overrides'][ $cid ]['tone'] ) ) {
			return $profile['category_overrides'][ $cid ]['tone'];
		}
		return $profile['primary_tone'];
	}

	/* ============================ Prompt context ============================ */

	/**
	 * Compact system-prompt block describing the brand voice. The Content
	 * Generator inlines this into every generation request so the article
	 * inherits the calibrated voice.
	 */
	public function to_prompt_context( $category_id = 0 ) {
		$p = $this->get_profile();
		$tone = $this->effective_tone( $category_id );
		$tones_map = self::supported_tones();
		$tone_summary = $tones_map[ $tone ]['summary'] ?? '';
		$lines = array();
		$lines[] = 'BRAND VOICE — this article MUST match the following voice profile:';
		$lines[] = '- Primary tone: ' . ( $tones_map[ $tone ]['label'] ?? $tone ) . ' — ' . $tone_summary;
		if ( $p['brand_tagline'] ) {
			$lines[] = '- Brand tagline / north star: ' . $p['brand_tagline'];
		}
		if ( $p['brand_pillars'] ) {
			$lines[] = '- Editorial pillars: ' . str_replace( "\n", '; ', trim( $p['brand_pillars'] ) );
		}
		if ( $p['brand_avoid'] ) {
			$lines[] = '- Words / framings to AVOID: ' . str_replace( "\n", '; ', trim( $p['brand_avoid'] ) );
		}
		$fmt = $p['fmt'];
		if ( $fmt['samples_observed'] > 0 ) {
			$lines[] = sprintf( '- Formatting memory (avg from %d prior posts): paragraphs ~%d words, sentences ~%d words, %d H2 / %d H3 per article, %d%% list usage, %.1f%% first-person.',
				$fmt['samples_observed'],
				$fmt['avg_paragraph_words'],
				$fmt['avg_sentence_words'],
				$fmt['h2_per_article'],
				$fmt['h3_per_article'],
				(int) round( $fmt['list_usage_rate'] * 100 ),
				$fmt['first_person_rate'] * 100
			);
			$lines[] = '- Match these structural patterns. They are the existing audience\'s expectation.';
		}
		if ( $category_id > 0 && ! empty( $p['category_overrides'][ $category_id ]['note'] ) ) {
			$lines[] = '- Category-specific note: ' . $p['category_overrides'][ $category_id ]['note'];
		}
		return implode( "\n", $lines );
	}

	/* ============================ Helpers ============================ */

	protected function mean( array $values ) {
		$values = array_filter( $values, 'is_numeric' );
		return $values ? array_sum( $values ) / count( $values ) : 0;
	}
}
