<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Programmatic SEO core engine — three responsibilities collapsed into one
 * class for cohesion:
 *
 *   - Variation::pick_variant()      Deterministic-but-rotating selection of
 *                                    intro variant, section order, and FAQ
 *                                    set per dataset row. Same values_hash
 *                                    always picks the same variant so a
 *                                    retry produces the same shape.
 *
 *   - Uniqueness::score( $title,
 *                        $content,
 *                        $template_id )
 *                                    Shingle-based similarity vs every
 *                                    other generated page in the same
 *                                    template. Returns 0-100 where 100 =
 *                                    perfectly unique.
 *
 *   - Generator::generate_row()      Composes the full prompt context from
 *                                    a template + dataset row + chosen
 *                                    variant, hands off to the existing
 *                                    Content Generator, runs the uniqueness
 *                                    check, saves or fails the row.
 *
 * All three are pure PHP except Generator::generate_row(), which delegates
 * the actual Claude call to RankWriter_AI_Content_Generator.
 */
class RankWriter_AI_PSE_Engine {

	private $manager;

	public function __construct() {
		$this->manager = new RankWriter_AI_PSE_Manager();
	}

	/* ====================== Variation ====================== */

	/**
	 * Deterministic variant selection per row.
	 *
	 * @return array { intro_variant, section_order, faq_set, conclusion_variant, signature }
	 */
	public function pick_variant( array $template, array $row ) {
		$outline = $template['outline'];
		$hash    = $row['values_hash'];

		$intro_variants      = isset( $outline['intro_variants'] ) ? $outline['intro_variants'] : array();
		$order_variants      = isset( $outline['section_order_variants'] ) ? $outline['section_order_variants'] : array();
		$conclusion_variants = isset( $outline['conclusion_variants'] ) ? $outline['conclusion_variants'] : array();
		$faq_pool            = isset( $outline['faq_pool'] ) ? $outline['faq_pool'] : array();

		$intro_idx = count( $intro_variants ) > 0 ? hexdec( substr( $hash, 0, 2 ) ) % count( $intro_variants ) : 0;
		$order_idx = count( $order_variants ) > 0 ? hexdec( substr( $hash, 2, 2 ) ) % count( $order_variants ) : 0;
		$conc_idx  = count( $conclusion_variants ) > 0 ? hexdec( substr( $hash, 4, 2 ) ) % count( $conclusion_variants ) : 0;

		// FAQ subset: 4 questions per page, deterministic shuffle.
		$faq_set = array();
		if ( ! empty( $faq_pool ) ) {
			$shuffled = $this->seeded_shuffle( $faq_pool, $hash );
			$faq_set  = array_slice( $shuffled, 0, 4 );
		}

		$signature = substr( $hash, 0, 8 );

		return array(
			'intro_variant'      => $intro_variants[ $intro_idx ] ?? '',
			'section_order'      => $order_variants[ $order_idx ] ?? array(),
			'conclusion_variant' => $conclusion_variants[ $conc_idx ] ?? '',
			'faq_set'            => $faq_set,
			'signature'          => $signature,
		);
	}

	/**
	 * Deterministic shuffle keyed by hash — same hash always produces the
	 * same order, but the order is distinct from sibling rows.
	 */
	private function seeded_shuffle( array $arr, $seed_hex ) {
		$seed = hexdec( substr( $seed_hex, 0, 8 ) );
		$n    = count( $arr );
		for ( $i = $n - 1; $i > 0; $i-- ) {
			// Lehmer LCG step keyed on seed + i.
			$seed = ( $seed * 1103515245 + 12345 + $i ) & 0x7fffffff;
			$j    = $seed % ( $i + 1 );
			$tmp        = $arr[ $i ];
			$arr[ $i ]  = $arr[ $j ];
			$arr[ $j ]  = $tmp;
		}
		return $arr;
	}

	/* ====================== Uniqueness ====================== */

	/**
	 * Compare a candidate post against every previously generated page from
	 * the same template using a shingle-overlap heuristic. Returns 0-100
	 * where 100 = no overlap.
	 *
	 * Implementation: 5-gram word shingles on plain content. We sample at
	 * most 200 shingles per page so the comparison stays O(1) memory.
	 */
	public function uniqueness_score( $title, $content_html, $template_id ) {
		global $wpdb;
		$template_id = absint( $template_id );
		$plain       = $this->normalize_text( $title . ' ' . wp_strip_all_tags( $content_html ) );
		$candidate   = $this->shingle_set( $plain, 5, 200 );
		if ( empty( $candidate ) ) {
			return 100;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT post_id FROM ' . RankWriter_AI_PSE_DB::rows_table() . ' WHERE template_id = %d AND status = %s AND post_id IS NOT NULL ORDER BY id DESC LIMIT 30',
			$template_id, 'generated'
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return 100;
		}

		$worst = 100;
		foreach ( $rows as $other ) {
			$other_post = get_post( (int) $other['post_id'] );
			if ( ! $other_post ) {
				continue;
			}
			$other_plain    = $this->normalize_text( $other_post->post_title . ' ' . wp_strip_all_tags( $other_post->post_content ) );
			$other_shingles = $this->shingle_set( $other_plain, 5, 200 );
			if ( empty( $other_shingles ) ) {
				continue;
			}
			$intersect = count( array_intersect_key( $candidate, $other_shingles ) );
			$union     = count( $candidate ) + count( $other_shingles ) - $intersect;
			if ( 0 === $union ) {
				continue;
			}
			$similarity = ( $intersect / $union ) * 100;
			$uniqueness = (int) round( 100 - $similarity );
			if ( $uniqueness < $worst ) {
				$worst = $uniqueness;
			}
			if ( $worst < 30 ) {
				break; // can't recover from this one
			}
		}
		return max( 0, min( 100, $worst ) );
	}

	private function normalize_text( $text ) {
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9\s]/', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	private function shingle_set( $text, $k = 5, $sample_cap = 200 ) {
		$words = preg_split( '/\s+/', $text );
		$words = array_values( array_filter( (array) $words, 'strlen' ) );
		$n     = count( $words );
		if ( $n < $k ) {
			return array();
		}
		$out  = array();
		$step = ( $n - $k + 1 ) > $sample_cap ? (int) ceil( ( $n - $k + 1 ) / $sample_cap ) : 1;
		for ( $i = 0; $i <= $n - $k; $i += $step ) {
			$shingle = implode( ' ', array_slice( $words, $i, $k ) );
			$out[ $shingle ] = 1;
			if ( count( $out ) >= $sample_cap ) {
				break;
			}
		}
		return $out;
	}

	/* ====================== Generator ====================== */

	/**
	 * Generate one dataset row → one WP post.
	 *
	 * Returns the post ID on success, or a WP_Error on failure. The row's
	 * status is updated in either case so the queue keeps moving.
	 *
	 * @param int $row_id
	 * @return int|WP_Error
	 */
	public function generate_row( $row_id ) {
		$row_id = absint( $row_id );
		$row    = $this->manager->get_row( $row_id );
		if ( ! $row ) {
			return new WP_Error( 'rwai_no_row', __( 'Dataset row not found.', 'rankwriter-ai' ) );
		}
		$template = $this->manager->get_template( $row['template_id'] );
		if ( ! $template ) {
			$this->manager->update_row( $row_id, array(
				'status' => 'failed',
				'error_message' => 'Template missing.',
				'attempts_increment' => true,
			) );
			return new WP_Error( 'rwai_no_template', __( 'Template missing.', 'rankwriter-ai' ) );
		}

		if ( 'active' !== $template['status'] ) {
			return new WP_Error( 'rwai_paused', __( 'Template is paused.', 'rankwriter-ai' ) );
		}

		// Required-variable check.
		foreach ( (array) $template['variables'] as $var_key => $var_cfg ) {
			$required = ! empty( $var_cfg['required'] );
			if ( $required && empty( $row['values'][ $var_key ] ) ) {
				$msg = sprintf( __( 'Row missing required variable "%s".', 'rankwriter-ai' ), $var_key );
				$this->manager->update_row( $row_id, array(
					'status'             => 'failed',
					'error_message'      => $msg,
					'attempts_increment' => true,
				) );
				return new WP_Error( 'rwai_missing_var', $msg );
			}
		}

		$variant = $this->pick_variant( $template, $row );

		$topic = RankWriter_AI_PSE_Manager::interpolate( $template['title_template'], $row['values'] );
		if ( '' === trim( $topic ) ) {
			$topic = $template['name'];
		}

		$this->manager->update_row( $row_id, array(
			'status'             => 'queued',
			'queued_at'          => current_time( 'mysql' ),
			'variant_signature'  => $variant['signature'],
			'attempts_increment' => true,
		) );

		// Compose the PSE prompt context.
		$pse_context = $this->build_pse_context( $template, $row, $variant );

		// Hand off to the main content generator. All existing systems
		// (intent detector, internal linker, CPC scorer, discover rules,
		// title intelligence, compliance, SEO meta, featured image) apply.
		$generator = new RankWriter_AI_Content_Generator();
		$post_id   = $generator->generate( array(
			'profile_id'      => $template['profile_id'] ? (int) $template['profile_id'] : 0,
			'topic'           => $topic,
			'word_count'      => (int) $template['min_word_count'],
			'extra_context'   => '',
			'desired_status'  => 'draft',
			'cluster_id'      => $template['cluster_id'] ? (int) $template['cluster_id'] : 0,
			'pse_context'     => $pse_context,
			'autopilot'       => true,
		) );

		if ( is_wp_error( $post_id ) ) {
			$this->manager->update_row( $row_id, array(
				'status'        => 'failed',
				'error_message' => $post_id->get_error_message(),
			) );
			return $post_id;
		}

		// Uniqueness check post-generation.
		$post   = get_post( $post_id );
		$score  = $this->uniqueness_score( $post->post_title, $post->post_content, (int) $template['id'] );
		$min    = (int) $template['min_uniqueness'];
		if ( $score < $min ) {
			// Page is too similar to siblings. Mark as failed; keep post as
			// draft so editor can review or delete.
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			$this->manager->update_row( $row_id, array(
				'status'           => 'failed',
				'post_id'          => $post_id,
				'uniqueness_score' => $score,
				'error_message'    => sprintf( __( 'Uniqueness %d%% below template floor (%d%%) — saved as draft for review.', 'rankwriter-ai' ), $score, $min ),
			) );
			update_post_meta( $post_id, '_rwai_pse_uniqueness', $score );
			return new WP_Error( 'rwai_low_uniqueness', sprintf( __( 'Page is too similar to siblings (%d%%).', 'rankwriter-ai' ), $score ) );
		}

		$this->manager->update_row( $row_id, array(
			'status'           => 'generated',
			'post_id'          => $post_id,
			'uniqueness_score' => $score,
			'generated_at'     => current_time( 'mysql' ),
			'error_message'    => null,
		) );

		update_post_meta( $post_id, '_rwai_pse_template_id', (int) $template['id'] );
		update_post_meta( $post_id, '_rwai_pse_row_id',      (int) $row_id );
		update_post_meta( $post_id, '_rwai_pse_uniqueness',  (int) $score );
		update_post_meta( $post_id, '_rwai_pse_variant',     $variant['signature'] );

		return (int) $post_id;
	}

	/**
	 * Build the prompt block that gets injected into the Content Generator's
	 * system prompt. Tells Claude exactly which entity it's writing about,
	 * which sections to use, what intro angle, and which FAQs to include.
	 */
	private function build_pse_context( array $template, array $row, array $variant ) {
		$lines   = array();
		$lines[] = '## Programmatic SEO entity context';
		$lines[] = 'This article is page N of a programmatic series built from a template. Make it FEEL bespoke and human, not formulaic. The sibling articles in this series differ from this one in the entity data below — your writing should reflect that this specific entity is unique, not just a slot-filled template.';
		$lines[] = '';
		$lines[] = '### Entity values for THIS page';
		foreach ( $row['values'] as $k => $v ) {
			$lines[] = '- ' . $k . ': "' . $v . '"';
		}

		if ( ! empty( $variant['intro_variant'] ) ) {
			$lines[] = '';
			$lines[] = '### Intro angle (use this specific opening, not a generic stage-setting paragraph)';
			$lines[] = RankWriter_AI_PSE_Manager::interpolate( $variant['intro_variant'], $row['values'] );
		}

		if ( ! empty( $variant['section_order'] ) ) {
			$lines[] = '';
			$lines[] = '### Section order (use this exact order; do NOT use the same order as sibling articles in this series)';
			$sections_def = isset( $template['outline']['sections'] ) ? $template['outline']['sections'] : array();
			$by_name      = array();
			foreach ( $sections_def as $sec ) {
				if ( ! empty( $sec['name'] ) ) {
					$by_name[ $sec['name'] ] = $sec;
				}
			}
			$num = 1;
			foreach ( $variant['section_order'] as $name ) {
				$sec = isset( $by_name[ $name ] ) ? $by_name[ $name ] : null;
				if ( ! $sec ) {
					continue;
				}
				$headings = isset( $sec['headings'] ) ? $sec['headings'] : array();
				$heading  = ! empty( $headings ) ? $headings[ ( hexdec( substr( $row['values_hash'], 6, 2 ) ) + $num ) % count( $headings ) ] : ucfirst( $name );
				$heading  = RankWriter_AI_PSE_Manager::interpolate( $heading, $row['values'] );
				$lines[]  = $num . '. <h2>' . $heading . '</h2>';
				if ( ! empty( $sec['content_guide'] ) ) {
					$lines[] = '   Guidance: ' . RankWriter_AI_PSE_Manager::interpolate( $sec['content_guide'], $row['values'] );
				}
				$num++;
			}
		}

		if ( ! empty( $variant['faq_set'] ) ) {
			$lines[] = '';
			$lines[] = '### FAQ section (must include exactly these questions, in this order)';
			foreach ( $variant['faq_set'] as $q ) {
				$lines[] = '- ' . RankWriter_AI_PSE_Manager::interpolate( $q, $row['values'] );
			}
		}

		if ( ! empty( $variant['conclusion_variant'] ) ) {
			$lines[] = '';
			$lines[] = '### Conclusion angle';
			$lines[] = RankWriter_AI_PSE_Manager::interpolate( $variant['conclusion_variant'], $row['values'] );
		}

		if ( ! empty( $template['semantic_keywords'] ) ) {
			$lines[] = '';
			$lines[] = '### Semantic keyword pool (weave naturally; do not stuff)';
			$lines[] = $template['semantic_keywords'];
		}

		$lines[] = '';
		$lines[] = '### Anti-doorway rules (non-negotiable)';
		$lines[] = '- Every paragraph must reference the entity values above with concrete detail. No paragraph should be entity-agnostic boilerplate that could appear verbatim on a sibling page.';
		$lines[] = '- Cite real numbers, dates, and named institutions where the entity values support it.';
		$lines[] = '- Vary sentence rhythm. Do NOT use the same opening sentence pattern as a typical article in this series.';
		$lines[] = '- The article must read as if a human practitioner wrote it about THIS specific entity, not as a slot-filled template.';

		return implode( "\n", $lines );
	}
}
