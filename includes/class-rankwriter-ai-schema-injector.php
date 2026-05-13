<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds JSON-LD structured data based on the schema_type returned by Claude
 * (Article, HowTo, FAQPage, NewsArticle, Product) and either:
 *   - hands it off to the active SEO plugin if one is present (Rank Math /
 *     Yoast can render their own schema), OR
 *   - injects the JSON-LD <script> block directly via wp_head when no SEO
 *     plugin is active.
 */
class RankWriter_AI_Schema_Injector {

	const META_KEY = '_rwai_schema_jsonld';

	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'maybe_print_schema' ), 30 );
	}

	/**
	 * Build a JSON-LD payload from a generated post and persist it.
	 */
	public function build_and_save( $post_id, $schema_type, $content_html, $focus_keyword = '' ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$schema_type = $this->normalize_type( $schema_type );
		$author      = get_the_author_meta( 'display_name', $post->post_author );
		if ( ! $author ) {
			$author = get_bloginfo( 'name' );
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
			'headline' => $post->post_title,
			'datePublished' => get_the_date( DATE_W3C, $post ),
			'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		if ( $focus_keyword ) {
			$payload['keywords'] = $focus_keyword;
		}

		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $src ) {
				$payload['image'] = $src[0];
			}
		}

		if ( 'FAQPage' === $schema_type ) {
			$faqs = $this->extract_faqs( $content_html );
			if ( ! empty( $faqs ) ) {
				$payload['mainEntity'] = array();
				foreach ( $faqs as $faq ) {
					$payload['mainEntity'][] = array(
						'@type'          => 'Question',
						'name'           => $faq['q'],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $faq['a'],
						),
					);
				}
			} else {
				// Downgrade: no Q/A detected, fall back to Article.
				$payload['@type'] = 'Article';
			}
		}

		if ( 'HowTo' === $schema_type ) {
			$steps = $this->extract_howto_steps( $content_html );
			if ( ! empty( $steps ) ) {
				$payload['name'] = $post->post_title;
				$payload['step'] = array();
				foreach ( $steps as $i => $s ) {
					$payload['step'][] = array(
						'@type'    => 'HowToStep',
						'position' => $i + 1,
						'name'     => $s['name'],
						'text'     => $s['text'],
					);
				}
			} else {
				$payload['@type'] = 'Article';
			}
		}

		update_post_meta( $post_id, self::META_KEY, $payload );
		return $payload;
	}

	public function maybe_print_schema() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post_id = get_the_ID();
		$payload = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return;
		}

		// If a major SEO plugin is active it already prints schema; avoid duplicates.
		$seo = new RankWriter_AI_SEO_Integration();
		if ( in_array( $seo->detect_plugin(), array( 'rank-math', 'yoast' ), true ) ) {
			return;
		}

		echo "\n<!-- RankWriter AI JSON-LD -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	private function normalize_type( $type ) {
		$type = trim( (string) $type );
		$allowed = array( 'Article', 'BlogPosting', 'NewsArticle', 'HowTo', 'FAQPage', 'Product', 'Review' );
		foreach ( $allowed as $a ) {
			if ( 0 === strcasecmp( $a, $type ) ) {
				return $a;
			}
		}
		return 'Article';
	}

	/**
	 * Detect FAQ pairs from typical patterns:
	 *   <h2>Question?</h2><p>Answer.</p>
	 *   <h3>Question?</h3><p>Answer.</p>
	 *   <strong>Q: ...</strong><br>A: ...
	 */
	private function extract_faqs( $html ) {
		$faqs = array();
		if ( preg_match_all( '#<h([23])[^>]*>([^<]*\?)\s*</h\1>\s*(.+?)(?=<h[23]\b|$)#is', $html, $m ) ) {
			foreach ( $m[2] as $i => $question ) {
				$answer = trim( wp_strip_all_tags( $m[3][ $i ] ) );
				$answer = preg_replace( '/\s+/', ' ', $answer );
				if ( strlen( $answer ) > 20 ) {
					$faqs[] = array(
						'q' => trim( wp_strip_all_tags( $question ) ),
						'a' => mb_substr( $answer, 0, 600 ),
					);
				}
			}
		}
		return $faqs;
	}

	private function extract_howto_steps( $html ) {
		$steps = array();
		// Pattern A: <h2>Step 1: name</h2><p>text</p>
		if ( preg_match_all( '#<h([23])[^>]*>\s*(?:step\s*\d+[:\s\-]+)?([^<]+)</h\1>\s*(.+?)(?=<h[23]\b|$)#is', $html, $m ) ) {
			foreach ( $m[2] as $i => $name ) {
				$text = trim( wp_strip_all_tags( $m[3][ $i ] ) );
				if ( '' !== $text ) {
					$steps[] = array(
						'name' => trim( $name ),
						'text' => mb_substr( $text, 0, 500 ),
					);
				}
			}
		}
		// Cap at 12 steps so we don't bloat the JSON.
		return array_slice( $steps, 0, 12 );
	}
}
