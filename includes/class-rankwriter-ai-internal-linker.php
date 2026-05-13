<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operationalizes the "default internal linking rules" stored on each
 * Category Profile by giving the generator a real list of post URLs to
 * link to, and by running a post-process pass that converts any
 * remaining bare-keyword mentions into <a> tags pointing at matching
 * existing posts.
 */
class RankWriter_AI_Internal_Linker {

	/**
	 * Build a candidate list of existing posts the generator should link from.
	 *
	 * Priority order:
	 *   0) Same-cluster siblings (pillar + already-published cluster topics)
	 *      — this is what makes topical authority wiring work.
	 *   1) Top-performing posts.
	 *   2) Posts in matching category.
	 *   3) Posts whose title contains a topic keyword.
	 *   4) Recent posts as fallback.
	 *
	 * @return array of { id, title, url, excerpt }
	 */
	public function get_candidates( $category_term_id, array $topic_keywords, $limit = 12, $cluster_id = 0 ) {
		$candidates = array();
		$seen       = array();

		// 0) Same-cluster siblings — strongest authority signal.
		if ( $cluster_id && class_exists( 'RankWriter_AI_Cluster_Manager' ) ) {
			$mgr = new RankWriter_AI_Cluster_Manager();
			foreach ( $mgr->get_cluster_post_ids( (int) $cluster_id ) as $sib_id ) {
				if ( isset( $seen[ $sib_id ] ) ) {
					continue;
				}
				$this->push_candidate( $candidates, $seen, (int) $sib_id, 'same-cluster' );
				if ( count( $candidates ) >= $limit ) {
					return $candidates;
				}
			}
		}

		// 1) Top-performing posts from the persisted style profile.
		$style   = new RankWriter_AI_Style_Profile();
		$profile = $style->get();
		if ( ! empty( $profile['top_performing_posts'] ) ) {
			foreach ( $profile['top_performing_posts'] as $tp ) {
				if ( empty( $tp['id'] ) || isset( $seen[ $tp['id'] ] ) ) {
					continue;
				}
				$this->push_candidate( $candidates, $seen, (int) $tp['id'], 'top-performer' );
				if ( count( $candidates ) >= $limit ) {
					return $candidates;
				}
			}
		}

		// 2) Posts in the target category.
		if ( $category_term_id ) {
			$cat_posts = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'category'       => (int) $category_term_id,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			foreach ( $cat_posts as $p ) {
				if ( isset( $seen[ $p->ID ] ) ) {
					continue;
				}
				$this->push_candidate( $candidates, $seen, (int) $p->ID, 'category-match' );
				if ( count( $candidates ) >= $limit ) {
					return $candidates;
				}
			}
		}

		// 3) Posts whose title contains a topic keyword (relevance-bias).
		foreach ( $topic_keywords as $kw ) {
			$kw = trim( (string) $kw );
			if ( strlen( $kw ) < 4 ) {
				continue;
			}
			$hits = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 5,
					's'              => $kw,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			foreach ( $hits as $p ) {
				if ( isset( $seen[ $p->ID ] ) ) {
					continue;
				}
				$this->push_candidate( $candidates, $seen, (int) $p->ID, 'keyword:' . $kw );
				if ( count( $candidates ) >= $limit ) {
					return $candidates;
				}
			}
		}

		// 4) Recent posts as fallback.
		$recent = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( $recent as $p ) {
			if ( isset( $seen[ $p->ID ] ) ) {
				continue;
			}
			$this->push_candidate( $candidates, $seen, (int) $p->ID, 'recent' );
			if ( count( $candidates ) >= $limit ) {
				return $candidates;
			}
		}

		return $candidates;
	}

	private function push_candidate( array &$candidates, array &$seen, $post_id, $reason ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		// Language-aware filtering: only link to posts in the same language
		// as the one being generated. The Content Generator sets the target
		// language via $this->target_language() before calling get_candidates.
		if ( '' !== $this->target_lang && class_exists( 'RankWriter_AI_Language' ) ) {
			$post_lang = RankWriter_AI_Language::get_post_language( $post->ID );
			if ( $post_lang !== $this->target_lang ) {
				return;
			}
		}
		$candidates[] = array(
			'id'      => (int) $post->ID,
			'title'   => $post->post_title,
			'url'     => get_permalink( $post->ID ),
			'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 25 ),
			'reason'  => $reason,
		);
		$seen[ $post->ID ] = true;
	}

	/** Language-scoped candidate filtering (set by Content Generator). */
	private $target_lang = '';
	public function set_target_language( $lang ) {
		$this->target_lang = strtolower( (string) $lang );
	}

	/**
	 * Render the candidate list as a Markdown block for the Claude prompt.
	 */
	public function to_prompt_context( array $candidates ) {
		if ( empty( $candidates ) ) {
			return '';
		}
		$lines = array();
		$lines[] = '## Real internal posts available for linking';
		$lines[] = 'Use <a href="..."> tags pointing at these exact URLs when the topic naturally calls for it. Use descriptive anchor text.';
		$lines[] = '';
		foreach ( $candidates as $c ) {
			$lines[] = '- **' . $c['title'] . '** — ' . $c['url'];
			if ( ! empty( $c['excerpt'] ) ) {
				$lines[] = '  _' . $c['excerpt'] . '_';
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Post-process pass: if Claude returned content with bare mentions of a
	 * candidate's title, link the first occurrence to that post's permalink.
	 * Skips occurrences that are already inside an <a> tag.
	 */
	public function auto_link( $content, array $candidates, $max_links = 5 ) {
		if ( empty( $candidates ) || '' === trim( $content ) ) {
			return $content;
		}
		$linked = 0;
		// Build a map of phrase => url, longest-first so we don't replace substrings.
		$phrases = array();
		foreach ( $candidates as $c ) {
			$title = trim( $c['title'] );
			if ( strlen( $title ) >= 8 ) {
				$phrases[ $title ] = $c['url'];
			}
		}
		uksort( $phrases, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		foreach ( $phrases as $phrase => $url ) {
			if ( $linked >= $max_links ) {
				break;
			}
			$escaped = preg_quote( $phrase, '/' );
			$pattern = '/(?<!href=["\'])(?<!>)\b(' . $escaped . ')\b(?![^<]*<\/a>)/i';
			$replacement = '<a href="' . esc_url( $url ) . '">$1</a>';
			$content = preg_replace( $pattern, $replacement, $content, 1, $count );
			if ( $count ) {
				$linked += $count;
			}
		}
		return $content;
	}
}
