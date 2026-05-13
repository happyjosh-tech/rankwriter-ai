<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls fresh, live keyword + title signals from external sources so the
 * generator can produce articles based on what is CURRENTLY being searched
 * — not on Claude's training-data memory.
 *
 * Sources (in order of cost):
 *   - Google Suggest autocomplete (free, public)
 *   - Google Trends daily-trending RSS by country (free, public)
 *   - Competitor RSS / Atom feeds (free; user supplies domains)
 *   - SerpAPI (optional; user supplies key)
 *   - DataForSEO (optional; user supplies login + password)
 *
 * Results are cached as a transient so repeat lookups don't hit the network.
 */
class RankWriter_AI_Keyword_Research {

	const CACHE_PREFIX = 'rwai_kw_';
	const CACHE_TTL    = HOUR_IN_SECONDS * 6;

	const POOL_OPTION  = 'rwai_keyword_pool';

	/**
	 * Discover keywords + competitor titles for a seed/topic.
	 *
	 * @param string $seed       Seed keyword or topic.
	 * @param string $country    ISO country code (US, GB, NG, etc.) for geo bias.
	 * @param array  $competitor_domains Optional list of competitor domains to harvest from.
	 * @return array {
	 *     @type array $suggest_keywords
	 *     @type array $trending_topics
	 *     @type array $competitor_titles
	 *     @type array $serpapi_related     (only if key configured)
	 *     @type array $dataforseo_volume   (only if creds configured)
	 *     @type array $merged_seed_pool    Deduplicated combined list, ranked.
	 * }
	 */
	public function discover( $seed, $country = 'US', $competitor_domains = array() ) {
		$seed = trim( (string) $seed );
		if ( '' === $seed ) {
			return new WP_Error( 'rwai_no_seed', __( 'A seed keyword is required.', 'rankwriter-ai' ) );
		}

		$cache_key = self::CACHE_PREFIX . md5( $seed . '|' . $country . '|' . implode( ',', (array) $competitor_domains ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$suggest      = $this->google_suggest( $seed, $country );
		$trending     = $this->google_trends_rss( $country );
		$comp_titles  = $this->competitor_titles( $competitor_domains );
		$serpapi      = $this->serpapi_related( $seed, $country );
		$dataforseo   = $this->dataforseo_volume( array_merge( array( $seed ), array_slice( $suggest, 0, 9 ) ), $country );

		$pool = $this->merge_and_rank( $seed, $suggest, $trending, $comp_titles, $serpapi );

		// Tag every pool entry with its detected search intent (heuristic,
		// transient-cached, microseconds per keyword).
		if ( class_exists( 'RankWriter_AI_Intent_Detector' ) ) {
			$intent_detector = new RankWriter_AI_Intent_Detector();
			foreach ( $pool as $i => $row ) {
				$intent       = $intent_detector->detect( $row['keyword'] );
				$pool[ $i ]['intent']            = $intent['primary'];
				$pool[ $i ]['intent_label']      = $intent['label'];
				$pool[ $i ]['intent_confidence'] = $intent['confidence'];
			}
		}

		// CPC Opportunity Score per keyword. If DataForSEO returned real
		// volume/CPC/competition, we blend it in via hints; otherwise the
		// scorer uses its heuristic niche + country + pattern model.
		if ( class_exists( 'RankWriter_AI_CPC_Scorer' ) ) {
			$dfs_map = array();
			if ( ! empty( $dataforseo ) ) {
				foreach ( $dataforseo as $dr ) {
					$dfs_map[ strtolower( $dr['keyword'] ) ] = $dr;
				}
			}
			$cpc_scorer = new RankWriter_AI_CPC_Scorer();
			foreach ( $pool as $i => $row ) {
				$hints = array();
				if ( isset( $pool[ $i ]['intent'] ) ) {
					$hints['intent'] = $pool[ $i ]['intent'];
				}
				$lk = strtolower( $row['keyword'] );
				if ( isset( $dfs_map[ $lk ] ) ) {
					if ( isset( $dfs_map[ $lk ]['cpc'] ) ) {
						$hints['real_cpc'] = (float) $dfs_map[ $lk ]['cpc'];
					}
					if ( isset( $dfs_map[ $lk ]['search_volume'] ) ) {
						$hints['search_volume'] = (int) $dfs_map[ $lk ]['search_volume'];
					}
					if ( isset( $dfs_map[ $lk ]['competition'] ) ) {
						$hints['competition'] = (string) $dfs_map[ $lk ]['competition'];
					}
				}
				$cpc_row = $cpc_scorer->score( $row['keyword'], $country, $hints );
				$pool[ $i ]['cpc']                 = $cpc_row['estimated_cpc_usd'];
				$pool[ $i ]['cpc_tier']            = $cpc_row['tier'];
				$pool[ $i ]['cpc_tier_label']      = RankWriter_AI_CPC_Scorer::tier_label( $cpc_row['tier'] );
				$pool[ $i ]['rpm']                 = $cpc_row['rpm_prediction_usd'];
				$pool[ $i ]['monetization_score']  = $cpc_row['monetization_score'];
				$pool[ $i ]['competition']         = $cpc_row['competition_level'];
				$pool[ $i ]['niche']               = $cpc_row['niche'];
				$pool[ $i ]['priority_niche']      = $cpc_row['priority_niche'];
				$pool[ $i ]['used_real_cpc']       = $cpc_row['used_real_data'];
			}
		}

		// Also group by intent so the UI / generator can serve same-intent
		// keyword pools (avoids mixed-intent articles).
		$intent_groups = array();
		if ( class_exists( 'RankWriter_AI_Intent_Detector' ) ) {
			$keywords_only = array_map( function ( $r ) { return $r['keyword']; }, $pool );
			$groups        = ( new RankWriter_AI_Intent_Detector() )->group_by_intent( $keywords_only );
			foreach ( $groups as $intent_key => $rows ) {
				$intent_groups[ $intent_key ] = count( $rows );
			}
		}

		$cpc_summary = array();
		if ( class_exists( 'RankWriter_AI_CPC_Scorer' ) ) {
			$scorer      = new RankWriter_AI_CPC_Scorer();
			$cpc_summary = $scorer->summarize( $scorer->score_bulk( $pool, $country ) );
		}

		$result = array(
			'seed'              => $seed,
			'country'           => $country,
			'fetched_at'        => current_time( 'mysql' ),
			'suggest_keywords'  => $suggest,
			'trending_topics'   => $trending,
			'competitor_titles' => $comp_titles,
			'serpapi_related'   => $serpapi,
			'dataforseo_volume' => $dataforseo,
			'merged_seed_pool'  => $pool,
			'intent_counts'     => $intent_groups,
			'cpc_summary'       => $cpc_summary,
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		$this->persist_pool( $result );

		return $result;
	}

	/**
	 * Returns the most recent persisted keyword pool merged across all seeds.
	 */
	public function get_pool() {
		$pool = get_option( self::POOL_OPTION, array() );
		return is_array( $pool ) ? $pool : array();
	}

	public function clear_pool() {
		delete_option( self::POOL_OPTION );
	}

	private function persist_pool( array $result ) {
		$pool = $this->get_pool();
		$entry = array(
			'seed'       => $result['seed'],
			'country'    => $result['country'],
			'fetched_at' => $result['fetched_at'],
			'keywords'   => array_slice( $result['merged_seed_pool'], 0, 25 ),
			'titles'     => array_slice( $result['competitor_titles'], 0, 20 ),
			'trending'   => array_slice( $result['trending_topics'], 0, 15 ),
		);
		array_unshift( $pool, $entry );
		$pool = array_slice( $pool, 0, 25 );
		update_option( self::POOL_OPTION, $pool, false );
	}

	/* --------------------------- Google Suggest --------------------------- */

	public function google_suggest( $query, $country = 'US' ) {
		$url = add_query_arg(
			array(
				'client' => 'firefox',
				'q'      => $query,
				'hl'     => strtolower( $country ),
				'gl'     => strtoupper( $country ),
			),
			'https://suggestqueries.google.com/complete/search'
		);

		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return array();
		}
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json[1] ) || ! is_array( $json[1] ) ) {
			return array();
		}

		$out = array();
		foreach ( $json[1] as $s ) {
			$s = trim( (string) $s );
			if ( '' !== $s ) {
				$out[] = $s;
			}
		}

		// Expand with prefix-letter probes for richer long-tail (a..e).
		foreach ( array( 'a', 'b', 'c', 'd', 'e' ) as $letter ) {
			$expand_url = add_query_arg(
				array(
					'client' => 'firefox',
					'q'      => $query . ' ' . $letter,
					'hl'     => strtolower( $country ),
					'gl'     => strtoupper( $country ),
				),
				'https://suggestqueries.google.com/complete/search'
			);
			$er = wp_remote_get( $expand_url, array( 'timeout' => 12 ) );
			if ( is_wp_error( $er ) ) {
				continue;
			}
			$ej = json_decode( (string) wp_remote_retrieve_body( $er ), true );
			if ( isset( $ej[1] ) && is_array( $ej[1] ) ) {
				foreach ( $ej[1] as $s ) {
					$s = trim( (string) $s );
					if ( '' !== $s && ! in_array( $s, $out, true ) ) {
						$out[] = $s;
					}
				}
			}
		}

		return array_slice( $out, 0, 30 );
	}

	/* --------------------------- Google Trends --------------------------- */

	public function google_trends_rss( $country = 'US' ) {
		$url = 'https://trends.google.com/trends/trendingsearches/daily/rss?geo=' . rawurlencode( strtoupper( $country ) );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return array();
		}
		$xml = (string) wp_remote_retrieve_body( $res );
		return $this->extract_rss_items( $xml, 20 );
	}

	/* --------------------------- Competitor RSS --------------------------- */

	public function competitor_titles( array $domains ) {
		$out = array();
		foreach ( $domains as $d ) {
			$d = trim( (string) $d );
			if ( '' === $d ) {
				continue;
			}
			$feed_urls = $this->guess_feed_urls( $d );
			foreach ( $feed_urls as $feed ) {
				$res = wp_remote_get( $feed, array( 'timeout' => 12, 'redirection' => 3 ) );
				if ( is_wp_error( $res ) ) {
					continue;
				}
				if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
					continue;
				}
				$xml   = (string) wp_remote_retrieve_body( $res );
				$items = $this->extract_rss_items( $xml, 15 );
				foreach ( $items as $it ) {
					$out[] = array(
						'title'  => $it['title'],
						'link'   => $it['link'],
						'date'   => $it['date'],
						'source' => $d,
					);
				}
				break;
			}
		}
		// Sort newest first.
		usort( $out, function ( $a, $b ) {
			$ta = $a['date'] ? strtotime( $a['date'] ) : 0;
			$tb = $b['date'] ? strtotime( $b['date'] ) : 0;
			return $tb - $ta;
		} );
		return array_slice( $out, 0, 40 );
	}

	private function guess_feed_urls( $domain ) {
		$domain = preg_replace( '#^https?://#i', '', $domain );
		$domain = rtrim( $domain, '/' );
		$base   = 'https://' . $domain;
		return array(
			$base . '/feed/',
			$base . '/feed',
			$base . '/rss/',
			$base . '/rss',
			$base . '/atom.xml',
			$base . '/feed.xml',
			$base . '/index.xml',
		);
	}

	private function extract_rss_items( $xml, $limit = 20 ) {
		$items = array();
		if ( '' === trim( (string) $xml ) ) {
			return $items;
		}

		// libxml-based parsing with entity loading disabled (XXE safety).
		$prev_error = libxml_use_internal_errors( true );
		$prev_entity = function_exists( 'libxml_disable_entity_loader' ) ? libxml_disable_entity_loader( true ) : null;

		$doc = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_error );
		if ( null !== $prev_entity && function_exists( 'libxml_disable_entity_loader' ) ) {
			libxml_disable_entity_loader( $prev_entity );
		}

		if ( false === $doc ) {
			return $items;
		}

		// RSS 2.0
		if ( isset( $doc->channel->item ) ) {
			foreach ( $doc->channel->item as $item ) {
				$title = isset( $item->title ) ? trim( (string) $item->title ) : '';
				$link  = isset( $item->link ) ? trim( (string) $item->link ) : '';
				$date  = isset( $item->pubDate ) ? trim( (string) $item->pubDate ) : '';
				if ( '' !== $title ) {
					$items[] = array( 'title' => $title, 'link' => $link, 'date' => $date );
				}
				if ( count( $items ) >= $limit ) {
					break;
				}
			}
			return $items;
		}

		// Atom
		if ( isset( $doc->entry ) ) {
			foreach ( $doc->entry as $entry ) {
				$title = isset( $entry->title ) ? trim( (string) $entry->title ) : '';
				$link  = '';
				if ( isset( $entry->link ) ) {
					$attrs = $entry->link->attributes();
					$link  = isset( $attrs['href'] ) ? (string) $attrs['href'] : '';
				}
				$date = isset( $entry->updated ) ? trim( (string) $entry->updated ) : ( isset( $entry->published ) ? trim( (string) $entry->published ) : '' );
				if ( '' !== $title ) {
					$items[] = array( 'title' => $title, 'link' => $link, 'date' => $date );
				}
				if ( count( $items ) >= $limit ) {
					break;
				}
			}
		}

		return $items;
	}

	/* --------------------------- SerpAPI (optional) --------------------------- */

	public function serpapi_related( $query, $country = 'US' ) {
		$key = (string) RankWriter_AI_Helpers::get_setting( 'serpapi_key', '' );
		if ( '' === $key ) {
			return array();
		}

		$url = add_query_arg(
			array(
				'engine'  => 'google',
				'q'       => $query,
				'gl'      => strtolower( $country ),
				'hl'      => strtolower( $country ),
				'api_key' => $key,
			),
			'https://serpapi.com/search.json'
		);

		$res = wp_remote_get( $url, array( 'timeout' => 25 ) );
		if ( is_wp_error( $res ) ) {
			return array();
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $json ) ) {
			return array();
		}

		$out = array(
			'related_searches'  => array(),
			'related_questions' => array(),
			'organic_titles'    => array(),
		);

		if ( ! empty( $json['related_searches'] ) && is_array( $json['related_searches'] ) ) {
			foreach ( $json['related_searches'] as $r ) {
				if ( ! empty( $r['query'] ) ) {
					$out['related_searches'][] = $r['query'];
				}
			}
		}
		if ( ! empty( $json['related_questions'] ) && is_array( $json['related_questions'] ) ) {
			foreach ( $json['related_questions'] as $r ) {
				if ( ! empty( $r['question'] ) ) {
					$out['related_questions'][] = $r['question'];
				}
			}
		}
		if ( ! empty( $json['organic_results'] ) && is_array( $json['organic_results'] ) ) {
			foreach ( array_slice( $json['organic_results'], 0, 10 ) as $r ) {
				if ( ! empty( $r['title'] ) ) {
					$out['organic_titles'][] = array(
						'title' => $r['title'],
						'link'  => isset( $r['link'] ) ? $r['link'] : '',
					);
				}
			}
		}
		return $out;
	}

	/* --------------------------- DataForSEO (optional) --------------------------- */

	public function dataforseo_volume( array $keywords, $country = 'US' ) {
		$login    = (string) RankWriter_AI_Helpers::get_setting( 'dataforseo_login', '' );
		$password = (string) RankWriter_AI_Helpers::get_setting( 'dataforseo_password', '' );
		if ( '' === $login || '' === $password ) {
			return array();
		}

		$payload = array(
			array(
				'language_code'      => 'en',
				'location_name'      => $this->country_name( $country ),
				'keywords'           => array_values( array_unique( array_filter( $keywords ) ) ),
				'include_serp_info'  => false,
			),
		);

		$res = wp_remote_post(
			'https://api.dataforseo.com/v3/keywords_data/google_ads/search_volume/live',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $login . ':' . $password ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array();
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $json ) || empty( $json['tasks'][0]['result'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $json['tasks'][0]['result'] as $row ) {
			if ( ! empty( $row['keyword'] ) ) {
				$out[] = array(
					'keyword'        => $row['keyword'],
					'search_volume'  => isset( $row['search_volume'] ) ? (int) $row['search_volume'] : 0,
					'competition'    => isset( $row['competition'] ) ? $row['competition'] : '',
					'cpc'            => isset( $row['cpc'] ) ? (float) $row['cpc'] : 0,
				);
			}
		}
		usort( $out, function ( $a, $b ) {
			return $b['search_volume'] - $a['search_volume'];
		} );
		return $out;
	}

	private function country_name( $code ) {
		$map = array(
			'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
			'AU' => 'Australia', 'NG' => 'Nigeria', 'IN' => 'India',
			'ZA' => 'South Africa', 'KE' => 'Kenya', 'GH' => 'Ghana',
			'DE' => 'Germany', 'FR' => 'France', 'BR' => 'Brazil',
			'MX' => 'Mexico', 'PH' => 'Philippines', 'PK' => 'Pakistan',
		);
		return isset( $map[ strtoupper( $code ) ] ) ? $map[ strtoupper( $code ) ] : 'United States';
	}

	/**
	 * Merge all signal sources into a single ranked keyword pool.
	 * Suggest > SerpAPI related > Competitor titles (tokenized) > Trending.
	 */
	private function merge_and_rank( $seed, $suggest, $trending, $comp_titles, $serpapi ) {
		$scores = array();

		foreach ( $suggest as $kw ) {
			$kw = strtolower( trim( $kw ) );
			if ( '' === $kw ) {
				continue;
			}
			$scores[ $kw ] = isset( $scores[ $kw ] ) ? $scores[ $kw ] + 4 : 4;
		}
		if ( ! empty( $serpapi['related_searches'] ) ) {
			foreach ( $serpapi['related_searches'] as $kw ) {
				$kw = strtolower( trim( $kw ) );
				if ( '' === $kw ) {
					continue;
				}
				$scores[ $kw ] = isset( $scores[ $kw ] ) ? $scores[ $kw ] + 3 : 3;
			}
		}
		if ( ! empty( $serpapi['related_questions'] ) ) {
			foreach ( $serpapi['related_questions'] as $kw ) {
				$kw = strtolower( trim( $kw ) );
				if ( '' === $kw ) {
					continue;
				}
				$scores[ $kw ] = isset( $scores[ $kw ] ) ? $scores[ $kw ] + 2 : 2;
			}
		}
		foreach ( $comp_titles as $ct ) {
			$kw = strtolower( trim( $ct['title'] ) );
			if ( '' === $kw ) {
				continue;
			}
			$scores[ $kw ] = isset( $scores[ $kw ] ) ? $scores[ $kw ] + 2 : 2;
		}
		foreach ( $trending as $t ) {
			$kw = strtolower( trim( $t['title'] ) );
			if ( '' === $kw ) {
				continue;
			}
			$scores[ $kw ] = isset( $scores[ $kw ] ) ? $scores[ $kw ] + 1 : 1;
		}

		arsort( $scores );
		$out = array();
		foreach ( $scores as $kw => $score ) {
			$out[] = array(
				'keyword' => $kw,
				'score'   => $score,
			);
		}
		return array_slice( $out, 0, 40 );
	}
}
