<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans the existing WordPress blog and returns structured signals about
 * content patterns, formatting, frequency, monetization, and gaps.
 *
 * The output is consumed by RankWriter_AI_Style_Profile to produce the
 * persisted "Blog Style Profile".
 */
class RankWriter_AI_Blog_Analyzer {

	private $limit;

	public function __construct( $limit = 0 ) {
		if ( ! $limit ) {
			$limit = (int) RankWriter_AI_Helpers::get_setting( 'analyze_post_limit', 200 );
		}
		$this->limit = max( 10, min( 2000, (int) $limit ) );
	}

	/**
	 * Run a full analysis pass. Returns a deterministic associative array
	 * suitable for storage and for feeding back into Claude prompts.
	 */
	public function analyze() {
		$posts = $this->fetch_posts();

		$signals = array(
			'analyzed_at'              => current_time( 'mysql' ),
			'sample_size'              => count( $posts ),
			'total_published'          => (int) wp_count_posts( 'post' )->publish,
			'titles'                   => array(),
			'existing_post_titles'     => array(),
			'avg_word_count'           => 0,
			'median_word_count'        => 0,
			'avg_h2'                   => 0,
			'avg_h3'                   => 0,
			'avg_images'               => 0,
			'avg_internal_links'       => 0,
			'avg_external_links'       => 0,
			'lists_per_post'           => 0,
			'meta_desc_coverage'       => 0,
			'images_with_alt_pct'      => 0,
			'image_style_guess'        => '',
			'top_keywords'             => array(),
			'top_categories'           => array(),
			'top_tags'                 => array(),
			'headline_patterns'        => array(),
			'publishing_cadence'       => array(),
			'top_performing'           => array(),
			'monetization'             => array(),
			'duplicate_titles'         => array(),
			'thin_posts'               => array(),
			'no_meta_desc_posts'       => array(),
			'orphan_posts'             => array(),
			'audience_intent'          => array(),
			'content_gaps'             => array(),
			'common_topics_covered'    => array(),
			'claude_deep_analysis'     => '',
		);

		if ( empty( $posts ) ) {
			return $signals;
		}

		$word_counts        = array();
		$h2_counts          = array();
		$h3_counts          = array();
		$img_counts         = array();
		$internal_links     = array();
		$external_links     = array();
		$list_counts        = array();
		$keyword_counter    = array();
		$headline_buckets   = array();
		$meta_desc_present  = 0;
		$total_imgs         = 0;
		$imgs_with_alt      = 0;
		$title_lookup       = array();
		$inbound_link_count = array();
		$site_host          = RankWriter_AI_Helpers::site_host();
		$affiliate_hits     = 0;
		$ad_code_hits       = 0;
		$amazon_hits        = 0;
		$cta_hits           = 0;

		$image_extensions   = array();
		$image_filename_hits = array(
			'photo'        => 0,
			'illustration' => 0,
			'infographic'  => 0,
			'screenshot'   => 0,
			'icon'         => 0,
			'banner'       => 0,
		);

		foreach ( $posts as $p ) {
			$content = (string) $p->post_content;
			$title   = (string) $p->post_title;

			$signals['titles'][] = $title;
			$signals['existing_post_titles'][] = array(
				'id'    => (int) $p->ID,
				'title' => $title,
				'date'  => $p->post_date,
			);
			$wc                  = RankWriter_AI_Helpers::word_count( $content );
			$word_counts[]       = $wc;

			$h2_counts[] = preg_match_all( '/<h2[\s>]/i', $content );
			$h3_counts[] = preg_match_all( '/<h3[\s>]/i', $content );
			$list_counts[] = preg_match_all( '/<(ul|ol)[\s>]/i', $content );

			$img_total = preg_match_all( '/<img\b([^>]*)>/i', $content, $img_matches );
			$img_counts[] = $img_total;
			$total_imgs  += $img_total;
			if ( ! empty( $img_matches[1] ) ) {
				foreach ( $img_matches[1] as $attrs ) {
					if ( preg_match( '/\balt\s*=\s*("([^"]+)"|\'([^\']+)\')/i', $attrs, $m ) ) {
						$alt_val = isset( $m[2] ) && '' !== $m[2] ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' );
						if ( '' !== trim( $alt_val ) ) {
							$imgs_with_alt++;
						}
					}
					if ( preg_match( '/\bsrc\s*=\s*("([^"]+)"|\'([^\']+)\')/i', $attrs, $sm ) ) {
						$src = isset( $sm[2] ) && '' !== $sm[2] ? $sm[2] : ( isset( $sm[3] ) ? $sm[3] : '' );
						$src_low = strtolower( $src );
						if ( preg_match( '/\.(jpe?g|png|gif|webp|svg|avif)/i', $src_low, $em ) ) {
							$ext = strtolower( $em[1] );
							$image_extensions[ $ext ] = isset( $image_extensions[ $ext ] ) ? $image_extensions[ $ext ] + 1 : 1;
						}
						foreach ( $image_filename_hits as $kw => $_n ) {
							if ( false !== strpos( $src_low, $kw ) ) {
								$image_filename_hits[ $kw ]++;
							}
						}
					}
				}
			}

			$int_ct = 0;
			$ext_ct = 0;
			if ( preg_match_all( '/<a\b[^>]*href=("([^"]+)"|\'([^\']+)\')/i', $content, $link_matches ) ) {
				foreach ( $link_matches[2] as $idx => $hrefA ) {
					$href = '' !== $hrefA ? $hrefA : ( isset( $link_matches[3][ $idx ] ) ? $link_matches[3][ $idx ] : '' );
					if ( '' === $href ) {
						continue;
					}
					if ( 0 === strpos( $href, '#' ) ) {
						continue;
					}
					$host = RankWriter_AI_Helpers::ext_link_host( $href );
					if ( '' === $host || $host === $site_host ) {
						$int_ct++;
						$slug = trim( wp_parse_url( $href, PHP_URL_PATH ), '/' );
						if ( $slug ) {
							if ( ! isset( $inbound_link_count[ $slug ] ) ) {
								$inbound_link_count[ $slug ] = 0;
							}
							$inbound_link_count[ $slug ]++;
						}
					} else {
						$ext_ct++;
						if ( false !== stripos( $host, 'amazon.' ) || false !== stripos( $href, 'tag=' ) ) {
							$amazon_hits++;
						}
						if ( preg_match( '/(go\.|aff|ref=|partner|click|track|affiliate)/i', $href ) ) {
							$affiliate_hits++;
						}
					}
				}
			}
			$internal_links[] = $int_ct;
			$external_links[] = $ext_ct;

			if ( preg_match( '/(google_ad_client|adsbygoogle|data-ad-slot|adsense)/i', $content ) ) {
				$ad_code_hits++;
			}
			if ( preg_match( '/(buy now|sign up|subscribe|get started|download|apply now)/i', wp_strip_all_tags( $content ) ) ) {
				$cta_hits++;
			}

			$meta_desc = $this->get_meta_description( $p->ID );
			if ( '' !== $meta_desc ) {
				$meta_desc_present++;
			} else {
				$signals['no_meta_desc_posts'][] = array(
					'id'    => $p->ID,
					'title' => $title,
				);
			}

			foreach ( RankWriter_AI_Helpers::tokenize( $title . ' ' . $content ) as $tok ) {
				if ( ! isset( $keyword_counter[ $tok ] ) ) {
					$keyword_counter[ $tok ] = 0;
				}
				$keyword_counter[ $tok ]++;
			}

			$bucket = $this->classify_headline( $title );
			if ( ! isset( $headline_buckets[ $bucket ] ) ) {
				$headline_buckets[ $bucket ] = 0;
			}
			$headline_buckets[ $bucket ]++;

			$norm = strtolower( trim( preg_replace( '/\s+/', ' ', $title ) ) );
			if ( ! isset( $title_lookup[ $norm ] ) ) {
				$title_lookup[ $norm ] = array();
			}
			$title_lookup[ $norm ][] = array(
				'id'    => $p->ID,
				'title' => $title,
			);

			if ( $wc < 500 ) {
				$signals['thin_posts'][] = array(
					'id'    => $p->ID,
					'title' => $title,
					'words' => $wc,
				);
			}
		}

		sort( $word_counts );
		$n = count( $word_counts );
		$signals['avg_word_count']     = $n ? (int) round( array_sum( $word_counts ) / $n ) : 0;
		$signals['median_word_count']  = $n ? (int) $word_counts[ intdiv( $n, 2 ) ] : 0;
		$signals['avg_h2']             = $this->avg( $h2_counts );
		$signals['avg_h3']             = $this->avg( $h3_counts );
		$signals['avg_images']         = $this->avg( $img_counts );
		$signals['avg_internal_links'] = $this->avg( $internal_links );
		$signals['avg_external_links'] = $this->avg( $external_links );
		$signals['lists_per_post']     = $this->avg( $list_counts );
		$signals['meta_desc_coverage'] = $n ? round( ( $meta_desc_present / $n ) * 100, 1 ) : 0;
		$signals['images_with_alt_pct'] = $total_imgs ? round( ( $imgs_with_alt / $total_imgs ) * 100, 1 ) : 0;

		arsort( $keyword_counter );
		$signals['top_keywords'] = array_slice( $keyword_counter, 0, 30, true );

		arsort( $headline_buckets );
		$signals['headline_patterns'] = $headline_buckets;

		$signals['top_categories'] = $this->top_terms( 'category', 10 );
		$signals['top_tags']       = $this->top_terms( 'post_tag', 20 );

		$signals['publishing_cadence'] = $this->publishing_cadence();

		$signals['top_performing']     = $this->top_performing_posts();

		$signals['monetization'] = array(
			'sample_size'        => $n,
			'adsense_code_pct'   => $n ? round( ( $ad_code_hits / $n ) * 100, 1 ) : 0,
			'affiliate_link_pct' => $n ? round( ( $affiliate_hits / $n ) * 100, 1 ) : 0,
			'amazon_link_pct'    => $n ? round( ( $amazon_hits / $n ) * 100, 1 ) : 0,
			'cta_phrase_pct'     => $n ? round( ( $cta_hits / $n ) * 100, 1 ) : 0,
			'dominant_strategy'  => $this->classify_monetization( $ad_code_hits, $affiliate_hits, $amazon_hits, $cta_hits, $n ),
		);

		foreach ( $title_lookup as $norm => $matches ) {
			if ( count( $matches ) > 1 ) {
				$signals['duplicate_titles'][] = array(
					'title_normalized' => $norm,
					'posts'            => $matches,
				);
			}
		}

		$signals['orphan_posts'] = $this->find_orphans( $inbound_link_count );

		$signals['audience_intent']       = $this->infer_audience_intent( $signals['titles'], $headline_buckets );
		$signals['common_topics_covered'] = $this->extract_topic_phrases( $signals['titles'] );
		$signals['image_style_guess']     = $this->infer_image_style( $image_extensions, $image_filename_hits );
		$signals['structural_patterns']   = $this->detect_structural_patterns( $posts );
		$signals['content_gaps']          = $this->derive_content_gaps( $signals );

		$existing = get_option( RankWriter_AI_Style_Profile::OPTION_KEY );
		if ( is_array( $existing ) && ! empty( $existing['raw_signals']['claude_deep_analysis'] ) ) {
			$signals['claude_deep_analysis'] = (string) $existing['raw_signals']['claude_deep_analysis'];
		}

		return $signals;
	}

	/**
	 * Classify search intent across the existing title set so the generator
	 * can mirror the dominant intent (informational / commercial / etc.).
	 */
	private function infer_audience_intent( array $titles, array $headline_buckets ) {
		$buckets = array(
			'informational' => 0,
			'commercial'    => 0,
			'transactional' => 0,
			'navigational'  => 0,
		);
		$info_kw  = array( 'what', 'why', 'how', 'guide', 'meaning', 'explain', 'difference', 'examples', 'tips', 'ideas' );
		$comm_kw  = array( 'best', 'top', 'review', 'vs', 'versus', 'comparison', 'alternatives', 'cheap', 'affordable', 'recommended' );
		$trans_kw = array( 'buy', 'apply', 'download', 'sign up', 'subscribe', 'order', 'price', 'pricing', 'discount', 'coupon', 'deal' );

		foreach ( $titles as $t ) {
			$low = strtolower( $t );
			$matched = false;
			foreach ( $trans_kw as $kw ) {
				if ( false !== strpos( $low, $kw ) ) {
					$buckets['transactional']++;
					$matched = true;
					break;
				}
			}
			if ( $matched ) {
				continue;
			}
			foreach ( $comm_kw as $kw ) {
				if ( false !== strpos( $low, $kw ) ) {
					$buckets['commercial']++;
					$matched = true;
					break;
				}
			}
			if ( $matched ) {
				continue;
			}
			foreach ( $info_kw as $kw ) {
				if ( false !== strpos( $low, $kw ) ) {
					$buckets['informational']++;
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				$buckets['navigational']++;
			}
		}

		$total = array_sum( $buckets );
		$pct   = array();
		foreach ( $buckets as $k => $v ) {
			$pct[ $k ] = $total ? round( ( $v / $total ) * 100, 1 ) : 0;
		}

		$dominant = '';
		$max      = 0;
		foreach ( $buckets as $k => $v ) {
			if ( $v > $max ) {
				$max      = $v;
				$dominant = $k;
			}
		}

		return array(
			'distribution_counts' => $buckets,
			'distribution_pct'    => $pct,
			'dominant'            => $dominant,
		);
	}

	/**
	 * Bigram + trigram extraction from titles → the topics the blog already
	 * covers. Used to warn the generator against duplicating coverage.
	 */
	private function extract_topic_phrases( array $titles ) {
		$bigrams  = array();
		$trigrams = array();
		$stop     = array_flip( RankWriter_AI_Helpers::stopwords() );

		foreach ( $titles as $t ) {
			$words = preg_split( '/\s+/', strtolower( preg_replace( '/[^a-z0-9\s]/i', ' ', $t ) ) );
			if ( ! is_array( $words ) ) {
				continue;
			}
			$words = array_values( array_filter( $words, function ( $w ) use ( $stop ) {
				return strlen( $w ) >= 3 && ! isset( $stop[ $w ] );
			} ) );
			$n = count( $words );
			for ( $i = 0; $i < $n - 1; $i++ ) {
				$pair = $words[ $i ] . ' ' . $words[ $i + 1 ];
				$bigrams[ $pair ] = isset( $bigrams[ $pair ] ) ? $bigrams[ $pair ] + 1 : 1;
			}
			for ( $i = 0; $i < $n - 2; $i++ ) {
				$trip = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
				$trigrams[ $trip ] = isset( $trigrams[ $trip ] ) ? $trigrams[ $trip ] + 1 : 1;
			}
		}

		arsort( $bigrams );
		arsort( $trigrams );
		$bigrams  = array_filter( $bigrams, function ( $c ) { return $c >= 2; } );
		$trigrams = array_filter( $trigrams, function ( $c ) { return $c >= 2; } );

		return array(
			'bigrams'  => array_slice( $bigrams, 0, 20, true ),
			'trigrams' => array_slice( $trigrams, 0, 15, true ),
		);
	}

	private function infer_image_style( array $exts, array $kw_hits ) {
		if ( empty( $exts ) ) {
			return 'no-images';
		}
		$total = array_sum( $exts );
		if ( ! empty( $exts['svg'] ) && ( $exts['svg'] / max( 1, $total ) ) > 0.4 ) {
			return 'illustration-vector';
		}
		if ( $kw_hits['infographic'] > 0 && $kw_hits['infographic'] >= $kw_hits['photo'] ) {
			return 'infographic-heavy';
		}
		if ( $kw_hits['screenshot'] > 0 ) {
			return 'screenshot-product-shot';
		}
		if ( $kw_hits['illustration'] > $kw_hits['photo'] ) {
			return 'illustration';
		}
		return 'realistic-photography';
	}

	/**
	 * Build a content-gap list distinct from "expansion opportunities":
	 * categories with few posts, missing intent buckets, topic phrases that
	 * appear in tags but barely in titles, low keyword diversity, etc.
	 */
	private function derive_content_gaps( array $s ) {
		$gaps = array();

		if ( ! empty( $s['top_categories'] ) ) {
			foreach ( $s['top_categories'] as $c ) {
				if ( $c['count'] >= 1 && $c['count'] < 5 ) {
					$gaps[] = array(
						'type'       => 'thin-category',
						'gap'        => sprintf( 'Category "%s" has only %d posts', $c['name'], $c['count'] ),
						'suggestion' => sprintf( 'Build 5-10 more articles under "%s" to establish topical authority', $c['name'] ),
					);
				}
			}
		}

		$intent = isset( $s['audience_intent']['distribution_pct'] ) ? $s['audience_intent']['distribution_pct'] : array();
		foreach ( $intent as $type => $pct ) {
			if ( $pct < 5 && 'navigational' !== $type ) {
				$gaps[] = array(
					'type'       => 'missing-intent',
					'gap'        => sprintf( 'Almost no %s-intent content (%s%%)', $type, $pct ),
					'suggestion' => sprintf( 'Add %s posts to cover the full funnel', $type ),
				);
			}
		}

		if ( ! empty( $s['top_tags'] ) ) {
			$title_blob = strtolower( implode( ' | ', isset( $s['titles'] ) ? $s['titles'] : array() ) );
			foreach ( array_slice( $s['top_tags'], 0, 15 ) as $tag ) {
				$tname = strtolower( $tag['name'] );
				if ( $tag['count'] >= 3 && false === strpos( $title_blob, $tname ) ) {
					$gaps[] = array(
						'type'       => 'tag-without-titles',
						'gap'        => sprintf( 'Tag "%s" is used on %d posts but appears in no titles', $tag['name'], $tag['count'] ),
						'suggestion' => sprintf( 'Create a pillar post explicitly targeting "%s"', $tag['name'] ),
					);
				}
			}
		}

		if ( ! empty( $s['top_keywords'] ) && count( $s['top_keywords'] ) < 20 ) {
			$gaps[] = array(
				'type'       => 'narrow-vocabulary',
				'gap'        => sprintf( 'Only %d recurring keywords detected — content vocabulary is narrow', count( $s['top_keywords'] ) ),
				'suggestion' => 'Diversify topics within your niche to capture more long-tail search demand',
			);
		}

		if ( isset( $s['avg_internal_links'] ) && $s['avg_internal_links'] < 2 && ! empty( $s['top_categories'] ) ) {
			$gaps[] = array(
				'type'       => 'no-topic-clusters',
				'gap'        => 'Low internal linking suggests no topic-cluster strategy in place',
				'suggestion' => 'Pick top 2-3 categories and build hub-and-spoke clusters with strong internal links',
			);
		}

		return $gaps;
	}

	private function fetch_posts() {
		return get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => $this->limit,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
	}

	private function avg( array $values ) {
		$n = count( $values );
		if ( ! $n ) {
			return 0;
		}
		return round( array_sum( $values ) / $n, 2 );
	}

	private function get_meta_description( $post_id ) {
		$candidates = array(
			'_yoast_wpseo_metadesc',
			'rank_math_description',
			'_aioseop_description',
			'_aioseo_description',
			'_seopress_titles_desc',
		);
		foreach ( $candidates as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) ) {
				return trim( (string) $val );
			}
		}
		$post = get_post( $post_id );
		return $post && '' !== $post->post_excerpt ? trim( (string) $post->post_excerpt ) : '';
	}

	private function classify_headline( $title ) {
		$t = strtolower( $title );
		if ( preg_match( '/^\s*\d+\s+/', $t ) || preg_match( '/\btop\s+\d+\b/', $t ) ) {
			return 'listicle';
		}
		if ( 0 === strpos( $t, 'how to' ) || 0 === strpos( $t, 'how-to' ) ) {
			return 'how-to';
		}
		if ( preg_match( '/\bbest\b/', $t ) ) {
			return 'best-of';
		}
		if ( 0 === strpos( $t, 'why ' ) ) {
			return 'why';
		}
		if ( preg_match( '/\b(vs|versus)\b/', $t ) ) {
			return 'comparison';
		}
		if ( preg_match( '/\bvisa|sponsorship|salary|job\b/', $t ) ) {
			return 'jobs-visa';
		}
		if ( preg_match( '/\b(review)\b/', $t ) ) {
			return 'review';
		}
		if ( '?' === substr( trim( $title ), -1 ) ) {
			return 'question';
		}
		return 'descriptive';
	}

	private function classify_monetization( $ads, $affiliate, $amazon, $cta, $n ) {
		if ( ! $n ) {
			return 'unknown';
		}
		$max  = max( $ads, $affiliate, $amazon, $cta );
		if ( 0 === $max ) {
			return 'none-detected';
		}
		if ( $ads === $max ) {
			return 'adsense-heavy';
		}
		if ( $amazon === $max ) {
			return 'amazon-affiliate';
		}
		if ( $affiliate === $max ) {
			return 'affiliate-heavy';
		}
		return 'lead-gen-cta';
	}

	private function top_terms( $taxonomy, $limit ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => $limit,
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => (int) $t->count,
			);
		}
		return $out;
	}

	private function publishing_cadence() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT DATE_FORMAT(post_date, '%Y-%m') AS ym, COUNT(*) AS c
			 FROM {$wpdb->posts}
			 WHERE post_type='post' AND post_status='publish'
			 GROUP BY ym
			 ORDER BY ym DESC
			 LIMIT 12"
		);
		$out = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$out[ $r->ym ] = (int) $r->c;
			}
		}
		$months = count( $out );
		$total  = array_sum( $out );
		$out['_per_month_avg'] = $months ? round( $total / $months, 2 ) : 0;
		return $out;
	}

	private function top_performing_posts() {
		$candidates = array(
			'_jetpack_post_views',
			'post_views_count',     // WP-PostViews
			'wpb_post_views_count',
			'_aaa_post_views_count',
		);
		global $wpdb;
		foreach ( $candidates as $meta_key ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, CAST(pm.meta_value AS UNSIGNED) AS views
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					 WHERE p.post_type='post' AND p.post_status='publish' AND pm.meta_key=%s
					 ORDER BY views DESC
					 LIMIT 10",
					$meta_key
				)
			);
			if ( ! empty( $rows ) ) {
				$out = array();
				foreach ( $rows as $r ) {
					$out[] = array(
						'id'     => (int) $r->ID,
						'title'  => $r->post_title,
						'views'  => (int) $r->views,
						'source' => $meta_key,
					);
				}
				return $out;
			}
		}
		// Fallback: most-commented as a weak engagement signal.
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, comment_count
			 FROM {$wpdb->posts}
			 WHERE post_type='post' AND post_status='publish' AND comment_count > 0
			 ORDER BY comment_count DESC
			 LIMIT 10"
		);
		$out = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$out[] = array(
					'id'       => (int) $r->ID,
					'title'    => $r->post_title,
					'comments' => (int) $r->comment_count,
					'source'   => 'comment_count_fallback',
				);
			}
		}
		return $out;
	}

	/**
	 * Opt-in Claude pass that reads 5-10 sample posts and writes a prose
	 * description of voice, tone, target reader, and recurring story beats.
	 * Stored on the style profile as `claude_deep_analysis`.
	 */
	public function run_claude_deep_analysis() {
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'orderby'        => 'rand',
			)
		);

		if ( empty( $posts ) ) {
			return new WP_Error( 'rwai_no_posts', __( 'No published posts to analyze.', 'rankwriter-ai' ) );
		}

		$samples = array();
		foreach ( $posts as $p ) {
			$plain = wp_strip_all_tags( $p->post_content );
			$plain = preg_replace( '/\s+/', ' ', $plain );
			$plain = mb_substr( trim( $plain ), 0, 1200 );
			$samples[] = "TITLE: " . $p->post_title . "\nEXCERPT: " . $plain;
		}

		$system = "You are a senior content strategist. You analyze writing samples and describe the blog's voice, tone, intended reader, recurring story beats, monetization signals, and weaknesses. You write tight, decision-grade prose for an editor — no fluff, no markdown headings, no preamble.";

		$user = "Below are excerpts from 8 random posts on a WordPress blog. In 200-300 words total, describe:\n"
			. "1. The dominant voice and tone (concrete adjectives, not generic ones)\n"
			. "2. The intended reader (who they are, what they want)\n"
			. "3. Recurring story beats or structural moves the writer favors\n"
			. "4. How monetization shows up (or doesn't) in the prose\n"
			. "5. The single biggest weakness you'd fix if you owned this blog\n\n"
			. "Return plain prose. No bullets. No headings.\n\n"
			. "---\n\n"
			. implode( "\n\n---\n\n", $samples );

		$text = $client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$style = new RankWriter_AI_Style_Profile();
		$current = $style->get();
		if ( ! is_array( $current ) || empty( $current ) ) {
			return new WP_Error( 'rwai_no_profile', __( 'Run the blog analyzer first before deep analysis.', 'rankwriter-ai' ) );
		}

		$current['claude_deep_analysis'] = (string) $text;
		if ( ! isset( $current['raw_signals'] ) || ! is_array( $current['raw_signals'] ) ) {
			$current['raw_signals'] = array();
		}
		$current['raw_signals']['claude_deep_analysis'] = (string) $text;
		update_option( RankWriter_AI_Style_Profile::OPTION_KEY, $current, false );

		return (string) $text;
	}

	/**
	 * Detect concrete structural patterns the writer uses across posts:
	 * FAQ sections, comparison tables, numbered step lists, salary/price
	 * mentions, company-name lists, product roundups, emotional-hook intros.
	 *
	 * This is what makes the generator continue patterns like
	 * "salary ranges + company lists + application steps + FAQs" or
	 * "emotional storytelling + practical tips + product recommendations"
	 * without the user spelling them out.
	 */
	private function detect_structural_patterns( array $posts ) {
		$patterns = array(
			'faq_section'         => 0,
			'comparison_table'    => 0,
			'numbered_steps'      => 0,
			'salary_range_mentions' => 0,
			'price_mentions'      => 0,
			'company_list'        => 0,
			'product_roundup'     => 0,
			'emotional_hook_intro'=> 0,
			'stats_with_citation' => 0,
			'tl_dr_summary'       => 0,
			'cta_block'           => 0,
			'pros_cons_lists'     => 0,
		);
		$n = count( $posts );
		if ( ! $n ) {
			return array( 'sample_size' => 0, 'patterns' => $patterns, 'percentages' => array(), 'top' => array() );
		}

		$emotion_kw = array( 'i remember when', 'my own', 'we struggled', 'when i first', 'felt overwhelmed', 'heartbreaking', 'unforgettable' );
		$cta_kw     = array( 'apply now', 'get started', 'sign up', 'download', 'subscribe', 'buy now', 'order now' );
		$pros_cons  = array( 'pros', 'cons' );

		foreach ( $posts as $p ) {
			$html  = (string) $p->post_content;
			$lower = strtolower( wp_strip_all_tags( $html ) );
			$first_para = '';
			if ( preg_match( '#<p\b[^>]*>(.+?)</p>#is', $html, $fp ) ) {
				$first_para = strtolower( wp_strip_all_tags( $fp[1] ) );
			}

			if ( preg_match( '#<h[23][^>]*>[^<]*\?[^<]*</h[23]>#i', $html ) || false !== stripos( $lower, 'frequently asked' ) ) {
				$patterns['faq_section']++;
			}
			if ( preg_match( '#<table\b#i', $html ) ) {
				$patterns['comparison_table']++;
			}
			if ( preg_match( '#<ol\b#i', $html ) || preg_match( '/\bstep\s*\d+\b/i', $lower ) ) {
				$patterns['numbered_steps']++;
			}
			if ( preg_match( '/\$\s?\d{1,3}(,\d{3})+(\s*[-–]\s*\$\s?\d{1,3}(,\d{3})+)?\b/', $html ) || preg_match( '/\bsalary\b.{0,30}\$\d/i', $html ) || preg_match( '/\b\d{2,3}k\s*[-–]\s*\d{2,3}k\b/i', $lower ) ) {
				$patterns['salary_range_mentions']++;
			}
			if ( preg_match( '/\$\s?\d+(\.\d{2})?\b/', $html ) ) {
				$patterns['price_mentions']++;
			}
			if ( preg_match_all( '#<(h[23]|li)[^>]*>\s*[A-Z][A-Za-z0-9&\.\- ]{2,30}\s*(Inc|LLC|Ltd|Corp|Group|Company)?\s*</#', $html, $mc ) ) {
				if ( $mc && count( $mc[0] ) >= 3 ) {
					$patterns['company_list']++;
				}
			}
			if ( preg_match( '/\b(best|top)\s+\d+\b/i', (string) $p->post_title ) || preg_match( '/\bproduct(s)?\b.{0,30}\b(recommend|review)/i', $lower ) ) {
				$patterns['product_roundup']++;
			}
			if ( '' !== $first_para ) {
				foreach ( $emotion_kw as $kw ) {
					if ( false !== strpos( $first_para, $kw ) ) {
						$patterns['emotional_hook_intro']++;
						break;
					}
				}
			}
			if ( preg_match( '/\b\d{1,3}(\.\d+)?\s*(%|percent)\b/i', $html ) ) {
				$patterns['stats_with_citation']++;
			}
			if ( preg_match( '/\b(tl;?dr|in short|quick answer)\b/i', $lower ) ) {
				$patterns['tl_dr_summary']++;
			}
			foreach ( $cta_kw as $kw ) {
				if ( false !== strpos( $lower, $kw ) ) {
					$patterns['cta_block']++;
					break;
				}
			}
			$has_pros = false; $has_cons = false;
			foreach ( $pros_cons as $kw ) {
				if ( false !== strpos( $lower, "\n" . $kw ) || preg_match( '#<(h[23]|strong)[^>]*>\s*' . $kw . '\b#i', $html ) ) {
					if ( 'pros' === $kw ) { $has_pros = true; }
					if ( 'cons' === $kw ) { $has_cons = true; }
				}
			}
			if ( $has_pros && $has_cons ) {
				$patterns['pros_cons_lists']++;
			}
		}

		$pct = array();
		foreach ( $patterns as $k => $v ) {
			$pct[ $k ] = round( ( $v / $n ) * 100, 1 );
		}

		// "Top" = patterns appearing in >= 30% of posts.
		$top = array();
		foreach ( $pct as $k => $p ) {
			if ( $p >= 30 ) {
				$top[ $k ] = $p;
			}
		}
		arsort( $top );

		return array(
			'sample_size' => $n,
			'patterns'    => $patterns,
			'percentages' => $pct,
			'top'         => $top,
		);
	}

	private function find_orphans( array $inbound ) {
		$recent = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$orphans = array();
		foreach ( $recent as $p ) {
			$slug = $p->post_name;
			if ( ! isset( $inbound[ $slug ] ) || 0 === $inbound[ $slug ] ) {
				$orphans[] = array(
					'id'    => $p->ID,
					'title' => $p->post_title,
					'slug'  => $slug,
				);
			}
			if ( count( $orphans ) >= 15 ) {
				break;
			}
		}
		return $orphans;
	}
}
