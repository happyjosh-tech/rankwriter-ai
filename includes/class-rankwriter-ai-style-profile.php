<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts raw analyzer signals into the persisted "Blog Style Profile"
 * that the generator consumes on every article run.
 */
class RankWriter_AI_Style_Profile {

	const OPTION_KEY = 'rwai_blog_style_profile';
	const RUN_KEY    = 'rwai_last_analysis_run';

	public function build_and_save( array $signals ) {
		$profile = $this->build( $signals );
		update_option( self::OPTION_KEY, $profile, false );
		update_option( self::RUN_KEY, current_time( 'mysql' ) );
		return $profile;
	}

	public function get() {
		$p = get_option( self::OPTION_KEY );
		return is_array( $p ) ? $p : array();
	}

	public function last_run() {
		return (string) get_option( self::RUN_KEY, '' );
	}

	public function clear() {
		delete_option( self::OPTION_KEY );
		delete_option( self::RUN_KEY );
	}

	/**
	 * Derive an opinionated, prompt-ready style profile from raw signals.
	 */
	public function build( array $s ) {
		$tone        = $this->infer_tone( $s );
		$headline    = $this->dominant_key( isset( $s['headline_patterns'] ) ? $s['headline_patterns'] : array() );
		$avg_wc      = isset( $s['avg_word_count'] ) ? (int) $s['avg_word_count'] : 0;
		$formatting  = $this->describe_formatting( $s );
		$dominant    = isset( $s['top_categories'] ) ? array_slice( $s['top_categories'], 0, 5 ) : array();
		$mon         = isset( $s['monetization'] ) ? $s['monetization'] : array();
		$cadence     = isset( $s['publishing_cadence']['_per_month_avg'] ) ? $s['publishing_cadence']['_per_month_avg'] : 0;

		$linking_opps = $this->internal_linking_opportunities( $s );
		$seo_gaps     = $this->seo_gaps( $s );
		$expansion    = $this->expansion_opportunities( $s );
		$duplicates   = isset( $s['duplicate_titles'] ) ? $s['duplicate_titles'] : array();

		$summary = sprintf(
			'%d posts analyzed (of %d total). Avg %d words, %s headlines, %s tone, monetization: %s. Publishing %.1f posts/month.',
			isset( $s['sample_size'] ) ? (int) $s['sample_size'] : 0,
			isset( $s['total_published'] ) ? (int) $s['total_published'] : 0,
			$avg_wc,
			$headline ? $headline : 'mixed',
			$tone,
			isset( $mon['dominant_strategy'] ) ? $mon['dominant_strategy'] : 'unknown',
			(float) $cadence
		);

		return array(
			'generated_at'                  => current_time( 'mysql' ),
			'summary'                       => $summary,
			'preferred_tone'                => $tone,
			'common_headline_style'         => $headline,
			'headline_pattern_distribution' => isset( $s['headline_patterns'] ) ? $s['headline_patterns'] : array(),
			'average_word_count'            => $avg_wc,
			'median_word_count'             => isset( $s['median_word_count'] ) ? (int) $s['median_word_count'] : 0,
			'preferred_formatting'          => $formatting,
			'dominant_categories'           => $dominant,
			'top_tags'                      => isset( $s['top_tags'] ) ? array_slice( $s['top_tags'], 0, 15 ) : array(),
			'top_keywords'                  => isset( $s['top_keywords'] ) ? array_slice( $s['top_keywords'], 0, 25, true ) : array(),
			'publishing_cadence'            => isset( $s['publishing_cadence'] ) ? $s['publishing_cadence'] : array(),
			'monetization_patterns'         => $mon,
			'internal_linking_opportunities'=> $linking_opps,
			'seo_gaps'                      => $seo_gaps,
			'content_expansion_opportunities' => $expansion,
			'duplicate_topic_warnings'      => $duplicates,
			'top_performing_posts'          => isset( $s['top_performing'] ) ? $s['top_performing'] : array(),
			'audience_intent'               => isset( $s['audience_intent'] ) ? $s['audience_intent'] : array(),
			'content_gaps'                  => isset( $s['content_gaps'] ) ? $s['content_gaps'] : array(),
			'common_topics_covered'         => isset( $s['common_topics_covered'] ) ? $s['common_topics_covered'] : array(),
			'image_style_guess'             => isset( $s['image_style_guess'] ) ? $s['image_style_guess'] : '',
			'structural_patterns'           => isset( $s['structural_patterns'] ) ? $s['structural_patterns'] : array(),
			'existing_post_titles'          => isset( $s['existing_post_titles'] ) ? $s['existing_post_titles'] : array(),
			'claude_deep_analysis'          => isset( $s['claude_deep_analysis'] ) ? $s['claude_deep_analysis'] : '',
			'sample_size'                   => isset( $s['sample_size'] ) ? (int) $s['sample_size'] : 0,
			'raw_signals'                   => $s,
		);
	}

	private function infer_tone( array $s ) {
		$h = isset( $s['headline_patterns'] ) ? $s['headline_patterns'] : array();
		$cta_pct = isset( $s['monetization']['cta_phrase_pct'] ) ? (float) $s['monetization']['cta_phrase_pct'] : 0;

		if ( ! empty( $h['how-to'] ) && $h['how-to'] >= 3 ) {
			return 'instructional-how-to';
		}
		if ( ! empty( $h['listicle'] ) && ! empty( $h['best-of'] ) ) {
			return 'authoritative-roundup';
		}
		if ( ! empty( $h['jobs-visa'] ) ) {
			return 'practical-application-focused';
		}
		if ( ! empty( $h['review'] ) ) {
			return 'review-and-recommendation';
		}
		if ( $cta_pct > 40 ) {
			return 'conversion-driven-conversational';
		}
		return 'professional-informative';
	}

	private function dominant_key( array $arr ) {
		if ( empty( $arr ) ) {
			return '';
		}
		arsort( $arr );
		reset( $arr );
		return (string) key( $arr );
	}

	private function describe_formatting( array $s ) {
		$avg_h2     = isset( $s['avg_h2'] ) ? $s['avg_h2'] : 0;
		$avg_h3     = isset( $s['avg_h3'] ) ? $s['avg_h3'] : 0;
		$avg_lists  = isset( $s['lists_per_post'] ) ? $s['lists_per_post'] : 0;
		$avg_images = isset( $s['avg_images'] ) ? $s['avg_images'] : 0;

		$traits = array();
		if ( $avg_h2 >= 5 ) {
			$traits[] = 'heavy use of H2 sections';
		} elseif ( $avg_h2 >= 2 ) {
			$traits[] = 'moderate H2 sectioning';
		} else {
			$traits[] = 'light sectioning';
		}
		if ( $avg_h3 >= 3 ) {
			$traits[] = 'frequent H3 sub-sections';
		}
		if ( $avg_lists >= 2 ) {
			$traits[] = 'bulleted/numbered lists in most posts';
		}
		if ( $avg_images >= 3 ) {
			$traits[] = 'image-rich layout';
		} elseif ( $avg_images >= 1 ) {
			$traits[] = 'modest imagery';
		} else {
			$traits[] = 'minimal imagery';
		}
		return array(
			'description' => implode( '; ', $traits ),
			'avg_h2'      => $avg_h2,
			'avg_h3'      => $avg_h3,
			'avg_lists'   => $avg_lists,
			'avg_images'  => $avg_images,
		);
	}

	private function internal_linking_opportunities( array $s ) {
		$opps = array();
		if ( ! empty( $s['orphan_posts'] ) ) {
			foreach ( $s['orphan_posts'] as $o ) {
				$opps[] = array(
					'reason' => 'No inbound internal links from analyzed sample',
					'post'   => $o,
					'action' => 'Link to this post from new articles on related topics',
				);
			}
		}
		if ( ! empty( $s['top_performing'] ) ) {
			foreach ( array_slice( $s['top_performing'], 0, 3 ) as $tp ) {
				$opps[] = array(
					'reason' => 'Top-performing post — strong internal link target',
					'post'   => $tp,
					'action' => 'Reference this from new related content to compound rankings',
				);
			}
		}
		return $opps;
	}

	private function seo_gaps( array $s ) {
		$gaps = array();
		if ( isset( $s['meta_desc_coverage'] ) && $s['meta_desc_coverage'] < 80 ) {
			$gaps[] = sprintf(
				'Only %s%% of analyzed posts have a meta description. Add meta descriptions to remaining posts.',
				$s['meta_desc_coverage']
			);
		}
		if ( isset( $s['images_with_alt_pct'] ) && $s['images_with_alt_pct'] < 80 ) {
			$gaps[] = sprintf(
				'Only %s%% of images have alt text. Backfill alt attributes for SEO and accessibility.',
				$s['images_with_alt_pct']
			);
		}
		if ( isset( $s['avg_internal_links'] ) && $s['avg_internal_links'] < 2 ) {
			$gaps[] = sprintf(
				'Average %s internal links per post — increase to 3-5 to improve topical authority.',
				$s['avg_internal_links']
			);
		}
		if ( ! empty( $s['no_meta_desc_posts'] ) ) {
			$gaps[] = sprintf(
				'%d posts in sample have no meta description (e.g., "%s").',
				count( $s['no_meta_desc_posts'] ),
				$s['no_meta_desc_posts'][0]['title']
			);
		}
		return $gaps;
	}

	private function expansion_opportunities( array $s ) {
		$out = array();
		if ( ! empty( $s['thin_posts'] ) ) {
			foreach ( array_slice( $s['thin_posts'], 0, 10 ) as $t ) {
				$out[] = array(
					'reason' => sprintf( 'Thin content (%d words)', $t['words'] ),
					'post'   => array(
						'id'    => $t['id'],
						'title' => $t['title'],
					),
					'action' => 'Expand to match the blog average; add FAQs, examples, and internal links',
				);
			}
		}
		if ( ! empty( $s['top_categories'] ) ) {
			$top = $s['top_categories'][0];
			$out[] = array(
				'reason' => sprintf( '"%s" is your dominant category (%d posts) — clusterable for topical authority', $top['name'], $top['count'] ),
				'post'   => null,
				'action' => sprintf( 'Plan 5-10 new posts targeting long-tail variants under "%s"', $top['name'] ),
			);
		}
		return $out;
	}

	/**
	 * Renders the style profile as a Markdown block ready to embed in a
	 * Claude system prompt.
	 */
	public function to_prompt_context() {
		$p = $this->get();
		if ( empty( $p ) ) {
			return '';
		}

		$lines = array();
		$lines[] = '## Blog Style Profile';
		$lines[] = $p['summary'];
		$lines[] = '';
		$lines[] = '### Style requirements (match the existing blog)';
		$lines[] = '- Preferred tone: ' . $p['preferred_tone'];
		$lines[] = '- Common headline style: ' . ( $p['common_headline_style'] ? $p['common_headline_style'] : 'mixed' );
		$lines[] = '- Target word count: approximately ' . $p['average_word_count'] . ' words';
		if ( ! empty( $p['preferred_formatting']['description'] ) ) {
			$lines[] = '- Formatting: ' . $p['preferred_formatting']['description'];
		}
		if ( ! empty( $p['dominant_categories'] ) ) {
			$cats = array();
			foreach ( $p['dominant_categories'] as $c ) {
				$cats[] = $c['name'];
			}
			$lines[] = '- Dominant categories on the blog: ' . implode( ', ', $cats );
		}
		if ( ! empty( $p['monetization_patterns']['dominant_strategy'] ) ) {
			$lines[] = '- Monetization strategy: ' . $p['monetization_patterns']['dominant_strategy'];
		}
		if ( ! empty( $p['top_keywords'] ) ) {
			$lines[] = '- Top recurring keywords: ' . implode( ', ', array_slice( array_keys( $p['top_keywords'] ), 0, 15 ) );
		}
		if ( ! empty( $p['audience_intent']['dominant'] ) ) {
			$lines[] = '- Dominant audience intent: ' . $p['audience_intent']['dominant'];
			if ( ! empty( $p['audience_intent']['distribution_pct'] ) ) {
				$dist = array();
				foreach ( $p['audience_intent']['distribution_pct'] as $k => $v ) {
					$dist[] = $k . ' ' . $v . '%';
				}
				$lines[] = '- Intent mix: ' . implode( ', ', $dist );
			}
		}
		if ( ! empty( $p['image_style_guess'] ) ) {
			$lines[] = '- Image style on this blog: ' . $p['image_style_guess'];
		}
		if ( ! empty( $p['structural_patterns']['top'] ) ) {
			$lines[] = '';
			$lines[] = '### Structural patterns this blog uses (continue them automatically)';
			$pattern_labels = array(
				'faq_section'           => 'FAQ section (4-6 questions at the end)',
				'comparison_table'      => 'comparison tables',
				'numbered_steps'        => 'numbered step-by-step lists',
				'salary_range_mentions' => 'salary ranges with concrete dollar figures',
				'price_mentions'        => 'specific price points',
				'company_list'          => 'lists of named companies',
				'product_roundup'       => 'product roundups / "best of" lists',
				'emotional_hook_intro'  => 'emotional first-person hook in the intro',
				'stats_with_citation'   => 'statistics with cited percentages',
				'tl_dr_summary'         => 'TL;DR / quick-answer summary near the top',
				'cta_block'             => 'clear call-to-action block',
				'pros_cons_lists'       => 'pros/cons lists',
			);
			foreach ( $p['structural_patterns']['top'] as $key => $pct_val ) {
				$label = isset( $pattern_labels[ $key ] ) ? $pattern_labels[ $key ] : $key;
				$lines[] = '- ' . $label . ' (used in ' . $pct_val . '% of posts)';
			}
		}
		if ( ! empty( $p['internal_linking_opportunities'] ) ) {
			$lines[] = '';
			$lines[] = '### Internal linking targets';
			foreach ( array_slice( $p['internal_linking_opportunities'], 0, 6 ) as $opp ) {
				if ( ! empty( $opp['post']['title'] ) ) {
					$lines[] = '- "' . $opp['post']['title'] . '" — ' . $opp['action'];
				}
			}
		}
		if ( ! empty( $p['common_topics_covered']['bigrams'] ) ) {
			$lines[] = '';
			$lines[] = '### Topics already covered on this blog (avoid duplicating exactly)';
			$top_phrases = array();
			foreach ( array_slice( $p['common_topics_covered']['bigrams'], 0, 10, true ) as $phrase => $count ) {
				$top_phrases[] = $phrase . ' (' . $count . ')';
			}
			$lines[] = implode( ', ', $top_phrases );
		}
		if ( ! empty( $p['content_gaps'] ) ) {
			$lines[] = '';
			$lines[] = '### Content gaps to fill';
			foreach ( array_slice( $p['content_gaps'], 0, 5 ) as $gap ) {
				$lines[] = '- ' . $gap['gap'] . ' → ' . $gap['suggestion'];
			}
		}
		if ( ! empty( $p['duplicate_topic_warnings'] ) ) {
			$lines[] = '';
			$lines[] = '### Avoid duplicating these existing posts';
			foreach ( array_slice( $p['duplicate_topic_warnings'], 0, 5 ) as $d ) {
				if ( ! empty( $d['posts'][0]['title'] ) ) {
					$lines[] = '- ' . $d['posts'][0]['title'];
				}
			}
		}
		if ( ! empty( $p['claude_deep_analysis'] ) ) {
			$lines[] = '';
			$lines[] = '### Editorial brief from prior deep analysis';
			$lines[] = $p['claude_deep_analysis'];
		}

		return implode( "\n", $lines );
	}
}
