<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the active SEO plugin (Rank Math, Yoast SEO, All in One SEO, SEOPress)
 * and writes meta title, meta description, focus keyword, OG fields, and a
 * basic schema hint directly into its post-meta convention.
 *
 * Falls back to a plain post-meta store + post_excerpt when no SEO plugin
 * is detected so the article still ships with usable SEO data.
 */
class RankWriter_AI_SEO_Integration {

	const FALLBACK_TITLE_KEY = '_rwai_seo_title';
	const FALLBACK_DESC_KEY  = '_rwai_seo_description';
	const FALLBACK_FOCUS_KEY = '_rwai_seo_focus_keyword';

	public function detect_plugin() {
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			return 'rank-math';
		}
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			return 'yoast';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_get_service' ) ) {
			return 'seopress';
		}
		return 'none';
	}

	public function detected_label() {
		$labels = array(
			'rank-math' => 'Rank Math',
			'yoast'     => 'Yoast SEO',
			'aioseo'    => 'All in One SEO',
			'seopress'  => 'SEOPress',
			'none'      => __( 'None detected (using fallback meta)', 'rankwriter-ai' ),
		);
		$key = $this->detect_plugin();
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels['none'];
	}

	/**
	 * Write SEO meta to whichever SEO plugin is active.
	 *
	 * @param int   $post_id
	 * @param array $seo {
	 *     @type string $title         SEO title (50-60 chars).
	 *     @type string $description   Meta description (150-160 chars).
	 *     @type string $focus_keyword Primary focus keyword.
	 *     @type array  $secondary     Optional secondary keywords.
	 *     @type string $og_title
	 *     @type string $og_description
	 *     @type string $canonical
	 *     @type string $schema_type   e.g. Article, HowTo, FAQPage.
	 * }
	 */
	public function write_meta( $post_id, array $seo ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$title       = isset( $seo['title'] ) ? sanitize_text_field( $seo['title'] ) : '';
		$desc        = isset( $seo['description'] ) ? sanitize_text_field( $seo['description'] ) : '';
		$focus       = isset( $seo['focus_keyword'] ) ? sanitize_text_field( $seo['focus_keyword'] ) : '';
		$secondary   = isset( $seo['secondary'] ) && is_array( $seo['secondary'] ) ? array_map( 'sanitize_text_field', $seo['secondary'] ) : array();
		$og_title    = isset( $seo['og_title'] ) ? sanitize_text_field( $seo['og_title'] ) : $title;
		$og_desc     = isset( $seo['og_description'] ) ? sanitize_text_field( $seo['og_description'] ) : $desc;
		$canonical   = isset( $seo['canonical'] ) ? esc_url_raw( $seo['canonical'] ) : '';
		$schema_type = isset( $seo['schema_type'] ) ? sanitize_text_field( $seo['schema_type'] ) : 'Article';

		switch ( $this->detect_plugin() ) {
			case 'rank-math':
				update_post_meta( $post_id, 'rank_math_title', $title );
				update_post_meta( $post_id, 'rank_math_description', $desc );
				update_post_meta( $post_id, 'rank_math_focus_keyword', implode( ',', array_filter( array_merge( array( $focus ), $secondary ) ) ) );
				update_post_meta( $post_id, 'rank_math_facebook_title', $og_title );
				update_post_meta( $post_id, 'rank_math_facebook_description', $og_desc );
				update_post_meta( $post_id, 'rank_math_twitter_title', $og_title );
				update_post_meta( $post_id, 'rank_math_twitter_description', $og_desc );
				if ( $canonical ) {
					update_post_meta( $post_id, 'rank_math_canonical_url', $canonical );
				}
				update_post_meta( $post_id, 'rank_math_rich_snippet', strtolower( $schema_type ) );
				break;

			case 'yoast':
				update_post_meta( $post_id, '_yoast_wpseo_title', $title );
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus );
				if ( ! empty( $secondary ) ) {
					update_post_meta( $post_id, '_yoast_wpseo_keywordsynonyms', wp_json_encode( $secondary ) );
				}
				update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $og_title );
				update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $og_desc );
				update_post_meta( $post_id, '_yoast_wpseo_twitter-title', $og_title );
				update_post_meta( $post_id, '_yoast_wpseo_twitter-description', $og_desc );
				if ( $canonical ) {
					update_post_meta( $post_id, '_yoast_wpseo_canonical', $canonical );
				}
				update_post_meta( $post_id, '_yoast_wpseo_schema_article_type', $schema_type );
				break;

			case 'aioseo':
				update_post_meta( $post_id, '_aioseo_title', $title );
				update_post_meta( $post_id, '_aioseo_description', $desc );
				update_post_meta( $post_id, '_aioseo_keywords', implode( ',', array_filter( array_merge( array( $focus ), $secondary ) ) ) );
				update_post_meta( $post_id, '_aioseo_og_title', $og_title );
				update_post_meta( $post_id, '_aioseo_og_description', $og_desc );
				update_post_meta( $post_id, '_aioseo_twitter_title', $og_title );
				update_post_meta( $post_id, '_aioseo_twitter_description', $og_desc );
				if ( $canonical ) {
					update_post_meta( $post_id, '_aioseo_canonical_url', $canonical );
				}
				break;

			case 'seopress':
				update_post_meta( $post_id, '_seopress_titles_title', $title );
				update_post_meta( $post_id, '_seopress_titles_desc', $desc );
				update_post_meta( $post_id, '_seopress_analysis_target_kw', implode( ',', array_filter( array_merge( array( $focus ), $secondary ) ) ) );
				update_post_meta( $post_id, '_seopress_social_fb_title', $og_title );
				update_post_meta( $post_id, '_seopress_social_fb_desc', $og_desc );
				update_post_meta( $post_id, '_seopress_social_twitter_title', $og_title );
				update_post_meta( $post_id, '_seopress_social_twitter_desc', $og_desc );
				if ( $canonical ) {
					update_post_meta( $post_id, '_seopress_robots_canonical', $canonical );
				}
				break;

			case 'none':
			default:
				update_post_meta( $post_id, self::FALLBACK_TITLE_KEY, $title );
				update_post_meta( $post_id, self::FALLBACK_DESC_KEY, $desc );
				update_post_meta( $post_id, self::FALLBACK_FOCUS_KEY, $focus );
				if ( $desc ) {
					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_excerpt' => $desc,
						)
					);
				}
				break;
		}

		update_post_meta( $post_id, '_rwai_seo_written', 1 );
		update_post_meta( $post_id, '_rwai_seo_target', $this->detect_plugin() );
		return true;
	}
}
