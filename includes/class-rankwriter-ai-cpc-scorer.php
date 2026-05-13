<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPC Opportunity Score engine.
 *
 *   score( $keyword, $country = 'US' )    → full scoring row
 *   score_bulk( $keywords, $country )     → array
 *   tier( $cpc_usd )                      → low|medium|high|extreme
 *   summarize( $rows )                    → aggregate stats for a list
 *
 * Mode A — heuristic (default, instant, no API)
 *   - niche pattern detection from the keyword text
 *   - per-niche base CPC table (US averages, derived from publicly known
 *     AdWords keyword-planner data ranges)
 *   - country modifier (relative to US CPC)
 *   - keyword-pattern bonuses ("best", "near me", "buy", "cheap", "free")
 *   - intent-aware adjustments (commercial / transactional skew higher)
 *
 * Mode B — DataForSEO upgrade (auto-enabled when the user has credentials)
 *   - uses real Google-Ads-derived CPC + search-volume + competition
 *     fetched by RankWriter_AI_Keyword_Research
 *   - blends real data with the heuristic so missing rows still score
 *
 * Everything cached as transients keyed by (keyword + country) for 30
 * days. Heuristics don't drift, so longer TTL is safe.
 */
class RankWriter_AI_CPC_Scorer {

	const TIER_LOW     = 'low';
	const TIER_MEDIUM  = 'medium';
	const TIER_HIGH    = 'high';
	const TIER_EXTREME = 'extreme';

	const CACHE_PREFIX = 'rwai_cpc_';
	const CACHE_TTL    = MONTH_IN_SECONDS;

	/**
	 * Priority niches the user called out — these get a +15 monetization
	 * bonus and a "priority" flag in the result row so the UI can highlight
	 * them.
	 */
	public static function priority_niches() {
		return array(
			'finance', 'insurance', 'legal', 'loans', 'visa_sponsorship',
			'scholarships', 'healthcare', 'saas', 'vpn', 'hosting',
		);
	}

	/**
	 * Niche → average US CPC (USD). These are conservative real-world
	 * averages for typical content articles in each niche, NOT for the
	 * extreme-bid edge cases (mesothelioma, structured-settlement loans,
	 * etc.) which can clear $200+.
	 */
	private static function niche_base_cpc() {
		return array(
			'legal'            => 25.0,
			'loans'            => 20.0,
			'insurance'        => 15.0,
			'finance'          => 10.0,
			'hosting'          => 10.0,
			'saas'             => 8.0,
			'healthcare'       => 8.0,
			'vpn'              => 7.0,
			'crypto'           => 5.0,
			'real_estate'      => 4.0,
			'tech'             => 3.5,
			'business'         => 3.0,
			'travel'           => 2.0,
			'education'        => 1.50,
			'jobs_careers'     => 1.50,
			'beauty'           => 1.50,
			'visa_sponsorship' => 1.20,
			'parenting'        => 1.20,
			'pet_care'         => 1.10,
			'fashion'          => 1.0,
			'fitness'          => 1.0,
			'food'             => 0.80,
			'home_diy'         => 1.0,
			'gaming'           => 0.70,
			'scholarships'     => 0.60,
			'general'          => 1.50,
		);
	}

	/**
	 * Keyword patterns → niche key. First-match wins.
	 */
	private static function niche_patterns() {
		return array(
			'legal'            => array( '/\b(lawyer|attorney|lawsuit|settlement|mesothelioma|dui|divorce|injury claim|legal advice|sue|legal services?)\b/i' ),
			'loans'            => array( '/\b(loan|mortgage|refinance|credit card|payday|student loan|business loan|personal loan|home loan|auto loan)\b/i' ),
			'insurance'        => array( '/\b(insurance|policy|premium|coverage|insurer|liability|underwrit|health insurance|life insurance|auto insurance|home insurance)\b/i' ),
			'finance'          => array( '/\b(invest|stocks?|retire|401k|ira|etf|index fund|trading|wealth|asset|portfolio|bank|banking|savings account|net worth|brokerage)\b/i' ),
			'hosting'          => array( '/\b(hosting|web host|vps|dedicated server|cpanel|cloud hosting|wordpress hosting|managed hosting|server hosting)\b/i' ),
			'vpn'              => array( '/\bvpn\b|\bvirtual private network\b|\bproxy server\b/i' ),
			'saas'             => array( '/\b(saas|crm|erp|software|app|platform|tool|dashboard|workflow|automation tool|project management|productivity tool)\b/i' ),
			'healthcare'       => array( '/\b(medical|doctor|treatment|diagnosis|surgery|medication|dental|therapy|symptom|disease|prescription|clinic|hospital)\b/i' ),
			'crypto'           => array( '/\b(crypto|bitcoin|ethereum|altcoin|defi|nft|blockchain|web3|wallet|exchange)\b/i' ),
			'real_estate'      => array( '/\b(real estate|property|homes for sale|rental|landlord|mortgage|realtor|house hunting|home buying|reit)\b/i' ),
			'visa_sponsorship' => array( '/\b(visa sponsorship|h1b|work visa|green card|immigration|work permit|foreign worker|sponsor visa|visa application)\b/i' ),
			'scholarships'     => array( '/\b(scholarship|grant|fellowship|tuition|financial aid|stipend|bursary|fully funded)\b/i' ),
			'jobs_careers'     => array( '/\b(jobs?|career|hiring|resume|cv|interview|salary|recruit|employment|hire me)\b/i' ),
			'tech'             => array( '/\b(laptop|smartphone|gadget|electronics|cpu|gpu|tech review|hardware|computer|tablet|iphone|android)\b/i' ),
			'business'         => array( '/\b(startup|entrepreneur|business|small business|llc|incorporate|business plan|marketing|seo|copywriting)\b/i' ),
			'beauty'           => array( '/\b(skincare|makeup|cosmetic|hair care|beauty|moisturizer|serum|anti-aging|salon|spa)\b/i' ),
			'fashion'          => array( '/\b(fashion|outfit|style|clothing|wardrobe|trend|what to wear|capsule wardrobe)\b/i' ),
			'parenting'        => array( '/\b(parenting|baby|toddler|kids?|child|infant|parents|family|raising)\b/i' ),
			'pet_care'         => array( '/\b(dog|cat|pet|puppy|kitten|veterinary|pet food|grooming|training)\b/i' ),
			'food'             => array( '/\b(recipe|cooking|meal|dinner|breakfast|baking|kitchen|food|cuisine|chef)\b/i' ),
			'travel'           => array( '/\b(travel|destination|vacation|trip|tourism|hotel|flight|itinerary|places to visit)\b/i' ),
			'fitness'          => array( '/\b(workout|fitness|gym|exercise|cardio|strength|yoga|running|weight loss|bodybuilding)\b/i' ),
			'home_diy'         => array( '/\b(diy|home improvement|garden|backyard|renovation|paint|repair|woodworking|household)\b/i' ),
			'education'        => array( '/\b(course|class|study|learn|education|school|university|college|degree|online learning|tutorial)\b/i' ),
			'gaming'           => array( '/\b(gaming|game|playstation|xbox|nintendo|esports|gamer|console|video game|fortnite)\b/i' ),
		);
	}

	/**
	 * Country code → CPC multiplier (1.0 = US baseline).
	 */
	private static function country_modifiers() {
		return array(
			'US' => 1.00, 'GB' => 0.90, 'CA' => 0.80, 'AU' => 0.75, 'NZ' => 0.65,
			'IE' => 0.65, 'DE' => 0.65, 'FR' => 0.60, 'NL' => 0.65, 'CH' => 0.85,
			'SE' => 0.65, 'NO' => 0.70, 'DK' => 0.65, 'FI' => 0.55,
			'IT' => 0.45, 'ES' => 0.40, 'PT' => 0.35, 'BE' => 0.55, 'AT' => 0.60,
			'JP' => 0.60, 'KR' => 0.55, 'SG' => 0.55, 'HK' => 0.55, 'IL' => 0.50,
			'AE' => 0.50, 'SA' => 0.40,
			'MX' => 0.25, 'BR' => 0.20, 'AR' => 0.15, 'CL' => 0.20, 'CO' => 0.15,
			'IN' => 0.10, 'PH' => 0.12, 'PK' => 0.08, 'BD' => 0.08, 'ID' => 0.10,
			'ZA' => 0.20, 'EG' => 0.10, 'NG' => 0.05, 'KE' => 0.07, 'GH' => 0.06,
			'TR' => 0.20, 'RU' => 0.20, 'PL' => 0.30, 'UA' => 0.15,
		);
	}

	/* ---------------- Public API ---------------- */

	/**
	 * @param string $keyword
	 * @param string $country ISO 2-letter code (US, GB, NG, etc.)
	 * @param array  $hints   Optional: real_cpc (USD), search_volume, competition ('low'|'medium'|'high'), intent (informational|commercial|transactional|navigational)
	 * @return array Score row.
	 */
	public function score( $keyword, $country = 'US', array $hints = array() ) {
		$keyword = trim( (string) $keyword );
		if ( '' === $keyword ) {
			return $this->empty_row();
		}
		$country = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $country ), 0, 2 ) ) ?: 'US';

		$cache_key = self::CACHE_PREFIX . md5( strtolower( $keyword ) . '|' . $country . '|' . md5( serialize( $hints ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['tier'] ) ) {
			return $cached;
		}

		$niche             = $this->detect_niche( $keyword );
		$base_cpc          = $this->base_cpc( $niche );
		$country_modifier  = $this->country_modifier( $country );
		$pattern_mod       = $this->pattern_modifier( $keyword );
		$intent            = isset( $hints['intent'] ) ? $hints['intent'] : $this->infer_intent( $keyword );

		$cpc = $base_cpc * $country_modifier * $pattern_mod['multiplier'];

		// Real-data override: if DataForSEO gave us an actual CPC, blend it
		// at 70/30 with the heuristic (real data dominates but heuristic
		// keeps the country/intent shaping).
		$used_real_data = false;
		if ( ! empty( $hints['real_cpc'] ) && (float) $hints['real_cpc'] > 0 ) {
			$cpc            = ( (float) $hints['real_cpc'] * 0.7 ) + ( $cpc * 0.3 );
			$used_real_data = true;
		}

		// Intent bonus on CPC.
		switch ( $intent ) {
			case 'transactional':
				$cpc *= 1.25;
				break;
			case 'commercial':
				$cpc *= 1.15;
				break;
			case 'navigational':
				$cpc *= 0.5;
				break;
			case 'informational':
			default:
				// no change
				break;
		}

		$tier = self::tier( $cpc );

		$priority = in_array( $niche, self::priority_niches(), true );

		// RPM estimate. Conservative AdSense math: realistic article RPM
		// averages ~CPC × 4 (multiple ad slots × ~1% CTR × ~70% bid capture).
		$rpm = $cpc * 4.0;

		$monetization_score = $this->monetization_score( $cpc, $intent, $priority, $country_modifier );

		$competition = $this->competition_level( $keyword, $hints, $tier );

		$traffic_potential = $this->traffic_potential( $keyword, $hints );

		$row = array(
			'keyword'             => $keyword,
			'country'             => $country,
			'niche'               => $niche,
			'priority_niche'      => $priority,
			'tier'                => $tier,
			'estimated_cpc_usd'   => round( $cpc, 2 ),
			'estimated_cpc_range' => array( round( $cpc * 0.7, 2 ), round( $cpc * 1.4, 2 ) ),
			'rpm_prediction_usd'  => round( $rpm, 2 ),
			'monetization_score'  => $monetization_score,
			'traffic_potential'   => $traffic_potential['label'],
			'traffic_score'       => $traffic_potential['score'],
			'competition_level'   => $competition,
			'country_modifier'    => $country_modifier,
			'used_real_data'      => $used_real_data,
			'pattern_signals'     => $pattern_mod['signals'],
			'intent'              => $intent,
		);

		set_transient( $cache_key, $row, self::CACHE_TTL );
		return $row;
	}

	public function score_bulk( array $keywords, $country = 'US' ) {
		$out = array();
		foreach ( $keywords as $kw ) {
			if ( is_array( $kw ) ) {
				$hints = array();
				if ( isset( $kw['intent'] ) ) {
					$hints['intent'] = $kw['intent'];
				}
				$out[] = $this->score( isset( $kw['keyword'] ) ? $kw['keyword'] : '', $country, $hints );
			} else {
				$out[] = $this->score( (string) $kw, $country );
			}
		}
		return $out;
	}

	public static function tier( $cpc_usd ) {
		$cpc = (float) $cpc_usd;
		if ( $cpc >= 20 ) {
			return self::TIER_EXTREME;
		}
		if ( $cpc >= 5 ) {
			return self::TIER_HIGH;
		}
		if ( $cpc >= 1 ) {
			return self::TIER_MEDIUM;
		}
		return self::TIER_LOW;
	}

	public static function tier_label( $tier ) {
		$labels = array(
			self::TIER_LOW     => __( 'Low CPC', 'rankwriter-ai' ),
			self::TIER_MEDIUM  => __( 'Medium CPC', 'rankwriter-ai' ),
			self::TIER_HIGH    => __( 'High CPC', 'rankwriter-ai' ),
			self::TIER_EXTREME => __( 'Extreme CPC', 'rankwriter-ai' ),
		);
		return isset( $labels[ $tier ] ) ? $labels[ $tier ] : '—';
	}

	public static function tier_color( $tier ) {
		$map = array(
			self::TIER_LOW     => '#787c82',
			self::TIER_MEDIUM  => '#2271b1',
			self::TIER_HIGH    => '#2a7e3b',
			self::TIER_EXTREME => '#8a3ffc',
		);
		return isset( $map[ $tier ] ) ? $map[ $tier ] : '#787c82';
	}

	/**
	 * Aggregate stats for a list of score rows. Used by the dashboard +
	 * cluster pages to show a single blended monetization indicator.
	 */
	public function summarize( array $rows ) {
		if ( empty( $rows ) ) {
			return array(
				'count'             => 0,
				'avg_cpc'           => 0,
				'max_cpc'           => 0,
				'avg_rpm'           => 0,
				'avg_score'         => 0,
				'tier_distribution' => array(),
				'priority_count'    => 0,
				'dominant_tier'     => self::TIER_LOW,
			);
		}
		$sum_cpc   = 0;
		$sum_rpm   = 0;
		$sum_score = 0;
		$max_cpc   = 0;
		$tier_d    = array( self::TIER_LOW => 0, self::TIER_MEDIUM => 0, self::TIER_HIGH => 0, self::TIER_EXTREME => 0 );
		$prio      = 0;
		foreach ( $rows as $r ) {
			$sum_cpc   += (float) $r['estimated_cpc_usd'];
			$sum_rpm   += (float) $r['rpm_prediction_usd'];
			$sum_score += (int) $r['monetization_score'];
			$max_cpc    = max( $max_cpc, (float) $r['estimated_cpc_usd'] );
			if ( isset( $tier_d[ $r['tier'] ] ) ) {
				$tier_d[ $r['tier'] ]++;
			}
			if ( ! empty( $r['priority_niche'] ) ) {
				$prio++;
			}
		}
		$n = count( $rows );
		arsort( $tier_d );
		$dominant = key( $tier_d );

		return array(
			'count'             => $n,
			'avg_cpc'           => round( $sum_cpc / $n, 2 ),
			'max_cpc'           => round( $max_cpc, 2 ),
			'avg_rpm'           => round( $sum_rpm / $n, 2 ),
			'avg_score'         => (int) round( $sum_score / $n ),
			'tier_distribution' => $tier_d,
			'priority_count'    => $prio,
			'dominant_tier'     => $dominant,
		);
	}

	/* ---------------- internals ---------------- */

	public function detect_niche( $keyword ) {
		foreach ( self::niche_patterns() as $niche => $patterns ) {
			foreach ( $patterns as $regex ) {
				if ( preg_match( $regex, $keyword ) ) {
					return $niche;
				}
			}
		}
		return 'general';
	}

	private function base_cpc( $niche ) {
		$map = self::niche_base_cpc();
		return isset( $map[ $niche ] ) ? (float) $map[ $niche ] : (float) $map['general'];
	}

	private function country_modifier( $country ) {
		$map = self::country_modifiers();
		return isset( $map[ $country ] ) ? (float) $map[ $country ] : 0.30; // unknown country → conservative
	}

	private function pattern_modifier( $keyword ) {
		$signals = array();
		$mult    = 1.0;
		$rules   = array(
			// CPC-boosting patterns
			array( '/\bnear me\b/i',                            1.30, 'near_me' ),
			array( '/\bbest\b/i',                               1.25, 'best' ),
			array( '/\btop\s*\d+\b/i',                          1.20, 'top_n' ),
			array( '/\b(cheap|affordable|budget)\b/i',          1.30, 'cheap' ),
			array( '/^\s*(buy|purchase|get|order|apply)\b/i',   1.25, 'transactional_verb' ),
			array( '/\b(quote|quotes)\b/i',                     1.25, 'quote' ),
			array( '/\b(review|reviews)\b/i',                   1.15, 'review' ),
			array( '/\b(compare|comparison|vs)\b/i',            1.20, 'comparison' ),
			array( '/\bin\s+(20\d{2})\b/',                      1.05, 'year' ),
			// CPC-suppressing patterns
			array( '/\b(free|freebie)\b/i',                     0.65, 'free' ),
			array( '/^\s*(what|why|how|when|where|who)\b/i',    0.85, 'question_word' ),
			array( '/\?$/',                                     0.85, 'question_mark' ),
			array( '/\b(tutorial|guide|explained|meaning)\b/i', 0.90, 'tutorial' ),
		);
		foreach ( $rules as $rule ) {
			list( $regex, $factor, $name ) = $rule;
			if ( preg_match( $regex, $keyword ) ) {
				$mult     *= $factor;
				$signals[] = $name;
			}
		}
		// Clamp so a single pattern can't 10x the CPC.
		$mult = max( 0.4, min( 2.5, $mult ) );
		return array(
			'multiplier' => $mult,
			'signals'    => $signals,
		);
	}

	private function infer_intent( $keyword ) {
		// Lightweight intent inference; if the formal Intent Detector is
		// available, defer to it for consistency.
		if ( class_exists( 'RankWriter_AI_Intent_Detector' ) ) {
			$d = ( new RankWriter_AI_Intent_Detector() )->detect( $keyword );
			return $d['primary'];
		}
		$kw = strtolower( $keyword );
		if ( preg_match( '/^\s*(buy|purchase|order|apply|get|download|sign\s*up|subscribe)\b/', $kw ) || strpos( $kw, 'near me' ) !== false ) {
			return 'transactional';
		}
		if ( preg_match( '/\b(best|top \d+|review|vs|compare|alternatives?)\b/', $kw ) ) {
			return 'commercial';
		}
		if ( preg_match( '/^(what|why|how|when|where|who|is|are|can)\b/', $kw ) || substr( $kw, -1 ) === '?' ) {
			return 'informational';
		}
		return 'informational';
	}

	private function monetization_score( $cpc, $intent, $priority, $country_modifier ) {
		$base = min( 100, $cpc * 4 );
		switch ( $intent ) {
			case 'transactional':
				$base += 25;
				break;
			case 'commercial':
				$base += 20;
				break;
			case 'informational':
				$base += 5;
				break;
			case 'navigational':
			default:
				// no change
				break;
		}
		if ( $priority ) {
			$base += 15;
		}
		// Country shapes the realizable score (NG/IN traffic can't earn US-tier RPM).
		$base *= $country_modifier;
		// Re-baseline: the country factor compresses everything; give a
		// modest floor so high-niche keywords in low-CPC markets aren't
		// scored zero.
		$base = max( 0, min( 100, $base + ( $priority ? 8 : 0 ) ) );
		return (int) round( $base );
	}

	private function competition_level( $keyword, $hints, $tier ) {
		if ( ! empty( $hints['competition'] ) ) {
			$c = strtolower( (string) $hints['competition'] );
			if ( in_array( $c, array( 'low', 'medium', 'high' ), true ) ) {
				return $c;
			}
		}
		// Heuristic: high-CPC tiers + commercial signals → high competition.
		$kw = strtolower( $keyword );
		if ( self::TIER_EXTREME === $tier ) {
			return 'high';
		}
		if ( preg_match( '/\b(best|top|review|vs|compare|cheap|buy)\b/', $kw ) ) {
			return self::TIER_HIGH === $tier ? 'high' : 'medium';
		}
		return self::TIER_HIGH === $tier ? 'medium' : 'low';
	}

	private function traffic_potential( $keyword, $hints ) {
		if ( ! empty( $hints['search_volume'] ) ) {
			$v = (int) $hints['search_volume'];
			if ( $v >= 10000 ) {
				return array( 'label' => 'very_high', 'score' => 95 );
			}
			if ( $v >= 1000 ) {
				return array( 'label' => 'high', 'score' => 80 );
			}
			if ( $v >= 100 ) {
				return array( 'label' => 'medium', 'score' => 55 );
			}
			if ( $v >= 10 ) {
				return array( 'label' => 'low', 'score' => 30 );
			}
			return array( 'label' => 'very_low', 'score' => 10 );
		}
		// Heuristic from keyword length (shorter = head term = more volume,
		// harder to rank).
		$word_count = str_word_count( $keyword );
		if ( $word_count <= 2 ) {
			return array( 'label' => 'high', 'score' => 75 );
		}
		if ( $word_count <= 4 ) {
			return array( 'label' => 'medium', 'score' => 55 );
		}
		if ( $word_count <= 6 ) {
			return array( 'label' => 'low-medium', 'score' => 40 );
		}
		return array( 'label' => 'low', 'score' => 25 );
	}

	private function empty_row() {
		return array(
			'keyword'             => '',
			'country'             => 'US',
			'niche'               => 'general',
			'priority_niche'      => false,
			'tier'                => self::TIER_LOW,
			'estimated_cpc_usd'   => 0,
			'estimated_cpc_range' => array( 0, 0 ),
			'rpm_prediction_usd'  => 0,
			'monetization_score'  => 0,
			'traffic_potential'   => 'unknown',
			'traffic_score'       => 0,
			'competition_level'   => 'unknown',
			'country_modifier'    => 1.0,
			'used_real_data'      => false,
			'pattern_signals'     => array(),
			'intent'              => 'informational',
		);
	}
}
