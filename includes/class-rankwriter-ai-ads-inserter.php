<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend ad insertion engine.
 *
 * Hooks into the_content / the_excerpt / wp_head / a custom shortcode
 * and walks the configured ad blocks deciding which ones apply to the
 * current request based on:
 *   - Page type (post, page, homepage, category, tag, search, archive)
 *   - Category / tag includes & excludes
 *   - Post ID excludes
 *   - Device (desktop / tablet / mobile)
 *   - Schedule (date range, time-of-day window, day-of-week)
 *   - Per-post override meta (_rwai_ads_disabled, _rwai_ads_disabled_blocks)
 *
 * Each matching block is rendered with an alignment wrapper:
 *   <div class="rwai-ad rwai-ad-{id} rwai-ad-align-{align}">…ad code…</div>
 *
 * The CSS lives in admin/css/ads-frontend.css and is enqueued lazily —
 * only when at least one ad is being rendered on the current page.
 */
class RankWriter_AI_Ads_Inserter {

	const META_DISABLED        = '_rwai_ads_disabled';
	const META_DISABLED_BLOCKS = '_rwai_ads_disabled_blocks';

	private $css_enqueued = false;
	private $loop_post_counter = 0; // for between_posts mode

	public function register_hooks() {
		// Frontend rendering — never on admin.
		add_filter( 'the_content', array( $this, 'inject_into_content' ), 30 );
		add_filter( 'the_excerpt', array( $this, 'inject_into_excerpt' ), 30 );
		add_shortcode( 'rwai_ad', array( $this, 'shortcode' ) );

		// AdSense Auto Ads + custom head HTML.
		add_action( 'wp_head', array( $this, 'inject_head' ), 99 );

		// "Between posts" rendering: hook the_post inside a query loop
		// and emit the ad block as filtered content. We piggyback on
		// loop_start / loop_end so we have a counter that resets per loop.
		add_action( 'loop_start', array( $this, 'reset_loop_counter' ) );
		add_action( 'loop_end',   array( $this, 'reset_loop_counter' ) );

		// ads.txt serving.
		add_action( 'init', array( $this, 'maybe_serve_ads_txt' ), 1 );

		// Per-post meta box (admin edit screen).
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post',      array( $this, 'save_meta_box' ), 10, 1 );
	}

	/* ============================ Public API ============================ */

	/**
	 * Render a single block by ID. Used by the shortcode + the auto-
	 * insertion paths. Returns '' if the block is disabled, not
	 * applicable to this request, or doesn't exist.
	 */
	public function render_block( $id ) {
		$id    = (int) $id;
		$block = RankWriter_AI_Ads_DB::get_block( $id );
		if ( ! $block || empty( $block['enabled'] ) || '' === trim( (string) $block['code'] ) ) {
			return '';
		}
		if ( ! $this->should_show_block( $block ) ) {
			return '';
		}
		return $this->wrap_block( $block );
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'block' => 0, 'id' => 0 ), $atts, 'rwai_ad' );
		$id   = (int) ( $atts['block'] ?: $atts['id'] );
		if ( ! $id ) { return ''; }
		return $this->render_block( $id );
	}

	/* ============================ Content filter ============================ */

	public function inject_into_content( $content ) {
		if ( ! $this->ads_globally_enabled() ) { return $content; }
		if ( ! in_the_loop() || ! is_singular() ) {
			// Between-posts on archives is handled separately.
			if ( $this->is_archive_context() ) {
				return $this->maybe_insert_between_posts( $content );
			}
			return $content;
		}

		$post_id = get_the_ID();
		if ( $this->post_has_ads_disabled( $post_id ) ) {
			return $content;
		}

		$disabled_blocks = $this->post_disabled_blocks( $post_id );
		$blocks          = RankWriter_AI_Ads_DB::get_blocks();

		// Split content into paragraph segments so we can insert after
		// specific paragraph indices. Uses <p> as the boundary — the
		// canonical paragraph tag that wpautop emits.
		$paragraph_chunks = $this->split_into_paragraphs( $content );

		$before    = '';
		$after     = '';

		foreach ( $blocks as $block ) {
			if ( in_array( (int) $block['id'], $disabled_blocks, true ) ) { continue; }
			if ( empty( $block['enabled'] ) || '' === trim( (string) $block['code'] ) ) { continue; }
			if ( ! $this->should_show_block( $block ) ) { continue; }

			switch ( $block['insertion'] ) {
				case 'before_content':
					$before .= $this->wrap_block( $block );
					break;

				case 'after_content':
					$after .= $this->wrap_block( $block );
					break;

				case 'after_paragraph':
					$targets = array_filter( array_map( 'intval', explode( ',', (string) $block['insertion_paragraphs'] ) ) );
					foreach ( $targets as $n ) {
						if ( $n >= 1 && $n <= count( $paragraph_chunks ) ) {
							$paragraph_chunks[ $n - 1 ] .= $this->wrap_block( $block );
						}
					}
					break;

				// before_excerpt / after_excerpt are handled in the
				// excerpt filter, not here.
				default:
					// none / between_posts / unknown — skip from content.
					break;
			}
		}

		$assembled = $before . implode( '', $paragraph_chunks ) . $after;
		return $assembled;
	}

	public function inject_into_excerpt( $excerpt ) {
		if ( ! $this->ads_globally_enabled() ) { return $excerpt; }
		$blocks = RankWriter_AI_Ads_DB::get_blocks();
		$post_id = get_the_ID();
		$disabled_blocks = $post_id ? $this->post_disabled_blocks( $post_id ) : array();

		$before = '';
		$after  = '';
		foreach ( $blocks as $block ) {
			if ( in_array( (int) $block['id'], $disabled_blocks, true ) ) { continue; }
			if ( empty( $block['enabled'] ) || '' === trim( (string) $block['code'] ) ) { continue; }
			if ( ! $this->should_show_block( $block ) ) { continue; }

			if ( 'before_excerpt' === $block['insertion'] ) {
				$before .= $this->wrap_block( $block );
			} elseif ( 'after_excerpt' === $block['insertion'] ) {
				$after .= $this->wrap_block( $block );
			}
		}
		return $before . $excerpt . $after;
	}

	/* ============================ Between-posts (archive) ============================ */

	public function reset_loop_counter() {
		$this->loop_post_counter = 0;
	}

	/**
	 * On archive pages, prepend a between_posts ad after every Nth post.
	 * Hooked via the_content for posts inside the loop.
	 */
	private function maybe_insert_between_posts( $content ) {
		if ( ! in_the_loop() ) { return $content; }
		$this->loop_post_counter++;

		$blocks = RankWriter_AI_Ads_DB::get_blocks();
		$prepend = '';
		foreach ( $blocks as $block ) {
			if ( 'between_posts' !== $block['insertion'] ) { continue; }
			if ( empty( $block['enabled'] ) || '' === trim( (string) $block['code'] ) ) { continue; }
			if ( ! $this->should_show_block( $block ) ) { continue; }

			$every = max( 1, (int) $block['between_posts_every'] );
			if ( $this->loop_post_counter > 0 && 0 === ( $this->loop_post_counter % $every ) ) {
				$prepend .= $this->wrap_block( $block );
			}
		}
		return $prepend . $content;
	}

	/* ============================ Head injection ============================ */

	public function inject_head() {
		if ( is_admin() ) { return; }
		if ( ! $this->ads_globally_enabled() ) { return; }

		$settings = RankWriter_AI_Ads_DB::get_settings();

		if ( ! empty( $settings['auto_ads_enabled'] ) && ! empty( $settings['auto_ads_pub_id'] ) ) {
			$pub = esc_attr( $settings['auto_ads_pub_id'] );
			echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $pub . '" crossorigin="anonymous"></script>' . "\n";
		}

		if ( ! empty( $settings['inject_in_head'] ) ) {
			// Raw passthrough — same trust model as ad block code.
			echo (string) $settings['inject_in_head'] . "\n";
		}
	}

	/* ============================ ads.txt ============================ */

	public function maybe_serve_ads_txt() {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path    = strtok( $request, '?' );
		if ( '/ads.txt' !== $path ) { return; }

		$settings = RankWriter_AI_Ads_DB::get_settings();
		$content  = (string) $settings['ads_txt_content'];
		if ( '' === trim( $content ) ) { return; } // let WP serve a 404 normally

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $content;
		exit;
	}

	/* ============================ Per-post meta box ============================ */

	public function register_meta_box() {
		add_meta_box(
			'rwai-ads-override',
			__( 'RankWriter Ads', 'rankwriter-ai' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'side',
			'default'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'rwai_ads_meta', 'rwai_ads_meta_nonce' );
		$disabled        = (int) get_post_meta( $post->ID, self::META_DISABLED, true );
		$disabled_blocks = (array) get_post_meta( $post->ID, self::META_DISABLED_BLOCKS, true );
		?>
		<p>
			<label>
				<input type="checkbox" name="rwai_ads_disabled" value="1" <?php checked( $disabled, 1 ); ?> />
				<?php esc_html_e( 'Disable ALL ads on this post', 'rankwriter-ai' ); ?>
			</label>
		</p>
		<p>
			<label for="rwai_ads_disabled_blocks"><?php esc_html_e( 'OR disable specific blocks (comma-separated IDs):', 'rankwriter-ai' ); ?></label>
			<input type="text" id="rwai_ads_disabled_blocks" name="rwai_ads_disabled_blocks" value="<?php echo esc_attr( implode( ',', array_map( 'intval', $disabled_blocks ) ) ); ?>" class="widefat" placeholder="e.g. 3,7,12" />
		</p>
		<?php
	}

	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['rwai_ads_meta_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( wp_unslash( $_POST['rwai_ads_meta_nonce'] ), 'rwai_ads_meta' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		update_post_meta( $post_id, self::META_DISABLED, ! empty( $_POST['rwai_ads_disabled'] ) ? 1 : 0 );

		$ids_raw = isset( $_POST['rwai_ads_disabled_blocks'] ) ? (string) wp_unslash( $_POST['rwai_ads_disabled_blocks'] ) : '';
		$ids     = array();
		foreach ( preg_split( '/[\s,]+/', $ids_raw ) as $p ) {
			$p = trim( $p );
			if ( ctype_digit( $p ) ) {
				$n = (int) $p;
				if ( $n >= 1 && $n <= RankWriter_AI_Ads_DB::NUM_BLOCKS ) {
					$ids[] = $n;
				}
			}
		}
		update_post_meta( $post_id, self::META_DISABLED_BLOCKS, array_values( array_unique( $ids ) ) );
	}

	/* ============================ Matching logic ============================ */

	private function ads_globally_enabled() {
		if ( is_feed() || is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		$s = RankWriter_AI_Ads_DB::get_settings();
		return ! empty( $s['master_enabled'] );
	}

	private function post_has_ads_disabled( $post_id ) {
		if ( ! $post_id ) { return false; }
		return (int) get_post_meta( $post_id, self::META_DISABLED, true ) === 1;
	}

	private function post_disabled_blocks( $post_id ) {
		if ( ! $post_id ) { return array(); }
		$raw = get_post_meta( $post_id, self::META_DISABLED_BLOCKS, true );
		return is_array( $raw ) ? array_map( 'intval', $raw ) : array();
	}

	/**
	 * Apply the full match cascade for one block: page type, device,
	 * schedule, category/tag includes/excludes, post-id excludes.
	 */
	private function should_show_block( array $block ) {
		// Device targeting first — cheapest checks.
		$device = $this->detect_device();
		if ( 'mobile'  === $device && empty( $block['show_mobile'] ) )  { return false; }
		if ( 'tablet'  === $device && empty( $block['show_tablet'] ) )  { return false; }
		if ( 'desktop' === $device && empty( $block['show_desktop'] ) ) { return false; }

		// Schedule.
		if ( ! $this->matches_schedule( $block ) ) { return false; }

		// Page type.
		if ( ! $this->matches_page_type( $block ) ) { return false; }

		// Category / tag / post-ID gating (only meaningful on singular posts).
		if ( is_singular( 'post' ) ) {
			$post_id = (int) get_the_ID();
			$excludes = array_filter( array_map( 'intval', explode( ',', (string) $block['exclude_post_ids'] ) ) );
			if ( in_array( $post_id, $excludes, true ) ) { return false; }

			if ( ! $this->matches_taxonomy( $post_id, 'category', (string) $block['include_categories'], (string) $block['exclude_categories'] ) ) {
				return false;
			}
			if ( ! $this->matches_taxonomy( $post_id, 'post_tag', (string) $block['include_tags'], (string) $block['exclude_tags'] ) ) {
				return false;
			}
		}

		return true;
	}

	private function matches_page_type( array $block ) {
		// is_home() = blog page; is_front_page() = static homepage or
		// blog homepage. We treat both as "homepage" for show_on_homepage.
		if ( is_front_page() || is_home() ) {
			return ! empty( $block['show_on_homepage'] );
		}
		if ( is_singular( 'post' ) ) { return ! empty( $block['show_on_posts'] ); }
		if ( is_singular( 'page' ) ) { return ! empty( $block['show_on_pages'] ); }
		if ( is_search() )           { return ! empty( $block['show_on_search'] ); }
		if ( is_category() )         { return ! empty( $block['show_on_category'] ); }
		if ( is_tag() )              { return ! empty( $block['show_on_tag'] ); }
		if ( is_archive() )          { return ! empty( $block['show_on_archive'] ); }
		return false;
	}

	private function matches_taxonomy( $post_id, $tax, $include_csv, $exclude_csv ) {
		$include = array_filter( array_map( 'intval', explode( ',', (string) $include_csv ) ) );
		$exclude = array_filter( array_map( 'intval', explode( ',', (string) $exclude_csv ) ) );
		if ( empty( $include ) && empty( $exclude ) ) { return true; }

		$terms = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) { return true; }
		$terms = array_map( 'intval', (array) $terms );

		if ( ! empty( $exclude ) && count( array_intersect( $terms, $exclude ) ) > 0 ) {
			return false;
		}
		if ( ! empty( $include ) && count( array_intersect( $terms, $include ) ) === 0 ) {
			return false;
		}
		return true;
	}

	private function matches_schedule( array $block ) {
		$now_ts = current_time( 'timestamp' );

		// Start / end date range.
		if ( ! empty( $block['schedule_start'] ) ) {
			$s = strtotime( $block['schedule_start'] );
			if ( $s && $now_ts < $s ) { return false; }
		}
		if ( ! empty( $block['schedule_end'] ) ) {
			$e = strtotime( $block['schedule_end'] );
			if ( $e && $now_ts > $e ) { return false; }
		}

		// Day-of-week.
		$days = array_filter( array_map( 'intval', explode( ',', (string) $block['schedule_days'] ) ), function ( $d ) { return $d >= 0 && $d <= 6; } );
		if ( ! empty( $days ) ) {
			$today_dow = (int) wp_date( 'w', $now_ts );
			if ( ! in_array( $today_dow, $days, true ) ) { return false; }
		}

		// Time-of-day window.
		if ( ! empty( $block['schedule_hour_from'] ) && ! empty( $block['schedule_hour_to'] ) ) {
			$now_hhmm = wp_date( 'H:i', $now_ts );
			$from = $block['schedule_hour_from'];
			$to   = $block['schedule_hour_to'];
			if ( $from <= $to ) {
				if ( $now_hhmm < $from || $now_hhmm > $to ) { return false; }
			} else {
				// Window wraps midnight (e.g. 22:00 → 06:00).
				if ( $now_hhmm < $from && $now_hhmm > $to ) { return false; }
			}
		}

		return true;
	}

	/**
	 * Heuristic device detection. wp_is_mobile() is the cheapest reliable
	 * signal; we layer a quick tablet-UA pattern on top so iPads /
	 * Android tablets are distinguishable from phones.
	 */
	private function detect_device() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( '' === $ua ) { return 'desktop'; }

		$is_tablet = (bool) preg_match( '/(iPad|Android(?!.*Mobile)|Tablet|PlayBook|Kindle|Silk)/i', $ua );
		if ( $is_tablet ) { return 'tablet'; }

		if ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) { return 'mobile'; }
		if ( preg_match( '/(Mobile|iPhone|Android.*Mobile|BlackBerry|IEMobile|Opera Mini)/i', $ua ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	private function is_archive_context() {
		return ( is_home() || is_front_page() || is_archive() || is_search() );
	}

	/* ============================ Rendering ============================ */

	private function wrap_block( array $block ) {
		$this->maybe_enqueue_css();
		$align = in_array( $block['alignment'], array( 'left', 'right', 'center' ), true ) ? $block['alignment'] : 'default';
		return sprintf(
			'<div class="rwai-ad rwai-ad-%d rwai-ad-align-%s">%s</div>',
			(int) $block['id'],
			esc_attr( $align ),
			(string) $block['code']
		);
	}

	private function maybe_enqueue_css() {
		if ( $this->css_enqueued ) { return; }
		$this->css_enqueued = true;
		// Inline tiny CSS so we don't pay a separate HTTP request just
		// for ad alignment.
		add_action( 'wp_footer', function () {
			echo '<style>.rwai-ad{margin:18px 0;clear:both;}.rwai-ad-align-left{text-align:left;float:left;margin-right:18px;}.rwai-ad-align-right{text-align:right;float:right;margin-left:18px;}.rwai-ad-align-center{text-align:center;}</style>';
		}, 99 );
	}

	/**
	 * Split content into paragraph chunks for after_paragraph insertion.
	 * Uses </p> as the cut boundary (every paragraph ends with </p>).
	 * Anything before the first </p> becomes chunk 1.
	 */
	private function split_into_paragraphs( $content ) {
		// Use a regex split that keeps the </p> on the left side so
		// re-joining produces valid HTML. We split AFTER each </p>.
		$parts = preg_split( '#(</p>)#i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( empty( $parts ) ) { return array( $content ); }

		// Re-pair the captured </p> back onto its paragraph: parts is
		// [p1, '</p>', p2, '</p>', ..., tail]. We want chunks of
		// "p1</p>", "p2</p>", ..., "tail".
		$chunks = array();
		$buf    = '';
		foreach ( $parts as $piece ) {
			$buf .= $piece;
			if ( 0 === strcasecmp( $piece, '</p>' ) ) {
				$chunks[] = $buf;
				$buf = '';
			}
		}
		if ( '' !== $buf ) { $chunks[] = $buf; }
		return empty( $chunks ) ? array( $content ) : $chunks;
	}
}
