<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seasonal Trend Engine.
 *
 * Detects recurring annual events / seasons (Black Friday, tax season,
 * scholarship season, back-to-school, holiday travel, etc.) and gives the
 * user a ranked list of "what to publish next" with optimal lead times.
 *
 * Architecture: a curated catalog of ~50 events with niche tags + a heat
 * function that peaks at the event's ideal-lead-time window. The engine
 * also cross-references the user's existing posts against the catalog so
 * it can tell them "Black Friday is in 28 days and you have zero posts
 * targeting it."
 *
 * Pure heuristic — no API calls. The catalog is hardcoded but plugin-
 * filterable via `rwai_seasonal_events`. Lightweight enough to run on
 * every admin page-load.
 */
class RankWriter_AI_Seasonal_Engine {

	const OPTION_COVERAGE_CACHE = 'rwai_seasonal_coverage_cache';
	const OPTION_DISMISSED      = 'rwai_seasonal_dismissed';
	const CRON_HOOK             = 'rwai_seasonal_tick';

	const NICHE_FINANCE   = 'finance';
	const NICHE_EDUCATION = 'education';
	const NICHE_RETAIL    = 'retail';
	const NICHE_TRAVEL    = 'travel';
	const NICHE_FOOD      = 'food';
	const NICHE_HEALTH    = 'health';
	const NICHE_PARENTING = 'parenting';
	const NICHE_TECH      = 'tech';
	const NICHE_GENERAL   = 'general';

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'tick' ) );
	}

	public function schedule_recurring() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Daily tick — refresh the coverage cache so the dashboard widget is
	 * snappy even when the site has thousands of posts.
	 */
	public function tick() {
		$this->refresh_coverage_cache();
	}

	/* ============================ Events catalog ============================ */

	/**
	 * Built-in catalog. Each event has:
	 *  - id           unique slug
	 *  - name         display name
	 *  - niches       array of niche tags it applies to
	 *  - traffic      low | medium | high | extreme — peak traffic potential
	 *  - lead_days    optimal publish lead time (post should already be live
	 *                 this many days before the event)
	 *  - window_days  how long the event "window" lasts after the peak
	 *  - date_fn      callable that returns timestamp of next occurrence
	 *                 given the current year
	 *  - keywords     array of keyword stems used for coverage detection
	 *                 (matched against post titles)
	 *  - seeds        array of seed topic ideas the user can spin into posts
	 */
	public function events() {
		$events = array(

			/* ---------- Calendar holidays ---------- */
			array(
				'id'         => 'new_year',
				'name'       => __( 'New Year', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_GENERAL, self::NICHE_HEALTH ),
				'traffic'    => 'high',
				'lead_days'  => 14,
				'window_days'=> 7,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 1, 1, $y ); },
				'keywords'   => array( 'new year', 'resolution', 'fresh start' ),
				'seeds'      => array( 'New Year resolutions {YEAR}', 'How to set goals for {YEAR}', 'New Year fitness plan' ),
			),
			array(
				'id'         => 'valentines',
				'name'       => __( "Valentine's Day", 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_FOOD, self::NICHE_GENERAL ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 2, 14, $y ); },
				'keywords'   => array( "valentine", "valentine's", 'date night', 'romantic gift' ),
				'seeds'      => array( "Best Valentine's Day gifts {YEAR}", 'Romantic date ideas on a budget', "Last-minute Valentine's gift guide" ),
			),
			array(
				'id'         => 'easter',
				'name'       => __( 'Easter', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FOOD, self::NICHE_PARENTING, self::NICHE_GENERAL ),
				'traffic'    => 'medium',
				'lead_days'  => 21,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return self::easter_timestamp( $y ); },
				'keywords'   => array( 'easter', 'easter egg', 'easter basket', 'easter brunch' ),
				'seeds'      => array( 'Easter brunch recipes {YEAR}', 'Easter egg hunt ideas', 'Easter gift baskets for kids' ),
			),
			array(
				'id'         => 'mothers_day',
				'name'       => __( "Mother's Day (US)", 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_PARENTING, self::NICHE_GENERAL ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return self::nth_weekday( $y, 5, 2, 0 ); }, // 2nd Sun May
				'keywords'   => array( "mother's day", 'mom gift', 'gift for mom' ),
				'seeds'      => array( "Mother's Day gift ideas {YEAR}", "Last-minute Mother's Day gifts", "Mother's Day brunch ideas" ),
			),
			array(
				'id'         => 'fathers_day',
				'name'       => __( "Father's Day (US)", 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_PARENTING, self::NICHE_GENERAL ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return self::nth_weekday( $y, 6, 3, 0 ); }, // 3rd Sun Jun
				'keywords'   => array( "father's day", 'dad gift', 'gift for dad' ),
				'seeds'      => array( "Father's Day gift ideas {YEAR}", "Gifts for dads who have everything", "Father's Day BBQ recipes" ),
			),
			array(
				'id'         => 'halloween',
				'name'       => __( 'Halloween', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_FOOD, self::NICHE_PARENTING ),
				'traffic'    => 'extreme',
				'lead_days'  => 30,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 10, 31, $y ); },
				'keywords'   => array( 'halloween', 'costume', 'trick or treat', 'pumpkin' ),
				'seeds'      => array( 'Halloween costume ideas {YEAR}', 'Easy DIY Halloween decorations', 'Halloween party food ideas' ),
			),
			array(
				'id'         => 'thanksgiving',
				'name'       => __( 'Thanksgiving (US)', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FOOD, self::NICHE_TRAVEL, self::NICHE_GENERAL ),
				'traffic'    => 'extreme',
				'lead_days'  => 21,
				'window_days'=> 3,
				'date_fn'    => function( $y ) { return self::nth_weekday( $y, 11, 4, 4 ); }, // 4th Thu Nov
				'keywords'   => array( 'thanksgiving', 'turkey recipe', 'thanksgiving travel' ),
				'seeds'      => array( 'Thanksgiving turkey recipe', 'Thanksgiving side dishes', 'Hosting Thanksgiving for the first time' ),
			),
			array(
				'id'         => 'christmas',
				'name'       => __( 'Christmas', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_FOOD, self::NICHE_GENERAL ),
				'traffic'    => 'extreme',
				'lead_days'  => 45,
				'window_days'=> 5,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 12, 25, $y ); },
				'keywords'   => array( 'christmas', 'holiday gift', 'gift guide', 'stocking stuffer' ),
				'seeds'      => array( 'Christmas gift guide {YEAR}', 'Stocking stuffer ideas under $25', 'Holiday gift ideas for him/her' ),
			),

			/* ---------- Shopping events ---------- */
			array(
				'id'         => 'black_friday',
				'name'       => __( 'Black Friday', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_TECH, self::NICHE_FINANCE ),
				'traffic'    => 'extreme',
				'lead_days'  => 30,
				'window_days'=> 4,
				'date_fn'    => function( $y ) {
					// Day after Thanksgiving (4th Thu Nov)
					return self::nth_weekday( $y, 11, 4, 4 ) + DAY_IN_SECONDS;
				},
				'keywords'   => array( 'black friday', 'deal', 'discount', 'sale' ),
				'seeds'      => array( 'Best Black Friday deals {YEAR}', 'Black Friday tech deals to look for', 'Black Friday vs Cyber Monday strategy' ),
			),
			array(
				'id'         => 'cyber_monday',
				'name'       => __( 'Cyber Monday', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_TECH ),
				'traffic'    => 'extreme',
				'lead_days'  => 28,
				'window_days'=> 1,
				'date_fn'    => function( $y ) {
					return self::nth_weekday( $y, 11, 4, 4 ) + ( 4 * DAY_IN_SECONDS );
				},
				'keywords'   => array( 'cyber monday', 'online deal', 'tech sale' ),
				'seeds'      => array( 'Cyber Monday deals {YEAR}', 'Best online tech deals this Cyber Monday', 'Cyber Monday vs Black Friday: which is better?' ),
			),
			array(
				'id'         => 'prime_day',
				'name'       => __( 'Amazon Prime Day', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_TECH ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 2,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 7, 11, $y ); },
				'keywords'   => array( 'prime day', 'amazon deal' ),
				'seeds'      => array( 'Prime Day deals to watch {YEAR}', 'Best Amazon Prime Day strategy', 'Prime Day vs Black Friday' ),
			),
			array(
				'id'         => 'memorial_day_sales',
				'name'       => __( 'Memorial Day Sales', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL ),
				'traffic'    => 'medium',
				'lead_days'  => 14,
				'window_days'=> 3,
				'date_fn'    => function( $y ) {
					// Last Mon of May
					$t = mktime( 0, 0, 0, 5, 31, $y );
					while ( (int) date( 'N', $t ) !== 1 ) { $t -= DAY_IN_SECONDS; }
					return $t;
				},
				'keywords'   => array( 'memorial day sale', 'memorial day deal' ),
				'seeds'      => array( 'Memorial Day mattress sales', 'Memorial Day appliance deals', 'Best Memorial Day weekend sales' ),
			),
			array(
				'id'         => 'labor_day_sales',
				'name'       => __( 'Labor Day Sales (US)', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL ),
				'traffic'    => 'medium',
				'lead_days'  => 14,
				'window_days'=> 3,
				'date_fn'    => function( $y ) { return self::nth_weekday( $y, 9, 1, 1 ); }, // 1st Mon Sep
				'keywords'   => array( 'labor day sale', 'labor day deal' ),
				'seeds'      => array( 'Labor Day weekend sales', 'Best Labor Day mattress deals' ),
			),
			array(
				'id'         => 'boxing_day',
				'name'       => __( 'Boxing Day (UK/CA)', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL ),
				'traffic'    => 'medium',
				'lead_days'  => 10,
				'window_days'=> 2,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 12, 26, $y ); },
				'keywords'   => array( 'boxing day' ),
				'seeds'      => array( 'Boxing Day sales {YEAR}', 'Boxing Day shopping strategy' ),
			),

			/* ---------- Academic / education ---------- */
			array(
				'id'         => 'back_to_school',
				'name'       => __( 'Back-to-School Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_EDUCATION, self::NICHE_RETAIL, self::NICHE_PARENTING ),
				'traffic'    => 'extreme',
				'lead_days'  => 30,
				'window_days'=> 45,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 8, 15, $y ); },
				'keywords'   => array( 'back to school', 'school supplies', 'student' ),
				'seeds'      => array( 'Back-to-school shopping list {YEAR}', 'Best backpacks for students', 'College dorm essentials' ),
			),
			array(
				'id'         => 'scholarship_season',
				'name'       => __( 'Scholarship Application Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_EDUCATION, self::NICHE_FINANCE ),
				'traffic'    => 'extreme',
				'lead_days'  => 30,
				'window_days'=> 120,
				'date_fn'    => function( $y ) {
					// Long season Oct → end of Feb. Use Nov 1 as peak.
					$start = mktime( 0, 0, 0, 10, 1, $y );
					if ( time() > $start + ( 180 * DAY_IN_SECONDS ) ) {
						return mktime( 0, 0, 0, 10, 1, $y + 1 );
					}
					return $start;
				},
				'keywords'   => array( 'scholarship', 'financial aid', 'grant', 'fafsa', 'bursary' ),
				'seeds'      => array( 'How to apply for scholarships {YEAR}', 'Scholarship essay tips', 'Best scholarships for international students' ),
			),
			array(
				'id'         => 'college_admissions',
				'name'       => __( 'College Admissions Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_EDUCATION ),
				'traffic'    => 'high',
				'lead_days'  => 30,
				'window_days'=> 120,
				'date_fn'    => function( $y ) {
					$start = mktime( 0, 0, 0, 11, 1, $y );
					if ( time() > $start + ( 180 * DAY_IN_SECONDS ) ) {
						return mktime( 0, 0, 0, 11, 1, $y + 1 );
					}
					return $start;
				},
				'keywords'   => array( 'admission', 'college essay', 'common app', 'sat', 'act' ),
				'seeds'      => array( 'College application essay tips', 'How to choose a college', 'SAT prep strategy {YEAR}' ),
			),
			array(
				'id'         => 'graduation',
				'name'       => __( 'Graduation Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_EDUCATION, self::NICHE_RETAIL ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 30,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 5, 15, $y ); },
				'keywords'   => array( 'graduation', 'graduate gift', 'commencement' ),
				'seeds'      => array( 'Graduation gift ideas {YEAR}', 'Graduation party planning', 'Career advice for new grads' ),
			),

			/* ---------- Financial / tax ---------- */
			array(
				'id'         => 'tax_season_us',
				'name'       => __( 'Tax Season (US)', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FINANCE ),
				'traffic'    => 'extreme',
				'lead_days'  => 30,
				'window_days'=> 90,
				'date_fn'    => function( $y ) {
					$start = mktime( 0, 0, 0, 1, 15, $y );
					if ( time() > $start + ( 180 * DAY_IN_SECONDS ) ) {
						return mktime( 0, 0, 0, 1, 15, $y + 1 );
					}
					return $start;
				},
				'keywords'   => array( 'tax', 'tax return', 'irs', 'tax deduction', 'tax filing' ),
				'seeds'      => array( 'How to file your taxes {YEAR}', 'Top tax deductions for {YEAR}', 'Tax software comparison' ),
			),
			array(
				'id'         => 'tax_season_uk',
				'name'       => __( 'UK Self-Assessment Deadline', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FINANCE ),
				'traffic'    => 'high',
				'lead_days'  => 21,
				'window_days'=> 14,
				'date_fn'    => function( $y ) {
					$d = mktime( 0, 0, 0, 1, 31, $y );
					if ( time() > $d + ( 30 * DAY_IN_SECONDS ) ) {
						$d = mktime( 0, 0, 0, 1, 31, $y + 1 );
					}
					return $d;
				},
				'keywords'   => array( 'self assessment', 'hmrc', 'uk tax' ),
				'seeds'      => array( 'How to file self-assessment {YEAR}', 'Self-employment tax UK guide', 'HMRC penalty deadlines' ),
			),
			array(
				'id'         => 'fiscal_year_end_us',
				'name'       => __( 'Year-End Financial Planning', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FINANCE ),
				'traffic'    => 'high',
				'lead_days'  => 30,
				'window_days'=> 30,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 12, 31, $y ); },
				'keywords'   => array( 'year-end', 'tax loss harvesting', 'ira contribution', '401k' ),
				'seeds'      => array( 'Year-end tax moves {YEAR}', 'Max your 401(k) before year-end', 'IRA contribution deadline tips' ),
			),

			/* ---------- Travel ---------- */
			array(
				'id'         => 'spring_break',
				'name'       => __( 'Spring Break Travel', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_TRAVEL ),
				'traffic'    => 'high',
				'lead_days'  => 45,
				'window_days'=> 30,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 3, 15, $y ); },
				'keywords'   => array( 'spring break', 'beach vacation', 'cancun', 'florida' ),
				'seeds'      => array( 'Best spring break destinations {YEAR}', 'Cheap spring break flights', 'Spring break packing list' ),
			),
			array(
				'id'         => 'summer_travel',
				'name'       => __( 'Summer Travel Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_TRAVEL ),
				'traffic'    => 'extreme',
				'lead_days'  => 60,
				'window_days'=> 90,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 6, 1, $y ); },
				'keywords'   => array( 'summer vacation', 'summer trip', 'road trip', 'family vacation' ),
				'seeds'      => array( 'Best family summer destinations {YEAR}', 'Cheap summer flight deals', 'Road trip planning guide' ),
			),
			array(
				'id'         => 'holiday_travel',
				'name'       => __( 'Holiday Travel Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_TRAVEL ),
				'traffic'    => 'high',
				'lead_days'  => 60,
				'window_days'=> 30,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 12, 15, $y ); },
				'keywords'   => array( 'holiday flight', 'christmas travel', 'thanksgiving flight' ),
				'seeds'      => array( 'Cheapest days to fly for the holidays', 'Holiday travel survival guide', 'How to beat holiday airport crowds' ),
			),

			/* ---------- Sports ---------- */
			array(
				'id'         => 'super_bowl',
				'name'       => __( 'Super Bowl', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_FOOD, self::NICHE_RETAIL, self::NICHE_GENERAL ),
				'traffic'    => 'extreme',
				'lead_days'  => 14,
				'window_days'=> 1,
				'date_fn'    => function( $y ) { return self::nth_weekday( $y, 2, 2, 0 ); }, // 2nd Sun Feb (approx)
				'keywords'   => array( 'super bowl', 'super bowl recipe', 'super bowl ad' ),
				'seeds'      => array( 'Super Bowl party food ideas {YEAR}', 'Best Super Bowl commercials of all time', 'Easy Super Bowl appetizers' ),
			),

			/* ---------- Health / lifestyle ---------- */
			array(
				'id'         => 'january_fitness',
				'name'       => __( 'New Year Fitness Surge', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_HEALTH ),
				'traffic'    => 'extreme',
				'lead_days'  => 14,
				'window_days'=> 60,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 1, 5, $y ); },
				'keywords'   => array( 'weight loss', 'diet', 'workout', 'gym', 'fitness goal' ),
				'seeds'      => array( 'How to lose weight in {YEAR}', 'Beginner home workout plan', 'Best diet to start in January' ),
			),
			array(
				'id'         => 'allergy_season',
				'name'       => __( 'Allergy Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_HEALTH ),
				'traffic'    => 'medium',
				'lead_days'  => 14,
				'window_days'=> 60,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 4, 1, $y ); },
				'keywords'   => array( 'allergy', 'pollen', 'hayfever', 'seasonal allergies' ),
				'seeds'      => array( 'Best allergy medicine {YEAR}', 'How to manage pollen allergies', 'Allergy-proofing your home' ),
			),

			/* ---------- Wedding / engagement ---------- */
			array(
				'id'         => 'wedding_season',
				'name'       => __( 'Wedding Season', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_RETAIL, self::NICHE_GENERAL ),
				'traffic'    => 'high',
				'lead_days'  => 60,
				'window_days'=> 120,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 5, 1, $y ); },
				'keywords'   => array( 'wedding', 'bridesmaid', 'wedding gift', 'wedding planning' ),
				'seeds'      => array( 'Wedding gift ideas {YEAR}', 'Wedding planning checklist', 'Affordable bridesmaid dresses' ),
			),

			/* ---------- Tech ---------- */
			array(
				'id'         => 'iphone_launch',
				'name'       => __( 'iPhone Launch Window', 'rankwriter-ai' ),
				'niches'     => array( self::NICHE_TECH ),
				'traffic'    => 'high',
				'lead_days'  => 30,
				'window_days'=> 14,
				'date_fn'    => function( $y ) { return mktime( 0, 0, 0, 9, 15, $y ); },
				'keywords'   => array( 'iphone', 'apple event', 'new iphone' ),
				'seeds'      => array( 'New iPhone features {YEAR}', 'iPhone vs Android: latest comparison', 'Best iPhone accessories' ),
			),
		);

		/**
		 * Filter the seasonal events catalog. Add/override entries from
		 * outside the plugin if needed.
		 */
		return apply_filters( 'rwai_seasonal_events', $events );
	}

	/* ============================ Date helpers ============================ */

	/**
	 * Nth occurrence of a weekday in a month.
	 *
	 * @param int $year
	 * @param int $month  1-12
	 * @param int $nth    1-5 (5 = last)
	 * @param int $dow    0=Sun … 6=Sat (PHP date('w') compatible)
	 */
	protected static function nth_weekday( $year, $month, $nth, $dow ) {
		$ts = mktime( 0, 0, 0, $month, 1, $year );
		$first_dow = (int) date( 'w', $ts );
		$offset = ( $dow - $first_dow + 7 ) % 7;
		$day = 1 + $offset + ( $nth - 1 ) * 7;
		// Last-weekday handling (nth=5 sometimes overshoots).
		$days_in_month = (int) date( 't', $ts );
		if ( $day > $days_in_month ) {
			$day -= 7;
		}
		return mktime( 0, 0, 0, $month, $day, $year );
	}

	/**
	 * Anonymous Gregorian Easter algorithm.
	 */
	protected static function easter_timestamp( $year ) {
		$a = $year % 19;
		$b = intdiv( $year, 100 );
		$c = $year % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return mktime( 0, 0, 0, $month, $day, $year );
	}

	/* ============================ Next-occurrence + heat ============================ */

	/**
	 * Returns the next occurrence timestamp >= now for a given event.
	 * Handles year-rollover and long windows where the event is still
	 * "in progress" right now.
	 */
	public function next_occurrence( array $event, $now = null ) {
		$now = $now ?: time();
		$y   = (int) date( 'Y', $now );
		$ts  = call_user_func( $event['date_fn'], $y );
		$window_end = $ts + ( max( 0, (int) $event['window_days'] ) * DAY_IN_SECONDS );
		if ( $window_end < $now ) {
			$ts = call_user_func( $event['date_fn'], $y + 1 );
		}
		return $ts;
	}

	/**
	 * Heat score 0-100. Peaks at exactly `lead_days` before the event,
	 * tapers off as we approach the event and falls hard after the window
	 * closes. Heavy events (traffic=extreme) start heating up further out.
	 */
	public function heat_score( array $event, $now = null ) {
		$now = $now ?: time();
		$ts  = $this->next_occurrence( $event, $now );
		$lead = max( 7, (int) $event['lead_days'] );
		$days_until = ( $ts - $now ) / DAY_IN_SECONDS;
		$window_days = max( 0, (int) $event['window_days'] );

		// Weight by traffic tier — extremes can hit 100 at peak, low-tier
		// events cap at ~70.
		$cap = array(
			'low'     => 60,
			'medium'  => 75,
			'high'    => 90,
			'extreme' => 100,
		);
		$max_score = $cap[ $event['traffic'] ] ?? 70;

		// Inside the event window itself = "happening now" = high heat.
		if ( $days_until <= 0 && $days_until >= -$window_days ) {
			return (int) $max_score;
		}
		// Already past window
		if ( $days_until < -$window_days ) {
			return 0;
		}
		// Too far out — beyond 2x lead time = noise
		if ( $days_until > 2 * $lead ) {
			return 0;
		}

		// Sweet spot is at exactly `lead_days` out — peak there.
		// Beyond lead_days: linear ramp from 0 (2*lead) to max (lead).
		// Inside lead_days: stay at max until event.
		if ( $days_until >= $lead ) {
			$frac = 1 - ( ( $days_until - $lead ) / $lead ); // 1 at $lead, 0 at 2*$lead
			return (int) max( 0, min( $max_score, $max_score * $frac ) );
		}
		return (int) $max_score;
	}

	/* ============================ Recommendations ============================ */

	/**
	 * Optimal publish window for an event: a tuple of ideal_start_date and
	 * ideal_publish_date. The post should be live no later than
	 * ideal_publish_date for the user to fully ride the wave.
	 */
	public function publish_window( array $event, $now = null ) {
		$now  = $now ?: time();
		$ts   = $this->next_occurrence( $event, $now );
		$lead = max( 7, (int) $event['lead_days'] );
		return array(
			'event_date'       => $ts,
			'event_date_human' => date_i18n( get_option( 'date_format' ), $ts ),
			'ideal_publish_ts' => $ts - ( $lead * DAY_IN_SECONDS ),
			'ideal_publish'    => date_i18n( get_option( 'date_format' ), $ts - ( $lead * DAY_IN_SECONDS ) ),
			'latest_publish_ts'=> $ts - ( 3 * DAY_IN_SECONDS ),
			'latest_publish'   => date_i18n( get_option( 'date_format' ), $ts - ( 3 * DAY_IN_SECONDS ) ),
			'days_until_event' => max( 0, (int) ceil( ( $ts - $now ) / DAY_IN_SECONDS ) ),
			'days_until_ideal' => (int) ceil( ( $ts - ( $lead * DAY_IN_SECONDS ) - $now ) / DAY_IN_SECONDS ),
		);
	}

	/**
	 * Expand the event's seed topics, swapping in the current/upcoming year
	 * for the {YEAR} placeholder.
	 */
	public function topic_suggestions( array $event, $now = null ) {
		$now = $now ?: time();
		$ts  = $this->next_occurrence( $event, $now );
		$y   = (int) date( 'Y', $ts );
		$out = array();
		foreach ( (array) ( $event['seeds'] ?? array() ) as $seed ) {
			$out[] = str_replace( '{YEAR}', (string) $y, $seed );
		}
		return $out;
	}

	/* ============================ Niche detection ============================ */

	/**
	 * Best-guess niche for the user's site, using the Style Profile dominant
	 * categories + CPC Scorer niche hints. Returns an array of niche tags
	 * the user is most likely targeting.
	 */
	public function detect_niches() {
		$niches = array();

		if ( class_exists( 'RankWriter_AI_Style_Profile' ) ) {
			$style = ( new RankWriter_AI_Style_Profile() )->get();
			$cats  = isset( $style['dominant_categories'] ) ? (array) $style['dominant_categories'] : ( isset( $style['top_categories'] ) ? (array) $style['top_categories'] : array() );
			foreach ( $cats as $c ) {
				$niches = array_merge( $niches, $this->niche_from_text( (string) ( is_array( $c ) ? ( $c['name'] ?? '' ) : $c ) ) );
			}
		}

		// Fallback: sniff niches from recent post titles.
		if ( empty( $niches ) ) {
			$recent = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 30, 'fields' => 'ids' ) );
			$titles = '';
			foreach ( $recent as $pid ) {
				$titles .= ' ' . get_the_title( $pid );
			}
			$niches = $this->niche_from_text( $titles );
		}

		$niches = array_values( array_unique( $niches ) );
		if ( empty( $niches ) ) {
			$niches = array( self::NICHE_GENERAL );
		}
		return $niches;
	}

	protected function niche_from_text( $text ) {
		$text = strtolower( $text );
		$map = array(
			self::NICHE_FINANCE   => array( 'finance', 'money', 'invest', 'tax', 'budget', 'credit', 'loan', 'mortgage', 'banking' ),
			self::NICHE_EDUCATION => array( 'school', 'student', 'scholarship', 'college', 'university', 'study', 'exam', 'admission' ),
			self::NICHE_RETAIL    => array( 'gift', 'deal', 'sale', 'shop', 'product', 'review', 'buy' ),
			self::NICHE_TRAVEL    => array( 'travel', 'flight', 'hotel', 'vacation', 'trip', 'destination', 'tourism' ),
			self::NICHE_FOOD      => array( 'recipe', 'cook', 'food', 'meal', 'kitchen', 'bake', 'dinner' ),
			self::NICHE_HEALTH    => array( 'health', 'fitness', 'workout', 'diet', 'weight', 'wellness', 'medical' ),
			self::NICHE_PARENTING => array( 'parent', 'kid', 'baby', 'toddler', 'family', 'mom', 'dad' ),
			self::NICHE_TECH      => array( 'tech', 'app', 'iphone', 'android', 'gadget', 'laptop', 'software' ),
		);
		$out = array();
		foreach ( $map as $niche => $stems ) {
			foreach ( $stems as $s ) {
				if ( false !== strpos( $text, $s ) ) {
					$out[] = $niche;
					break;
				}
			}
		}
		return $out;
	}

	/* ============================ Ranked upcoming + coverage ============================ */

	/**
	 * Return upcoming events within the given window, optionally filtered
	 * by niche, sorted by heat-score DESC.
	 *
	 * @param int    $days_ahead  How many days into the future to scan
	 * @param array  $niche_filter Restrict to events with at least one of
	 *                             these niches; empty = all niches
	 * @param bool   $with_coverage Include coverage info per event
	 */
	public function upcoming( $days_ahead = 120, array $niche_filter = array(), $with_coverage = true ) {
		$now      = time();
		$cutoff   = $now + ( max( 1, (int) $days_ahead ) * DAY_IN_SECONDS );
		$results  = array();
		$coverage = $with_coverage ? $this->get_coverage_cache() : array();

		foreach ( $this->events() as $event ) {
			if ( ! empty( $niche_filter ) && empty( array_intersect( $niche_filter, $event['niches'] ) ) ) {
				continue;
			}
			$ts   = $this->next_occurrence( $event, $now );
			if ( $ts > $cutoff && $this->heat_score( $event, $now ) <= 0 ) {
				continue;
			}
			$heat   = $this->heat_score( $event, $now );
			$window = $this->publish_window( $event, $now );
			$cov    = $coverage[ $event['id'] ] ?? array( 'matched_posts' => array(), 'count' => 0 );

			$results[] = array(
				'event'           => $event,
				'heat'            => $heat,
				'next_ts'         => $ts,
				'next_human'      => date_i18n( get_option( 'date_format' ), $ts ),
				'days_until_event'=> $window['days_until_event'],
				'window'          => $window,
				'coverage'        => $cov,
				'topic_suggestions' => $this->topic_suggestions( $event, $now ),
				'priority'        => $this->priority_score( $event, $heat, $cov ),
			);
		}

		usort( $results, function ( $a, $b ) {
			return $b['priority'] - $a['priority'];
		} );

		return $results;
	}

	/**
	 * Composite priority = heat × traffic-tier-weight × (uncovered-bonus).
	 * Sites that already have content for an event get downweighted so the
	 * user is nudged toward gaps.
	 */
	protected function priority_score( array $event, $heat, array $coverage ) {
		$tier_w = array( 'low' => 0.6, 'medium' => 0.8, 'high' => 1.0, 'extreme' => 1.2 );
		$w = $tier_w[ $event['traffic'] ] ?? 0.8;
		$score = $heat * $w;
		if ( ( $coverage['count'] ?? 0 ) === 0 ) {
			$score += 15; // gap bonus
		} elseif ( $coverage['count'] < 3 ) {
			$score += 5;
		}
		return (int) min( 200, $score );
	}

	/* ============================ Coverage detection ============================ */

	/**
	 * Cross-reference event keywords against the user's existing posts and
	 * return a per-event matched-post count. Cached for 24h via an option;
	 * the daily cron refreshes the cache.
	 */
	public function get_coverage_cache() {
		$cached = get_option( self::OPTION_COVERAGE_CACHE, array() );
		if ( is_array( $cached ) && ! empty( $cached['generated_at'] ) && ( time() - (int) $cached['generated_at'] ) < DAY_IN_SECONDS ) {
			return $cached['events'] ?? array();
		}
		return $this->refresh_coverage_cache();
	}

	public function refresh_coverage_cache() {
		$result = array();
		$titles = $this->index_post_titles();
		foreach ( $this->events() as $event ) {
			$matches = array();
			foreach ( (array) ( $event['keywords'] ?? array() ) as $kw ) {
				$kw = strtolower( trim( (string) $kw ) );
				if ( '' === $kw ) { continue; }
				foreach ( $titles as $row ) {
					if ( false !== strpos( $row['title_lower'], $kw ) ) {
						$matches[ $row['ID'] ] = $row;
					}
				}
			}
			$result[ $event['id'] ] = array(
				'count'         => count( $matches ),
				'matched_posts' => array_slice( array_values( $matches ), 0, 5 ),
			);
		}
		update_option( self::OPTION_COVERAGE_CACHE, array(
			'generated_at' => time(),
			'events'       => $result,
		), false );
		return $result;
	}

	protected function index_post_titles() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' LIMIT 5000",
			ARRAY_A
		);
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'ID'          => (int) $r['ID'],
				'title'       => $r['post_title'],
				'title_lower' => strtolower( $r['post_title'] ),
			);
		}
		return $out;
	}

	/* ============================ Niche-specific insights ============================ */

	public function niche_insights( array $niches, $days_ahead = 120 ) {
		$upcoming   = $this->upcoming( $days_ahead, $niches );
		$top3       = array_slice( $upcoming, 0, 3 );
		$gap_events = array_filter( $upcoming, function ( $r ) { return ( $r['coverage']['count'] ?? 0 ) === 0 && $r['heat'] > 0; } );
		return array(
			'niches'      => $niches,
			'upcoming'    => $upcoming,
			'top3'        => $top3,
			'gap_count'   => count( $gap_events ),
			'covered_count' => count( $upcoming ) - count( $gap_events ),
		);
	}

	/* ============================ Calendar view helper ============================ */

	public function calendar_year( $year = null ) {
		$year = $year ? (int) $year : (int) date( 'Y' );
		$now  = time();
		$months = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$months[ $m ] = array(
				'name'   => date_i18n( 'F', mktime( 0, 0, 0, $m, 1, $year ) ),
				'events' => array(),
			);
		}
		foreach ( $this->events() as $event ) {
			$ts    = call_user_func( $event['date_fn'], $year );
			$month = (int) date( 'n', $ts );
			$months[ $month ]['events'][] = array(
				'event'  => $event,
				'ts'     => $ts,
				'day'    => (int) date( 'j', $ts ),
				'heat'   => $this->heat_score( $event, $now ),
			);
		}
		foreach ( $months as &$mref ) {
			usort( $mref['events'], function ( $a, $b ) { return $a['day'] - $b['day']; } );
		}
		return $months;
	}

	/* ============================ Reminder dismissal ============================ */

	public function dismissed_event_ids() {
		$d = get_option( self::OPTION_DISMISSED, array() );
		return is_array( $d ) ? $d : array();
	}

	public function dismiss_event( $event_id ) {
		$d = $this->dismissed_event_ids();
		$d[ $event_id ] = time();
		update_option( self::OPTION_DISMISSED, $d, false );
	}
}
