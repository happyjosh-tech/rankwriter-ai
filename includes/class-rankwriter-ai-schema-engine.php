<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema Automation Engine.
 *
 * Replaces the older single-type injector with a full multi-schema `@graph`
 * generator. For every post we emit a graph containing:
 *
 *   - Organization (sitewide, references the Site's logo + social profiles)
 *   - WebSite (with a SearchAction)
 *   - BreadcrumbList (Home › Category › Post)
 *   - The primary post type (Article / Recipe / JobPosting / Event / Review /
 *     HowTo, etc., auto-detected from content)
 *   - FAQPage (additive — added whenever Q/A pairs are detected, regardless
 *     of primary type)
 *
 * Detection is keyword + structure scored; the user can override per-post.
 * Output skips itself when Rank Math / Yoast / SEOPress are active so we
 * never duplicate. Output is one `<script type="application/ld+json">`
 * block per page — lightweight regardless of how many types are present.
 */
class RankWriter_AI_Schema_Engine {

	const META_GRAPH       = '_rwai_schema_graph';
	const META_TYPE        = '_rwai_schema_primary_type';
	const META_FAQ_OPTOUT  = '_rwai_schema_skip_faq';
	const OPTION_ORG       = 'rwai_schema_organization';

	const TYPE_ARTICLE    = 'Article';
	const TYPE_BLOGPOST   = 'BlogPosting';
	const TYPE_NEWS       = 'NewsArticle';
	const TYPE_FAQ        = 'FAQPage';
	const TYPE_HOWTO      = 'HowTo';
	const TYPE_REVIEW     = 'Review';
	const TYPE_RECIPE     = 'Recipe';
	const TYPE_JOB        = 'JobPosting';
	const TYPE_EVENT      = 'Event';
	const TYPE_PRODUCT    = 'Product';

	public static function available_primary_types() {
		return array(
			self::TYPE_ARTICLE  => __( 'Article (default)', 'rankwriter-ai' ),
			self::TYPE_BLOGPOST => __( 'BlogPosting', 'rankwriter-ai' ),
			self::TYPE_NEWS     => __( 'NewsArticle', 'rankwriter-ai' ),
			self::TYPE_HOWTO    => __( 'HowTo', 'rankwriter-ai' ),
			self::TYPE_REVIEW   => __( 'Review', 'rankwriter-ai' ),
			self::TYPE_RECIPE   => __( 'Recipe', 'rankwriter-ai' ),
			self::TYPE_JOB      => __( 'JobPosting', 'rankwriter-ai' ),
			self::TYPE_EVENT    => __( 'Event', 'rankwriter-ai' ),
			self::TYPE_PRODUCT  => __( 'Product', 'rankwriter-ai' ),
		);
	}

	/* ============================ Organization settings ============================ */

	public static function organization_settings() {
		$defaults = array(
			'name'    => get_bloginfo( 'name' ),
			'logo'    => self::default_logo_url(),
			'sameas'  => '',
			'phone'   => '',
			'email'   => '',
			'address' => '',
		);
		$saved = get_option( self::OPTION_ORG, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function save_organization_settings( array $values ) {
		$merged = array(
			'name'    => sanitize_text_field( $values['name'] ?? '' ),
			'logo'    => esc_url_raw( $values['logo'] ?? '' ),
			'sameas'  => sanitize_textarea_field( $values['sameas'] ?? '' ),
			'phone'   => sanitize_text_field( $values['phone'] ?? '' ),
			'email'   => sanitize_email( $values['email'] ?? '' ),
			'address' => sanitize_textarea_field( $values['address'] ?? '' ),
		);
		update_option( self::OPTION_ORG, $merged );
		return $merged;
	}

	protected static function default_logo_url() {
		$icon = get_site_icon_url( 512 );
		if ( $icon ) { return $icon; }
		if ( function_exists( 'get_custom_logo' ) ) {
			$custom = get_theme_mod( 'custom_logo' );
			if ( $custom ) {
				$src = wp_get_attachment_image_src( (int) $custom, 'full' );
				if ( $src ) { return $src[0]; }
			}
		}
		return '';
	}

	/* ============================ Auto-detection ============================ */

	/**
	 * Score-based detector. Each pattern contributes points to a candidate
	 * type. Highest non-Article score wins; ties default to Article.
	 */
	public function detect_primary_type( $post ) {
		$title   = strtolower( (string) $post->post_title );
		$content = (string) $post->post_content;
		$text    = strtolower( wp_strip_all_tags( $content ) );

		$scores = array(
			self::TYPE_RECIPE => 0,
			self::TYPE_JOB    => 0,
			self::TYPE_EVENT  => 0,
			self::TYPE_REVIEW => 0,
			self::TYPE_HOWTO  => 0,
			self::TYPE_NEWS   => 0,
		);

		// Recipe signals
		if ( preg_match( '/\b(recipe|ingredients|directions|prep\s+time|cook\s+time|servings|yield)\b/', $text ) ) {
			$scores[ self::TYPE_RECIPE ] += 5;
		}
		if ( preg_match( '/\b\d+\s+(cups?|tbsp|tsp|teaspoons?|tablespoons?|ounces?|grams?|pounds?|ml)\b/', $text ) ) {
			$scores[ self::TYPE_RECIPE ] += 3;
		}
		if ( strpos( $title, 'recipe' ) !== false ) {
			$scores[ self::TYPE_RECIPE ] += 4;
		}

		// JobPosting signals
		if ( preg_match( '/\b(job|position|hiring|apply\s+now|salary|qualifications?|responsibilities|requirements|benefits|remote\s+job|full[-\s]?time|part[-\s]?time)\b/', $text ) ) {
			$scores[ self::TYPE_JOB ] += 3;
		}
		if ( preg_match( '/\b(\$|£|€)\s?\d{2,3}[,\.]?\d{3}/', $text ) && strpos( $text, 'salary' ) !== false ) {
			$scores[ self::TYPE_JOB ] += 4;
		}

		// Event signals
		if ( preg_match( '/\b(event|conference|summit|workshop|webinar|venue|tickets?|registration)\b/', $text ) ) {
			$scores[ self::TYPE_EVENT ] += 3;
		}
		if ( preg_match( '/\b(starts?|begins?|opens?)\s+on\b/', $text ) ) {
			$scores[ self::TYPE_EVENT ] += 2;
		}

		// Review signals
		if ( preg_match( '/\b(review|verdict|pros\s+and\s+cons|rating|score|out\s+of\s+(?:5|10))\b/', $text ) ) {
			$scores[ self::TYPE_REVIEW ] += 3;
		}
		if ( preg_match( '/[★☆]{3,}|\b\d(?:\.\d)?\s*\/\s*(?:5|10)\b/', $content ) ) {
			$scores[ self::TYPE_REVIEW ] += 4;
		}
		if ( strpos( $title, ' review' ) !== false || preg_match( '/^review:/', $title ) ) {
			$scores[ self::TYPE_REVIEW ] += 5;
		}

		// HowTo signals — distinct from generic listicle
		if ( preg_match( '/^how\s+to\b/i', $post->post_title ) ) {
			$scores[ self::TYPE_HOWTO ] += 4;
		}
		if ( preg_match_all( '/<h[23][^>]*>\s*step\s*\d+\b/i', $content ) >= 2 ) {
			$scores[ self::TYPE_HOWTO ] += 5;
		}

		// News — date-anchored, "breaking", "announced", recent year + present
		// tense pattern. Conservative because everything that's blogpost-y
		// also looks newsy in places.
		if ( preg_match( '/\b(breaking|announced|reveals?|reports?\s+say|today|yesterday)\b/', $text ) ) {
			$scores[ self::TYPE_NEWS ] += 1;
		}

		arsort( $scores );
		$top_type  = key( $scores );
		$top_score = current( $scores );

		// Minimum confidence threshold — below this, fall back to Article.
		// Recipe and Job need higher confidence to swap from Article since
		// they have stricter Google-required fields.
		$thresholds = array(
			self::TYPE_RECIPE => 7,
			self::TYPE_JOB    => 6,
			self::TYPE_EVENT  => 5,
			self::TYPE_REVIEW => 5,
			self::TYPE_HOWTO  => 5,
			self::TYPE_NEWS   => 4,
		);
		$threshold = $thresholds[ $top_type ] ?? 5;

		if ( $top_score < $threshold ) {
			return self::TYPE_ARTICLE;
		}
		return $top_type;
	}

	public function get_primary_type( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_TYPE, true );
		return $saved ? (string) $saved : '';
	}

	public function set_primary_type( $post_id, $type ) {
		$valid = array_keys( self::available_primary_types() );
		if ( ! in_array( $type, $valid, true ) ) {
			$type = self::TYPE_ARTICLE;
		}
		update_post_meta( $post_id, self::META_TYPE, $type );
		return $type;
	}

	/* ============================ Graph build ============================ */

	/**
	 * Build the full @graph for a post. Always includes Organization +
	 * WebSite + BreadcrumbList + the primary type. Adds FAQPage if
	 * Q/A pairs are detected and the user hasn't opted out.
	 */
	public function build_graph( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return array(); }

		$primary = $this->get_primary_type( $post_id );
		if ( ! $primary ) {
			$primary = $this->detect_primary_type( $post );
			update_post_meta( $post_id, self::META_TYPE, $primary );
		}

		$graph = array();
		$graph[] = $this->build_organization();
		$graph[] = $this->build_website();
		$graph[] = $this->build_breadcrumb( $post );

		$primary_node = $this->build_primary_node( $post, $primary );
		if ( ! empty( $primary_node ) ) {
			$graph[] = $primary_node;
		}

		// Always-additive FAQPage, unless explicitly skipped.
		if ( empty( get_post_meta( $post_id, self::META_FAQ_OPTOUT, true ) ) && self::TYPE_FAQ !== $primary ) {
			$faqs = $this->extract_faqs( $post->post_content );
			if ( count( $faqs ) >= 2 ) {
				$graph[] = $this->build_faq_node( $post, $faqs );
			}
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		return $payload;
	}

	public function build_and_save( $post_id ) {
		$payload = $this->build_graph( $post_id );
		if ( ! empty( $payload['@graph'] ) ) {
			update_post_meta( $post_id, self::META_GRAPH, $payload );
		}
		return $payload;
	}

	public function get_saved_graph( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_GRAPH, true );
		return is_array( $saved ) ? $saved : array();
	}

	/* ============================ Sitewide nodes ============================ */

	protected function build_organization() {
		$o     = self::organization_settings();
		$node  = array(
			'@type'  => 'Organization',
			'@id'    => home_url( '/#organization' ),
			'name'   => $o['name'] ?: get_bloginfo( 'name' ),
			'url'    => home_url( '/' ),
		);
		if ( ! empty( $o['logo'] ) ) {
			$node['logo'] = array(
				'@type' => 'ImageObject',
				'@id'   => home_url( '/#logo' ),
				'url'   => $o['logo'],
			);
			$node['image'] = array( '@id' => home_url( '/#logo' ) );
		}
		$sameas = array_filter( array_map( 'trim', preg_split( '/\s+/', (string) $o['sameas'] ) ) );
		if ( ! empty( $sameas ) ) {
			$node['sameAs'] = array_values( $sameas );
		}
		if ( ! empty( $o['email'] ) ) {
			$node['email'] = $o['email'];
		}
		if ( ! empty( $o['phone'] ) ) {
			$node['telephone'] = $o['phone'];
		}
		if ( ! empty( $o['address'] ) ) {
			$node['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $o['address'],
			);
		}
		return $node;
	}

	protected function build_website() {
		$home = home_url( '/' );
		return array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => $home . '#organization' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'potentialAction' => array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					),
					'query-input' => 'required name=search_term_string',
				),
			),
		);
	}

	protected function build_breadcrumb( $post ) {
		$home  = home_url( '/' );
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'rankwriter-ai' ),
				'item'     => $home,
			),
		);
		$pos = 2;
		$cats = get_the_category( $post->ID );
		if ( ! empty( $cats ) ) {
			$cat = $cats[0];
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $cat->name,
				'item'     => get_category_link( $cat->term_id ),
			);
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => $post->post_title,
			'item'     => get_permalink( $post->ID ),
		);
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post->ID ) . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/* ============================ Primary nodes ============================ */

	protected function build_primary_node( $post, $type ) {
		switch ( $type ) {
			case self::TYPE_RECIPE:
				return $this->build_recipe_node( $post );
			case self::TYPE_JOB:
				return $this->build_job_node( $post );
			case self::TYPE_EVENT:
				return $this->build_event_node( $post );
			case self::TYPE_REVIEW:
				return $this->build_review_node( $post );
			case self::TYPE_HOWTO:
				return $this->build_howto_node( $post );
			case self::TYPE_PRODUCT:
				return $this->build_product_node( $post );
			case self::TYPE_FAQ:
				$faqs = $this->extract_faqs( $post->post_content );
				return $this->build_faq_node( $post, $faqs );
			default:
				return $this->build_article_node( $post, $type );
		}
	}

	protected function article_base( $post ) {
		$home   = home_url( '/' );
		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( ! $author ) { $author = get_bloginfo( 'name' ); }
		$node = array(
			'@id'               => get_permalink( $post->ID ) . '#primary',
			'headline'          => $post->post_title,
			'name'              => $post->post_title,
			'datePublished'     => get_the_date( DATE_W3C, $post ),
			'dateModified'      => get_the_modified_date( DATE_W3C, $post ),
			'inLanguage'        => get_bloginfo( 'language' ),
			'author'            => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'publisher'         => array( '@id' => $home . '#organization' ),
			'mainEntityOfPage'  => get_permalink( $post->ID ),
		);
		$excerpt = has_excerpt( $post->ID ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
		if ( $excerpt ) {
			$node['description'] = $excerpt;
		}
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $src ) {
				$node['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}
		return $node;
	}

	protected function build_article_node( $post, $type = self::TYPE_ARTICLE ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => $type ) + $node;
		return $node;
	}

	protected function build_howto_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_HOWTO ) + $node;
		$steps = $this->extract_howto_steps( $post->post_content );
		if ( ! empty( $steps ) ) {
			$node['step'] = array();
			foreach ( $steps as $i => $s ) {
				$node['step'][] = array(
					'@type'    => 'HowToStep',
					'position' => $i + 1,
					'name'     => $s['name'],
					'text'     => $s['text'],
				);
			}
		}
		return $node;
	}

	protected function build_faq_node( $post, array $faqs ) {
		$node = array(
			'@type'      => self::TYPE_FAQ,
			'@id'        => get_permalink( $post->ID ) . '#faq',
			'mainEntity' => array(),
		);
		foreach ( $faqs as $faq ) {
			$node['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $faq['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $faq['a'],
				),
			);
		}
		return $node;
	}

	protected function build_recipe_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_RECIPE ) + $node;

		$ingredients = $this->extract_list_after_heading( $post->post_content, '/ingredient/i' );
		$instructions = $this->extract_list_after_heading( $post->post_content, '/instruction|direction|method|steps?/i' );

		if ( ! empty( $ingredients ) ) {
			$node['recipeIngredient'] = array_slice( $ingredients, 0, 50 );
		}
		if ( ! empty( $instructions ) ) {
			$node['recipeInstructions'] = array();
			foreach ( array_slice( $instructions, 0, 30 ) as $i => $step ) {
				$node['recipeInstructions'][] = array(
					'@type' => 'HowToStep',
					'position' => $i + 1,
					'text'  => $step,
				);
			}
		}

		// Extract optional fields with light regex.
		$text = wp_strip_all_tags( $post->post_content );
		if ( preg_match( '/prep\s+time[:\s]+([0-9]+)\s*(mins?|hours?|min|hr|h)/i', $text, $m ) ) {
			$node['prepTime'] = $this->iso_duration( $m[1], $m[2] );
		}
		if ( preg_match( '/cook\s+time[:\s]+([0-9]+)\s*(mins?|hours?|min|hr|h)/i', $text, $m ) ) {
			$node['cookTime'] = $this->iso_duration( $m[1], $m[2] );
		}
		if ( preg_match( '/total\s+time[:\s]+([0-9]+)\s*(mins?|hours?|min|hr|h)/i', $text, $m ) ) {
			$node['totalTime'] = $this->iso_duration( $m[1], $m[2] );
		}
		if ( preg_match( '/(?:servings?|yield)[:\s]+([0-9]+)/i', $text, $m ) ) {
			$node['recipeYield'] = (int) $m[1];
		}
		return $node;
	}

	protected function build_job_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_JOB ) + $node;
		$node['title']        = $post->post_title;
		$node['datePosted']   = get_the_date( DATE_W3C, $post );
		$node['description']  = wp_strip_all_tags( $post->post_content );
		$node['hiringOrganization'] = array( '@id' => home_url( '/#organization' ) );

		$text = wp_strip_all_tags( $post->post_content );
		if ( preg_match( '/(?:salary|pay)[:\s]+(?:\$|£|€)?\s?([0-9][0-9,]+)(?:\s*(?:-|to|–)\s*(?:\$|£|€)?\s?([0-9][0-9,]+))?/i', $text, $m ) ) {
			$min = (int) str_replace( ',', '', $m[1] );
			$max = isset( $m[2] ) ? (int) str_replace( ',', '', $m[2] ) : $min;
			$node['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => 'USD',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => $min,
					'maxValue' => $max,
					'unitText' => 'YEAR',
				),
			);
		}
		if ( preg_match( '/\b(remote|hybrid|on[-\s]?site)\b/i', $text, $m ) ) {
			$node['jobLocationType'] = ( strtolower( $m[1] ) === 'remote' ) ? 'TELECOMMUTE' : 'OnSite';
		}
		if ( preg_match( '/\b(full[-\s]?time|part[-\s]?time|contract|internship|temporary)\b/i', $text, $m ) ) {
			$type_map = array(
				'full-time' => 'FULL_TIME', 'fulltime' => 'FULL_TIME', 'full time' => 'FULL_TIME',
				'part-time' => 'PART_TIME', 'parttime' => 'PART_TIME', 'part time' => 'PART_TIME',
				'contract'  => 'CONTRACTOR', 'internship' => 'INTERN', 'temporary'  => 'TEMPORARY',
			);
			$key = strtolower( $m[1] );
			$node['employmentType'] = $type_map[ $key ] ?? 'OTHER';
		}
		return $node;
	}

	protected function build_event_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_EVENT ) + $node;

		$text = wp_strip_all_tags( $post->post_content );
		// Try to find a start date — look for "Date:" / "When:" or any
		// date pattern in the first paragraph.
		$first_para = preg_split( '/\n\n|\r\n\r\n/', $text )[0] ?? $text;
		if ( preg_match( '/\b([A-Z][a-z]+\s+\d{1,2},?\s+\d{4})\b/', $first_para, $m ) ) {
			$ts = strtotime( $m[1] );
			if ( $ts ) {
				$node['startDate'] = date( DATE_W3C, $ts );
			}
		}
		if ( preg_match( '/\bvenue[:\s]+([^.\n]{3,80})/i', $text, $m ) ) {
			$node['location'] = array(
				'@type' => 'Place',
				'name'  => trim( $m[1] ),
			);
		} else {
			$node['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
			$node['location'] = array(
				'@type' => 'VirtualLocation',
				'url'   => get_permalink( $post->ID ),
			);
		}
		$node['eventStatus'] = 'https://schema.org/EventScheduled';
		$node['organizer']   = array( '@id' => home_url( '/#organization' ) );
		return $node;
	}

	protected function build_review_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_REVIEW ) + $node;

		$content = $post->post_content;
		$rating_value = null;
		$best = 5;
		if ( preg_match( '/(\d(?:\.\d)?)\s*\/\s*(5|10)\b/', $content, $m ) ) {
			$rating_value = (float) $m[1];
			$best = (int) $m[2];
		} elseif ( preg_match_all( '/[★]/u', $content, $stars ) ) {
			$rating_value = count( $stars[0] );
			$best = 5;
		} elseif ( preg_match( '/rating[:\s]+(\d(?:\.\d)?)\s*\/\s*(5|10)/i', $content, $m ) ) {
			$rating_value = (float) $m[1];
			$best = (int) $m[2];
		}
		if ( null !== $rating_value ) {
			$node['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating_value,
				'bestRating'  => $best,
				'worstRating' => 1,
			);
		}

		// The thing being reviewed: best guess is the post title minus the
		// word "review". The user should set this manually for accuracy.
		$reviewed = trim( preg_replace( '/\s*review\s*[:\-]?\s*/i', '', $post->post_title ) );
		$node['itemReviewed'] = array(
			'@type' => 'Thing',
			'name'  => $reviewed ?: $post->post_title,
		);
		return $node;
	}

	protected function build_product_node( $post ) {
		$node = $this->article_base( $post );
		$node = array( '@type' => self::TYPE_PRODUCT ) + $node;
		$node['name'] = $post->post_title;
		return $node;
	}

	/* ============================ Validation ============================ */

	/**
	 * Validate the graph against Google's required-field rules per type.
	 * Returns an array of warnings. Empty array = valid.
	 */
	public function validate( array $payload ) {
		$warnings = array();
		$graph    = $payload['@graph'] ?? array();
		if ( empty( $graph ) ) {
			$warnings[] = array( 'severity' => 'error', 'message' => __( 'Empty @graph.', 'rankwriter-ai' ) );
			return $warnings;
		}

		$required_by_type = array(
			self::TYPE_ARTICLE  => array( 'headline', 'datePublished', 'author', 'publisher', 'image' ),
			self::TYPE_BLOGPOST => array( 'headline', 'datePublished', 'author', 'publisher', 'image' ),
			self::TYPE_NEWS     => array( 'headline', 'datePublished', 'author', 'publisher', 'image' ),
			self::TYPE_HOWTO    => array( 'name', 'step' ),
			self::TYPE_RECIPE   => array( 'name', 'recipeIngredient', 'recipeInstructions', 'image' ),
			self::TYPE_JOB      => array( 'title', 'description', 'datePosted', 'hiringOrganization' ),
			self::TYPE_EVENT    => array( 'name', 'startDate', 'location' ),
			self::TYPE_REVIEW   => array( 'itemReviewed', 'reviewRating' ),
			self::TYPE_FAQ      => array( 'mainEntity' ),
			self::TYPE_PRODUCT  => array( 'name' ),
		);

		foreach ( $graph as $node ) {
			$t = $node['@type'] ?? '';
			if ( ! isset( $required_by_type[ $t ] ) ) {
				continue;
			}
			foreach ( $required_by_type[ $t ] as $field ) {
				if ( empty( $node[ $field ] ) ) {
					$warnings[] = array(
						'severity' => 'error',
						'type'     => $t,
						'field'    => $field,
						'message'  => sprintf( __( '%1$s is missing required field "%2$s".', 'rankwriter-ai' ), $t, $field ),
					);
				}
			}
			// Per-type extra checks
			if ( self::TYPE_REVIEW === $t && empty( $node['reviewRating']['ratingValue'] ?? null ) ) {
				$warnings[] = array(
					'severity' => 'error',
					'type'     => $t,
					'field'    => 'reviewRating.ratingValue',
					'message'  => __( 'Review is missing a numeric ratingValue.', 'rankwriter-ai' ),
				);
			}
			if ( self::TYPE_RECIPE === $t ) {
				if ( isset( $node['recipeIngredient'] ) && count( (array) $node['recipeIngredient'] ) < 2 ) {
					$warnings[] = array(
						'severity' => 'warning',
						'type'     => $t,
						'field'    => 'recipeIngredient',
						'message'  => __( 'Recipe has fewer than 2 ingredients — Google may not show as a rich result.', 'rankwriter-ai' ),
					);
				}
			}
			if ( self::TYPE_JOB === $t && empty( $node['jobLocation'] ) && empty( $node['jobLocationType'] ) ) {
				$warnings[] = array(
					'severity' => 'warning',
					'type'     => $t,
					'field'    => 'jobLocation',
					'message'  => __( 'JobPosting has no location and no jobLocationType. Google requires at least one.', 'rankwriter-ai' ),
				);
			}
		}
		return $warnings;
	}

	/* ============================ Print on frontend ============================ */

	public function print_for_post( $post_id ) {
		$payload = $this->get_saved_graph( $post_id );
		if ( empty( $payload['@graph'] ) ) {
			$payload = $this->build_graph( $post_id );
		}
		if ( empty( $payload['@graph'] ) ) {
			return;
		}
		echo "\n<!-- RankWriter AI Schema Engine -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/* ============================ Extractors ============================ */

	public function extract_faqs( $html ) {
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

	public function extract_howto_steps( $html ) {
		$steps = array();
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
		return array_slice( $steps, 0, 12 );
	}

	/**
	 * Grab the items of the first <ul>/<ol> that appears after an <h2>/<h3>
	 * whose text matches the given pattern. Used for Recipe ingredients +
	 * instructions.
	 */
	protected function extract_list_after_heading( $html, $heading_pattern ) {
		if ( ! preg_match_all( '#<h[23][^>]*>([^<]+)</h[23]>\s*(?:<(?:ul|ol)[^>]*>(.+?)</(?:ul|ol)>)#is', $html, $m, PREG_SET_ORDER ) ) {
			return array();
		}
		foreach ( $m as $match ) {
			if ( preg_match( $heading_pattern, $match[1] ) ) {
				$items = array();
				if ( preg_match_all( '#<li[^>]*>(.+?)</li>#is', $match[2], $list ) ) {
					foreach ( $list[1] as $li ) {
						$txt = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $li ) ) );
						if ( '' !== $txt ) { $items[] = mb_substr( $txt, 0, 500 ); }
					}
				}
				return $items;
			}
		}
		return array();
	}

	protected function iso_duration( $value, $unit ) {
		$unit = strtolower( $unit );
		$value = (int) $value;
		if ( strpos( $unit, 'h' ) === 0 ) {
			return 'PT' . $value . 'H';
		}
		return 'PT' . $value . 'M';
	}

	/* ============================ Dashboard helpers ============================ */

	/**
	 * Audit recent N posts. Returns counts by type and per-post warning
	 * tallies for the dashboard.
	 */
	public function audit_recent( $limit = 100 ) {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 500, (int) $limit ) ),
		) );
		$counts   = array();
		$rows     = array();
		$problem_count = 0;
		foreach ( $posts as $p ) {
			$payload = $this->get_saved_graph( $p->ID );
			if ( empty( $payload['@graph'] ) ) {
				$payload = $this->build_graph( $p->ID );
			}
			$types = array();
			foreach ( (array) ( $payload['@graph'] ?? array() ) as $node ) {
				$t = $node['@type'] ?? '';
				if ( $t ) {
					$types[] = $t;
					$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
				}
			}
			$warnings = $this->validate( $payload );
			$errors_only = array_filter( $warnings, function( $w ) { return ( $w['severity'] ?? '' ) === 'error'; } );
			if ( ! empty( $errors_only ) ) { $problem_count++; }
			$rows[] = array(
				'post_id'        => $p->ID,
				'title'          => $p->post_title,
				'types'          => $types,
				'warnings'       => $warnings,
				'errors'         => count( $errors_only ),
				'warning_count'  => count( $warnings ) - count( $errors_only ),
			);
		}
		return array(
			'counts'        => $counts,
			'rows'          => $rows,
			'problem_count' => $problem_count,
		);
	}
}
