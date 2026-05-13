<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claude-powered helpers for the Topical Authority Cluster Engine:
 *
 *  - suggest_supporting_topics()   — given a pillar topic, return 6-10
 *                                    supporting article topics
 *  - suggest_semantic_keywords()   — comma-separated semantic keyword pool
 *  - suggest_pillar_titles()       — when only a niche is known, propose
 *                                    pillar topics that anchor a cluster
 *  - suggest_clusters_from_blog()  — propose entire cluster groupings
 *                                    from the existing Blog Style Profile
 *
 * All calls go through the existing Claude client and respect the user's
 * API key + model settings.
 */
class RankWriter_AI_Cluster_Suggester {

	private $client;
	private $profiles;
	private $style;

	public function __construct() {
		$this->client   = new RankWriter_AI_Claude_Client();
		$this->profiles = new RankWriter_AI_Category_Profiles();
		$this->style    = new RankWriter_AI_Style_Profile();
	}

	/**
	 * @return array|WP_Error List of topic strings (cleaned, deduped).
	 */
	public function suggest_supporting_topics( $pillar_topic, $count = 8, $profile_id = 0, array $existing_topics = array() ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$profile_block = '';
		if ( $profile_id ) {
			$profile_block = $this->profiles->to_prompt_context( $profile_id );
		}
		$blog_summary = $this->blog_summary();

		$avoid = '';
		if ( ! empty( $existing_topics ) ) {
			$avoid = "\n## Topics already in this cluster (do not duplicate)\n- " . implode( "\n- ", array_slice( array_map( 'wp_strip_all_tags', $existing_topics ), 0, 30 ) ) . "\n";
		}

		$system = "You are an SEO content strategist. You design topical authority clusters: one pillar article and several supporting articles that link back to it, all built around tightly related search intents. Your job is to propose supporting article topics for a given pillar.\n\n"
			. $blog_summary
			. ( $profile_block ? "\n" . $profile_block . "\n" : '' )
			. "\n## Cluster rules\n"
			. "- Each supporting topic must be a real article a reader would search for in 2026.\n"
			. "- Each must be DIFFERENT enough from the others that they don't cannibalize each other in search.\n"
			. "- Together they should cover the major sub-intents of the pillar (transactional, informational, comparison, troubleshooting, list, geographic variants, etc.).\n"
			. "- Avoid yes/no questions. Use specific article titles that promise clear value.\n"
			. "- Avoid duplicating any existing cluster topics listed below.\n";

		$user = "Pillar topic: \"$pillar_topic\"\n\n"
			. "Propose {$count} supporting article topics that, together with this pillar, form a tight topical authority cluster."
			. $avoid
			. "\n\nReturn ONLY a JSON array of strings (no preamble, no markdown). Example:\n"
			. '["First supporting topic", "Second supporting topic", ...]';

		$text = $this->client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_string_list( $text );
	}

	/**
	 * @return string|WP_Error Comma-separated semantic keyword pool.
	 */
	public function suggest_semantic_keywords( $pillar_topic, $count = 20 ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$system = "You map a topic to its semantic keyword cloud — the entities, modifiers, related search phrases, and co-occurring terms that search engines associate with it. Return tight, niche-relevant keywords only.";
		$user   = "Topic: \"$pillar_topic\"\n\nReturn {$count} semantic keywords / phrases that should appear (collectively) across the articles in this topic's cluster. Comma-separated, no numbering, no quotes, no preamble. Mix head terms with long-tail phrases.";

		$text = $this->client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = trim( (string) $text );
		// Best-effort cleanup: strip code fences, trim each item.
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$parts = array_map( 'trim', preg_split( '/[,\n]+/', $text ) );
		$parts = array_values( array_filter( $parts, function ( $p ) {
			return '' !== $p && strlen( $p ) <= 80;
		} ) );
		return implode( ', ', array_slice( $parts, 0, max( 1, (int) $count ) ) );
	}

	/**
	 * Suggest pillar topics for a given category profile.
	 */
	public function suggest_pillar_titles( $profile_id, $count = 5 ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$profile = $this->profiles->get( $profile_id );
		if ( ! $profile ) {
			return new WP_Error( 'rwai_no_profile', __( 'Profile not found.', 'rankwriter-ai' ) );
		}
		$profile_block = $this->profiles->to_prompt_context( $profile_id );

		$system = "You design topical authority clusters. A pillar is a broad, comprehensive article that anchors many supporting articles. Pillars are evergreen, search-volume-heavy, and feel like the natural top-of-funnel entry point for a niche.\n\n" . $this->blog_summary() . "\n\n" . $profile_block;

		$user = "For the \"{$profile['name']}\" category, propose {$count} pillar article titles. Each pillar should be broad enough to anchor 6-10 supporting articles but specific enough to rank. Return JSON array of strings only.";

		$text = $this->client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_string_list( $text );
	}

	/**
	 * Examine the existing Blog Style Profile and propose cluster
	 * groupings. Useful for old blogs that want to retroactively
	 * organize existing content into clusters.
	 *
	 * Returns: array of arrays, each: { pillar, supporting: [...] }.
	 */
	public function suggest_clusters_from_blog( $count = 5 ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}
		$style = $this->style->get();
		if ( empty( $style ) ) {
			return new WP_Error( 'rwai_no_style', __( 'Run the Blog Analyzer first so we have content to cluster.', 'rankwriter-ai' ) );
		}

		$titles = array();
		if ( ! empty( $style['existing_post_titles'] ) ) {
			foreach ( array_slice( $style['existing_post_titles'], 0, 80 ) as $pt ) {
				$titles[] = $pt['title'];
			}
		}
		$cats = array();
		if ( ! empty( $style['dominant_categories'] ) ) {
			foreach ( $style['dominant_categories'] as $c ) {
				$cats[] = $c['name'] . ' (' . $c['count'] . ')';
			}
		}

		$system = "You audit blog content and propose topical authority clusters: groupings of related articles around a pillar topic. You look at existing article titles, dominant categories, and audience intent.\n\n" . $this->blog_summary();

		$user  = "Below is a sample of post titles + dominant categories from a WordPress blog. Propose {$count} topical authority clusters this blog should build (or formalize) to maximize topical authority and internal linking.\n\n";
		$user .= "## Categories\n- " . implode( "\n- ", $cats ) . "\n\n";
		$user .= "## Sample post titles\n- " . implode( "\n- ", array_slice( $titles, 0, 80 ) ) . "\n\n";
		$user .= "Return ONLY a JSON array of objects with this exact shape:\n";
		$user .= "[{\"pillar\": \"<pillar topic>\", \"supporting\": [\"<topic 1>\", \"<topic 2>\", ...]}, ...]\n\n";
		$user .= "Each cluster: 1 pillar + 5-7 supporting topics. Pick clusters where the blog already has partial coverage but is missing 3-5 supporting pieces.";

		$text = $this->client->send( $system, array( array( 'role' => 'user', 'content' => $user ) ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return $this->parse_cluster_list( $text );
	}

	/* ---------------------- helpers ---------------------- */

	private function blog_summary() {
		$s = $this->style->get();
		if ( empty( $s ) ) {
			return '(no Blog Style Profile yet — treat the site as a generic content blog)';
		}
		$bits = array();
		if ( ! empty( $s['summary'] ) ) {
			$bits[] = $s['summary'];
		}
		if ( ! empty( $s['preferred_tone'] ) ) {
			$bits[] = 'Tone: ' . $s['preferred_tone'];
		}
		if ( ! empty( $s['audience_intent']['dominant'] ) ) {
			$bits[] = 'Audience intent: ' . $s['audience_intent']['dominant'];
		}
		return '## Blog context' . "\n" . implode( '. ', $bits );
	}

	private function parse_string_list( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$parsed = json_decode( $text, true );
		if ( ! is_array( $parsed ) ) {
			// Try to find an array substring.
			if ( preg_match( '/\[.*\]/s', $text, $m ) ) {
				$parsed = json_decode( $m[0], true );
			}
		}
		if ( ! is_array( $parsed ) ) {
			// Last resort: split lines.
			$parsed = array();
			foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
				$line = trim( $line, " \t\"'•-*0123456789.)" );
				if ( '' !== $line && strlen( $line ) <= 200 ) {
					$parsed[] = $line;
				}
			}
		}
		$out  = array();
		$seen = array();
		foreach ( $parsed as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$item = trim( $item );
			if ( '' === $item ) {
				continue;
			}
			$key = strtolower( $item );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
		}
		return $out;
	}

	private function parse_cluster_list( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$parsed = json_decode( $text, true );
		if ( ! is_array( $parsed ) && preg_match( '/\[.*\]/s', $text, $m ) ) {
			$parsed = json_decode( $m[0], true );
		}
		if ( ! is_array( $parsed ) ) {
			return array();
		}
		$out = array();
		foreach ( $parsed as $item ) {
			if ( ! is_array( $item ) || empty( $item['pillar'] ) ) {
				continue;
			}
			$pillar     = trim( (string) $item['pillar'] );
			$supporting = isset( $item['supporting'] ) && is_array( $item['supporting'] ) ? $item['supporting'] : array();
			$clean_sup  = array();
			foreach ( $supporting as $s ) {
				if ( is_string( $s ) ) {
					$s = trim( $s );
					if ( '' !== $s ) {
						$clean_sup[] = $s;
					}
				}
			}
			if ( '' !== $pillar ) {
				$out[] = array(
					'pillar'     => $pillar,
					'supporting' => array_values( array_unique( $clean_sup ) ),
				);
			}
		}
		return $out;
	}
}
