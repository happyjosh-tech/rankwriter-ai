<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic cluster analysis (no LLM calls).
 *
 *  - find_topical_gaps()    — given a cluster, surface gaps in coverage by
 *                             comparing existing supporting topics against
 *                             the topic-phrases the site already covers
 *  - auto_match_posts()     — scan all published posts and attach any
 *                             whose title closely matches a topic row that
 *                             doesn't yet have a post_id
 *  - score_internal_links() — given a generated post, count how many of
 *                             its outbound internal links point to other
 *                             posts in the same cluster (a real measure
 *                             of cluster wiring strength)
 */
class RankWriter_AI_Cluster_Analyzer {

	private $manager;

	public function __construct() {
		$this->manager = new RankWriter_AI_Cluster_Manager();
	}

	/**
	 * Compare a cluster's supporting topics against the topic-phrase
	 * inventory in the Blog Style Profile, and flag covered-but-not-linked
	 * topics + obvious missing sub-intents.
	 *
	 * @return array {
	 *   @type array $orphan_posts    Posts that match a cluster topic but
	 *                                aren't yet linked to it.
	 *   @type array $missing_intents Topic-shape patterns the cluster
	 *                                doesn't yet cover (how-to, comparison,
	 *                                cost, best-of, etc.).
	 * }
	 */
	public function find_topical_gaps( $cluster_id ) {
		$cluster = $this->manager->get( $cluster_id, true );
		if ( ! $cluster ) {
			return new WP_Error( 'rwai_no_cluster', __( 'Cluster not found.', 'rankwriter-ai' ) );
		}

		$gaps = array(
			'orphan_posts'    => $this->find_orphan_posts( $cluster ),
			'missing_intents' => $this->find_missing_intents( $cluster ),
		);
		return $gaps;
	}

	/**
	 * Scan every supporting topic in a cluster that doesn't yet have a
	 * post_id, look for an existing published post whose title closely
	 * matches the topic, and link them.
	 *
	 * @return int Number of topics matched + linked.
	 */
	public function auto_match_posts( $cluster_id ) {
		$cluster = $this->manager->get( $cluster_id, true );
		if ( ! $cluster ) {
			return 0;
		}
		$matched = 0;
		foreach ( (array) $cluster['topics'] as $t ) {
			if ( ! empty( $t['post_id'] ) ) {
				continue;
			}
			$pid = $this->find_matching_post( $t['topic'] );
			if ( $pid ) {
				$this->manager->update_topic( $t['id'], array(
					'post_id' => $pid,
					'status'  => 'published',
				) );
				update_post_meta( $pid, RankWriter_AI_Cluster_Manager::META_TOPIC_ID, (int) $t['id'] );
				$matched++;
			}
		}
		return $matched;
	}

	/**
	 * Count how many of a generated post's outbound internal links land
	 * on sibling posts in the same cluster.
	 */
	public function score_internal_links( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$rel = $this->manager->find_cluster_for_post( $post_id );
		if ( ! $rel ) {
			return 0;
		}
		$siblings = $this->manager->get_cluster_post_ids( $rel['cluster_id'] );
		$sib_slugs = array();
		foreach ( $siblings as $sid ) {
			if ( $sid === $post_id ) {
				continue;
			}
			$p = get_post( $sid );
			if ( $p ) {
				$sib_slugs[ $p->post_name ] = $sid;
			}
		}

		$hits = 0;
		if ( preg_match_all( '/<a\b[^>]*href=("([^"]+)"|\'([^\']+)\')/i', $post->post_content, $m ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			foreach ( $m[2] as $idx => $href ) {
				$href = '' !== $href ? $href : ( isset( $m[3][ $idx ] ) ? $m[3][ $idx ] : '' );
				if ( '' === $href ) {
					continue;
				}
				$parsed_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( $parsed_host && $parsed_host !== $host ) {
					continue;
				}
				$path = trim( (string) wp_parse_url( $href, PHP_URL_PATH ), '/' );
				if ( '' === $path ) {
					continue;
				}
				// Take the last path segment as the slug.
				$slug = basename( $path );
				if ( isset( $sib_slugs[ $slug ] ) ) {
					$hits++;
				}
			}
		}
		return $hits;
	}

	/* ---------------------- internals ---------------------- */

	private function find_orphan_posts( $cluster ) {
		$orphans = array();
		foreach ( (array) $cluster['topics'] as $t ) {
			if ( ! empty( $t['post_id'] ) ) {
				continue;
			}
			$pid = $this->find_matching_post( $t['topic'] );
			if ( $pid ) {
				$orphans[] = array(
					'topic_id' => (int) $t['id'],
					'topic'    => $t['topic'],
					'post_id'  => $pid,
					'title'    => get_the_title( $pid ),
				);
			}
		}
		return $orphans;
	}

	private function find_missing_intents( $cluster ) {
		$topics = array();
		foreach ( (array) $cluster['topics'] as $t ) {
			$topics[] = strtolower( $t['topic'] );
		}
		$blob = implode( ' | ', $topics );

		// 1) Sub-intent shape coverage (how-to / cost / comparison / etc.)
		$shapes = array(
			'how-to'      => array( 'how to', 'how do', 'step by step', 'guide' ),
			'cost'        => array( 'cost', 'price', 'how much', 'fee', 'pricing' ),
			'comparison'  => array( ' vs ', ' versus ', 'compare', 'difference' ),
			'best-of'     => array( 'best ', 'top ', 'top 10', 'top 5' ),
			'requirements'=> array( 'requirements', 'qualify', 'eligibility', 'who can' ),
			'application' => array( 'apply', 'application', 'how to apply' ),
			'beginner'    => array( 'beginner', 'for beginners', 'getting started', 'first time' ),
			'mistakes'    => array( 'mistakes', 'what not to', 'avoid' ),
			'examples'    => array( 'examples', 'case study', 'real-world' ),
			'faq'         => array( 'faq', 'questions', 'common questions' ),
		);

		$missing = array();
		foreach ( $shapes as $key => $needles ) {
			$found = false;
			foreach ( $needles as $n ) {
				if ( false !== strpos( $blob, $n ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$missing[] = $key;
			}
		}

		// 2) Full-funnel intent coverage (informational / commercial /
		// transactional / navigational). A complete cluster spans the
		// funnel — flag any major bucket with zero topics.
		if ( class_exists( 'RankWriter_AI_Intent_Detector' ) ) {
			$buckets = array(
				RankWriter_AI_Intent_Detector::INTENT_INFO  => 0,
				RankWriter_AI_Intent_Detector::INTENT_COMM  => 0,
				RankWriter_AI_Intent_Detector::INTENT_TRANS => 0,
			);
			$detector = new RankWriter_AI_Intent_Detector();
			foreach ( (array) $cluster['topics'] as $t ) {
				$intent = $detector->detect( $t['topic'] );
				if ( isset( $buckets[ $intent['primary'] ] ) ) {
					$buckets[ $intent['primary'] ]++;
				}
			}
			foreach ( $buckets as $bucket => $count ) {
				if ( 0 === $count ) {
					$missing[] = 'intent:' . $bucket;
				}
			}
		}

		return $missing;
	}

	/**
	 * Find a published post whose title closely matches a topic string.
	 * Uses a 60% Jaccard-like token overlap on stopword-filtered tokens.
	 */
	private function find_matching_post( $topic ) {
		$tokens = $this->tokenize( $topic );
		if ( empty( $tokens ) ) {
			return 0;
		}

		// Use WP search to narrow candidates by the longest token.
		usort( $tokens, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		$anchor = $tokens[0];

		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $anchor,
		) );
		$best_pid   = 0;
		$best_score = 0.0;
		foreach ( (array) $posts as $p ) {
			$pt = $this->tokenize( $p->post_title );
			if ( empty( $pt ) ) {
				continue;
			}
			$overlap = count( array_intersect( $tokens, $pt ) );
			$min_len = min( count( $tokens ), count( $pt ) );
			if ( ! $min_len ) {
				continue;
			}
			$score = $overlap / $min_len;
			if ( $score >= 0.6 && $score > $best_score ) {
				$best_score = $score;
				$best_pid   = (int) $p->ID;
			}
		}
		return $best_pid;
	}

	private function tokenize( $text ) {
		if ( class_exists( 'RankWriter_AI_Helpers' ) ) {
			return RankWriter_AI_Helpers::tokenize( $text );
		}
		$text = strtolower( wp_strip_all_tags( (string) $text ) );
		$text = preg_replace( '/[^a-z0-9\s]/', ' ', $text );
		$parts = preg_split( '/\s+/', trim( (string) $text ) );
		return array_values( array_filter( (array) $parts, function ( $t ) { return strlen( $t ) >= 4; } ) );
	}
}
