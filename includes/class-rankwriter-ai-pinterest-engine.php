<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinterest Auto Content Mode — Claude-powered pin package generator.
 *
 *   generate_for_post( $post_id, $count = 3 )       → array of saved pin IDs
 *   generate_for_topic( $topic, $niche, $count )    → array of pin packages
 *                                                     (not saved to DB)
 *   detect_niche( $text )                           → one of 10 niches
 *
 * A "pin package" includes:
 *   - title          (CTR-optimized, Pinterest-style)
 *   - description    (200-500 chars, keyword-rich)
 *   - hashtags       (5-8 niche-relevant)
 *   - overlay_text   (the big bold text on the pin image)
 *   - overlay_secondary (smaller second line, often a number or CTA)
 *   - image_prompt   (detailed text-to-image prompt for DALL-E / Midjourney)
 *   - board_suggestions (3-5 Pinterest boards this pin fits)
 *
 * Every Claude call returns N pin variations so the user can A/B test.
 */
class RankWriter_AI_Pinterest_Engine {

	const NICHE_GENERAL    = 'general';
	const NICHE_FASHION    = 'fashion';
	const NICHE_PETS       = 'pets';
	const NICHE_RECIPES    = 'recipes';
	const NICHE_QUOTES     = 'quotes';
	const NICHE_SCHOLARS   = 'scholarships';
	const NICHE_TRAVEL     = 'travel';
	const NICHE_FITNESS    = 'fitness';
	const NICHE_HAIRSTYLES = 'hairstyles';
	const NICHE_DECOR      = 'home_decor';
	const NICHE_MOTIVATION = 'motivation';

	public static function supported_niches() {
		return array(
			self::NICHE_FASHION    => __( 'Fashion',     'rankwriter-ai' ),
			self::NICHE_PETS       => __( 'Pets',        'rankwriter-ai' ),
			self::NICHE_RECIPES    => __( 'Recipes',     'rankwriter-ai' ),
			self::NICHE_QUOTES     => __( 'Quotes',      'rankwriter-ai' ),
			self::NICHE_SCHOLARS   => __( 'Scholarships','rankwriter-ai' ),
			self::NICHE_TRAVEL     => __( 'Travel',      'rankwriter-ai' ),
			self::NICHE_FITNESS    => __( 'Fitness',     'rankwriter-ai' ),
			self::NICHE_HAIRSTYLES => __( 'Hairstyles',  'rankwriter-ai' ),
			self::NICHE_DECOR      => __( 'Home decor',  'rankwriter-ai' ),
			self::NICHE_MOTIVATION => __( 'Motivation',  'rankwriter-ai' ),
			self::NICHE_GENERAL    => __( 'General',     'rankwriter-ai' ),
		);
	}

	private static function niche_patterns() {
		return array(
			self::NICHE_RECIPES    => '/\b(recipe|cooking|meal|dinner|breakfast|baking|food|cuisine|chef|dish|appetizer|dessert)\b/i',
			self::NICHE_TRAVEL     => '/\b(travel|destination|vacation|trip|tourism|hotel|flight|itinerary|places? to visit|backpack|wanderlust)\b/i',
			self::NICHE_FASHION    => '/\b(fashion|outfit|style|clothing|wardrobe|trend|what to wear|capsule wardrobe|dress|aesthetic)\b/i',
			self::NICHE_PETS       => '/\b(dog|cat|pet|puppy|kitten|grooming|breed|leash|aquarium)\b/i',
			self::NICHE_FITNESS    => '/\b(workout|fitness|gym|exercise|cardio|strength|yoga|weight loss|core|abs|hiit)\b/i',
			self::NICHE_HAIRSTYLES => '/\b(hairstyle|haircut|braid|curls|highlights|balayage|bun|ponytail|hair color)\b/i',
			self::NICHE_DECOR      => '/\b(home decor|interior|living room|bedroom|kitchen|farmhouse|boho|scandinavian|modern|cozy)\b/i',
			self::NICHE_QUOTES     => '/\b(quote|inspirational|wisdom|saying|words to live by)\b/i',
			self::NICHE_SCHOLARS   => '/\b(scholarship|grant|fellowship|tuition|financial aid|fully funded)\b/i',
			self::NICHE_MOTIVATION => '/\b(motivation|inspiration|mindset|self[- ]improvement|productivity|success|goals|habits)\b/i',
		);
	}

	public function detect_niche( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return self::NICHE_GENERAL;
		}
		foreach ( self::niche_patterns() as $niche => $regex ) {
			if ( preg_match( $regex, $text ) ) {
				return $niche;
			}
		}
		return self::NICHE_GENERAL;
	}

	private static function niche_guidance( $niche ) {
		$g = array(
			self::NICHE_RECIPES => array(
				'tone'    => 'practical, sensory; lean on cook time and ingredient counts',
				'overlay' => 'count + ingredient/keyword: "5-Ingredient Pasta" / "30-Min Dinner"',
				'image'   => 'top-down food photography on neutral background, natural light, vibrant colors',
				'boards'  => array( 'Easy Dinner Recipes', 'Healthy Meal Prep', 'Weeknight Cooking', '30-Minute Meals' ),
			),
			self::NICHE_TRAVEL => array(
				'tone'    => 'evocative, place-named, specific',
				'overlay' => 'place name + benefit: "10 Hidden Gems in Bali" / "Cheapest Time to Visit"',
				'image'   => 'aspirational landscape photography, golden hour, wide shot, no people front-and-centre',
				'boards'  => array( 'Travel Inspiration', 'Bucket List Destinations', 'Budget Travel Tips', 'Solo Travel' ),
			),
			self::NICHE_FASHION => array(
				'tone'    => 'trend-aware, aesthetic, specific colour and season',
				'overlay' => 'season + style: "Fall Outfits 2026" / "Capsule Wardrobe Pieces"',
				'image'   => 'flatlay or styled mannequin shot, neutral background, magazine quality',
				'boards'  => array( 'Outfit Inspiration', 'Fall Fashion', 'Capsule Wardrobe', 'Aesthetic Outfits' ),
			),
			self::NICHE_PETS => array(
				'tone'    => 'warm, owner-perspective, breed-specific where possible',
				'overlay' => 'emotional hook + tip count: "7 Things Every Dog Owner Should Know"',
				'image'   => 'candid pet photography, eye-level, soft natural light',
				'boards'  => array( 'Dog Tips', 'Puppy Training', 'Cat Care', 'Pet Health' ),
			),
			self::NICHE_FITNESS => array(
				'tone'    => 'specific, results-oriented, time-bound',
				'overlay' => 'duration + outcome: "15-Min Ab Workout" / "30-Day Pull-Up Plan"',
				'image'   => 'workout shot or gym setup, bright lighting, no faces close-up',
				'boards'  => array( 'Workout Routines', 'Home Workouts', 'Fitness Tips', 'Weight Loss Journey' ),
			),
			self::NICHE_HAIRSTYLES => array(
				'tone'    => 'technique-specific, hair-type-aware',
				'overlay' => 'style + technique: "Easy Heatless Curls" / "Bob Cut Ideas 2026"',
				'image'   => 'back-of-head or 3/4 angle hair shot, salon lighting, sharp focus on hair',
				'boards'  => array( 'Hairstyle Inspiration', 'Easy Hair Tutorials', 'Wedding Hair', 'Short Hair Ideas' ),
			),
			self::NICHE_DECOR => array(
				'tone'    => 'aspirational, room-specific, style-named',
				'overlay' => 'room + style: "Small Bedroom Ideas" / "Boho Living Room Decor"',
				'image'   => 'wide-angle room shot, natural light, styled but lived-in',
				'boards'  => array( 'Home Decor Ideas', 'Small Space Living', 'Living Room Inspiration', 'Boho Style' ),
			),
			self::NICHE_QUOTES => array(
				'tone'    => 'concise, declarative, single emotional beat',
				'overlay' => 'the quote itself in big bold typography',
				'image'   => 'minimalist solid background or soft gradient, typography-dominant',
				'boards'  => array( 'Daily Inspiration', 'Words of Wisdom', 'Self Care Quotes', 'Motivational Quotes' ),
			),
			self::NICHE_SCHOLARS => array(
				'tone'    => 'specific amounts, deadlines, eligibility',
				'overlay' => 'amount + audience: "$50K Scholarships for Nigerian Students"',
				'image'   => 'university campus or graduation photo, optimistic lighting',
				'boards'  => array( 'Scholarships Worldwide', 'Study Abroad', 'Financial Aid', 'Fully Funded Programs' ),
			),
			self::NICHE_MOTIVATION => array(
				'tone'    => 'second-person, action-oriented, single big idea',
				'overlay' => 'declarative one-liner: "Stop Waiting. Start Now."',
				'image'   => 'minimalist or abstract, calm tones, no faces',
				'boards'  => array( 'Daily Motivation', 'Mindset Shifts', 'Productivity Tips', 'Self Improvement' ),
			),
			self::NICHE_GENERAL => array(
				'tone'    => 'clear, useful, specific',
				'overlay' => 'benefit + qualifier: "The Ultimate Guide to X"',
				'image'   => 'clean professional photography matching the topic',
				'boards'  => array( 'Tips and Tricks', 'How To Guides', 'Useful Resources' ),
			),
		);
		return isset( $g[ $niche ] ) ? $g[ $niche ] : $g[ self::NICHE_GENERAL ];
	}

	/**
	 * Generate N pin packages for a saved blog post and persist each one.
	 *
	 * @return int[]|WP_Error Array of inserted pin IDs.
	 */
	public function generate_for_post( $post_id, $count = 3, $niche_override = '' ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rwai_no_post', __( 'Post not found.', 'rankwriter-ai' ) );
		}
		$count = max( 1, min( 5, (int) $count ) );

		$plain      = wp_trim_words( wp_strip_all_tags( $post->post_content ), 120 );
		$niche      = $niche_override ?: $this->detect_niche( $post->post_title . ' ' . $plain );
		$packages   = $this->call_claude( $post->post_title, $plain, $niche, $count );
		if ( is_wp_error( $packages ) ) {
			return $packages;
		}
		$inserted = array();
		foreach ( $packages as $idx => $pkg ) {
			$pkg['post_id'] = $post_id;
			$pkg['niche']   = $niche;
			$pkg['variation_signature'] = substr( md5( $post_id . '|' . $idx . '|' . $pkg['title'] ), 0, 16 );
			$pin_id = $this->insert_pin( $pkg );
			if ( $pin_id ) {
				$inserted[] = $pin_id;
				do_action( 'rwai_pinterest_pin_ready', $pin_id, $pkg );
			}
		}
		return $inserted;
	}

	public function generate_for_topic( $topic, $niche = '', $count = 3 ) {
		$topic = trim( (string) $topic );
		if ( '' === $topic ) {
			return new WP_Error( 'rwai_no_topic', __( 'Topic is required.', 'rankwriter-ai' ) );
		}
		$count = max( 1, min( 5, (int) $count ) );
		$niche = $niche ?: $this->detect_niche( $topic );
		return $this->call_claude( $topic, '', $niche, $count );
	}

	/* ---------------- Claude call ---------------- */

	private function call_claude( $title, $body_excerpt, $niche, $count ) {
		if ( ! class_exists( 'RankWriter_AI_Claude_Client' ) ) {
			return new WP_Error( 'rwai_no_client', __( 'Claude client missing.', 'rankwriter-ai' ) );
		}
		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$guide = self::niche_guidance( $niche );

		$system = "You produce Pinterest pin packages that drive clicks and saves on the Pinterest feed. Every pin you produce must:\n"
			. "- Look fresh, not a copy of common Pinterest patterns.\n"
			. "- Pass Pinterest's spam guidelines: no clickbait promises, no medical/financial guarantees, no shocking claims.\n"
			. "- Optimize for the mobile Pinterest UI: short bold overlay text, scannable in 1 second.\n"
			. "- Match the niche's visual + tonal conventions.\n\n"
			. "## Niche: " . $niche . "\n"
			. "- Tone: " . $guide['tone'] . "\n"
			. "- Overlay style: " . $guide['overlay'] . "\n"
			. "- Image style: " . $guide['image'] . "\n";

		$user  = "Article title: \"$title\"\n";
		if ( $body_excerpt ) {
			$user .= "Article excerpt: " . $body_excerpt . "\n";
		}
		$user .= "\nReturn ONLY a JSON object with this exact shape (no preamble, no markdown fences):\n";
		$user .= "{\n";
		$user .= "  \"pins\": [\n";
		$user .= "    {\n";
		$user .= "      \"title\":             \"<60-100 char Pinterest title — different from article title — keyword-front-loaded>\",\n";
		$user .= "      \"description\":       \"<200-500 char description with naturally woven keywords + 1 hook line>\",\n";
		$user .= "      \"hashtags\":          [\"<no #>\", \"<no #>\", \"<no #>\", \"<no #>\", \"<no #>\"],\n";
		$user .= "      \"overlay_text\":      \"<3-7 word bold text for the pin image — the THING people will read in 1 second>\",\n";
		$user .= "      \"overlay_secondary\": \"<optional 2-5 word qualifier line, usually a number or CTA>\",\n";
		$user .= "      \"image_prompt\":      \"<detailed prompt for DALL-E / Midjourney describing the pin's hero image — composition, lighting, colors, no text>\",\n";
		$user .= "      \"board_suggestions\": [\"<board 1>\", \"<board 2>\", \"<board 3>\"]\n";
		$user .= "    }\n";
		$user .= "  ]\n";
		$user .= "}\n\n";
		$user .= "Produce exactly {$count} distinct pin variations. Each variation should feel like a different angle on the same article (different hook, different overlay phrase, different image direction) — NOT three tiny tweaks of the same pin.\n";
		$user .= "Hashtags: 5-8 niche-relevant hashtags, no leading #, lowercase, no spaces inside.\n";
		$user .= "Image prompts: describe the IMAGE only. NO text rendering instruction inside the prompt — the overlay text is added separately by the plugin.";

		$text = $client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_pin_response( $text, $guide );
	}

	private function parse_pin_response( $text, $guide ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );

		$json = json_decode( $text, true );
		if ( ! is_array( $json ) ) {
			if ( preg_match( '/\{.*\}/s', $text, $m ) ) {
				$json = json_decode( $m[0], true );
			}
		}
		if ( ! is_array( $json ) || empty( $json['pins'] ) || ! is_array( $json['pins'] ) ) {
			return new WP_Error( 'rwai_bad_response', __( 'Could not parse Pinterest pin variations from the response.', 'rankwriter-ai' ) );
		}
		$out = array();
		foreach ( $json['pins'] as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$pkg = array(
				'title'            => sanitize_text_field( (string) ( $p['title']            ?? '' ) ),
				'description'      => sanitize_textarea_field( (string) ( $p['description']      ?? '' ) ),
				'hashtags'         => array_map( 'sanitize_text_field', array_filter( (array) ( $p['hashtags'] ?? array() ), 'is_string' ) ),
				'overlay_text'     => sanitize_text_field( (string) ( $p['overlay_text']     ?? '' ) ),
				'overlay_secondary'=> sanitize_text_field( (string) ( $p['overlay_secondary'] ?? '' ) ),
				'image_prompt'     => sanitize_textarea_field( (string) ( $p['image_prompt']     ?? '' ) ),
				'board_suggestions'=> array_map( 'sanitize_text_field', array_filter( (array) ( $p['board_suggestions'] ?? array() ), 'is_string' ) ),
			);
			if ( empty( $pkg['board_suggestions'] ) && isset( $guide['boards'] ) ) {
				$pkg['board_suggestions'] = array_slice( (array) $guide['boards'], 0, 3 );
			}
			if ( '' !== $pkg['title'] ) {
				$out[] = $pkg;
			}
		}
		return $out;
	}

	/* ---------------- DB ops ---------------- */

	public function insert_pin( array $pkg ) {
		global $wpdb;
		if ( ! RankWriter_AI_Pinterest_DB::ready() ) {
			return 0;
		}
		$wpdb->insert( RankWriter_AI_Pinterest_DB::pins_table(), array(
			'post_id'             => ! empty( $pkg['post_id'] ) ? absint( $pkg['post_id'] ) : null,
			'niche'               => isset( $pkg['niche'] ) ? sanitize_text_field( $pkg['niche'] ) : 'general',
			'title'               => isset( $pkg['title'] ) ? mb_substr( (string) $pkg['title'], 0, 255 ) : '',
			'description'         => isset( $pkg['description'] ) ? (string) $pkg['description'] : '',
			'hashtags'            => wp_json_encode( isset( $pkg['hashtags'] ) ? (array) $pkg['hashtags'] : array() ),
			'overlay_text'        => isset( $pkg['overlay_text'] ) ? mb_substr( (string) $pkg['overlay_text'], 0, 255 ) : '',
			'overlay_secondary'   => isset( $pkg['overlay_secondary'] ) ? mb_substr( (string) $pkg['overlay_secondary'], 0, 255 ) : null,
			'image_prompt'        => isset( $pkg['image_prompt'] ) ? (string) $pkg['image_prompt'] : null,
			'board_suggestions'   => wp_json_encode( isset( $pkg['board_suggestions'] ) ? (array) $pkg['board_suggestions'] : array() ),
			'status'              => 'draft',
			'variation_signature' => isset( $pkg['variation_signature'] ) ? substr( (string) $pkg['variation_signature'], 0, 16 ) : null,
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public function update_pin( $pin_id, array $args ) {
		global $wpdb;
		$pin_id = absint( $pin_id );
		if ( ! $pin_id ) { return false; }
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( array( 'title', 'description', 'overlay_text', 'overlay_secondary', 'image_prompt', 'niche', 'status', 'pin_url', 'error_message' ) as $f ) {
			if ( array_key_exists( $f, $args ) ) {
				$update[ $f ] = $args[ $f ];
			}
		}
		if ( isset( $args['hashtags'] ) && is_array( $args['hashtags'] ) ) {
			$update['hashtags'] = wp_json_encode( $args['hashtags'] );
		}
		if ( isset( $args['board_suggestions'] ) && is_array( $args['board_suggestions'] ) ) {
			$update['board_suggestions'] = wp_json_encode( $args['board_suggestions'] );
		}
		if ( array_key_exists( 'image_attachment_id', $args ) ) {
			$update['image_attachment_id'] = $args['image_attachment_id'] ? absint( $args['image_attachment_id'] ) : null;
		}
		if ( array_key_exists( 'scheduled_at', $args ) ) {
			$update['scheduled_at'] = $args['scheduled_at'];
		}
		if ( array_key_exists( 'posted_at', $args ) ) {
			$update['posted_at'] = $args['posted_at'];
		}
		$wpdb->update( RankWriter_AI_Pinterest_DB::pins_table(), $update, array( 'id' => $pin_id ) );
		return true;
	}

	public function delete_pin( $pin_id ) {
		global $wpdb;
		$pin_id = absint( $pin_id );
		if ( ! $pin_id ) { return false; }
		// Delete the overlay attachment too (if it exists and is RWAI-generated).
		$pin = $this->get_pin( $pin_id );
		if ( $pin && ! empty( $pin['image_attachment_id'] ) ) {
			$attached_to = (int) get_post_meta( $pin['image_attachment_id'], '_rwai_pinterest_pin_id', true );
			if ( $attached_to === $pin_id ) {
				wp_delete_attachment( $pin['image_attachment_id'], true );
			}
		}
		$wpdb->delete( RankWriter_AI_Pinterest_DB::pins_table(), array( 'id' => $pin_id ) );
		return true;
	}

	public function get_pin( $pin_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . RankWriter_AI_Pinterest_DB::pins_table() . ' WHERE id = %d', absint( $pin_id )
		), ARRAY_A );
		return $row ? $this->hydrate_pin( $row ) : null;
	}

	public function get_pins( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'status'  => '',
			'post_id' => 0,
			'niche'   => '',
			'limit'   => 50,
			'offset'  => 0,
			'orderby' => 'updated_at',
			'order'   => 'DESC',
		) );
		$where  = '1=1';
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where    .= ' AND status = %s';
			$params[]  = $args['status'];
		}
		if ( ! empty( $args['post_id'] ) ) {
			$where    .= ' AND post_id = %d';
			$params[]  = absint( $args['post_id'] );
		}
		if ( ! empty( $args['niche'] ) ) {
			$where    .= ' AND niche = %s';
			$params[]  = $args['niche'];
		}
		$orderby = in_array( $args['orderby'], array( 'id', 'updated_at', 'created_at', 'scheduled_at', 'posted_at' ), true ) ? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		$sql  = 'SELECT * FROM ' . RankWriter_AI_Pinterest_DB::pins_table() . " WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = $this->hydrate_pin( $r );
		}
		return $out;
	}

	public function global_stats() {
		global $wpdb;
		$t = RankWriter_AI_Pinterest_DB::pins_table();
		return array(
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
			'draft'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'draft'" ),
			'scheduled' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'scheduled'" ),
			'ready'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'ready'" ),
			'posted'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'posted'" ),
		);
	}

	private function hydrate_pin( $r ) {
		$r['id']                  = (int) $r['id'];
		$r['post_id']             = $r['post_id'] ? (int) $r['post_id'] : null;
		$r['image_attachment_id'] = $r['image_attachment_id'] ? (int) $r['image_attachment_id'] : null;
		$r['hashtags']            = json_decode( (string) $r['hashtags'], true ) ?: array();
		$r['board_suggestions']   = json_decode( (string) $r['board_suggestions'], true ) ?: array();
		return $r;
	}
}
