<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + scoring + lookup layer for Topical Authority Clusters.
 *
 * A "cluster" is one pillar article + N supporting articles, all linked
 * back to the pillar. Supporting articles also link laterally to each
 * other. This is the core of topical authority SEO.
 *
 * Status lifecycle for a supporting topic:
 *   suggested → queued (sent to Autopilot) → published (post exists)
 *               └─→ skipped (user dismissed)
 */
class RankWriter_AI_Cluster_Manager {

	const META_TOPIC_ID = '_rwai_cluster_topic_id';
	const STATUSES      = array( 'suggested', 'queued', 'published', 'skipped' );

	/**
	 * Insert a new cluster. Slug auto-derived from name if not provided.
	 *
	 * @return int|WP_Error Inserted cluster id.
	 */
	public function create( array $args ) {
		global $wpdb;
		if ( ! RankWriter_AI_Clusters_DB::ready() ) {
			return new WP_Error( 'rwai_no_tables', __( 'Cluster tables missing — deactivate and reactivate the plugin.', 'rankwriter-ai' ) );
		}
		$name = isset( $args['name'] ) ? trim( (string) $args['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rwai_no_name', __( 'Cluster name is required.', 'rankwriter-ai' ) );
		}
		$slug = isset( $args['slug'] ) ? sanitize_title( $args['slug'] ) : sanitize_title( $name );
		$slug = $this->ensure_unique_slug( $slug );

		$row = array(
			'name'                    => sanitize_text_field( $name ),
			'slug'                    => $slug,
			'description'             => isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '',
			'pillar_post_id'          => isset( $args['pillar_post_id'] ) ? absint( $args['pillar_post_id'] ) : null,
			'profile_id'              => isset( $args['profile_id'] ) ? absint( $args['profile_id'] ) : null,
			'target_supporting_count' => max( 3, min( 30, isset( $args['target_supporting_count'] ) ? (int) $args['target_supporting_count'] : 6 ) ),
			'semantic_keywords'       => isset( $args['semantic_keywords'] ) ? sanitize_textarea_field( $args['semantic_keywords'] ) : '',
			'created_at'              => current_time( 'mysql' ),
			'updated_at'              => current_time( 'mysql' ),
		);

		$wpdb->insert( RankWriter_AI_Clusters_DB::clusters_table(), $row );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'rwai_insert_failed', __( 'Could not insert cluster.', 'rankwriter-ai' ) );
		}
		return $id;
	}

	public function update( $id, array $args ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'rwai_bad_id', __( 'Invalid cluster ID.', 'rankwriter-ai' ) );
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $args['name'] ) ) {
			$update['name'] = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $args['description'] );
		}
		if ( array_key_exists( 'pillar_post_id', $args ) ) {
			$update['pillar_post_id'] = $args['pillar_post_id'] ? absint( $args['pillar_post_id'] ) : null;
		}
		if ( array_key_exists( 'profile_id', $args ) ) {
			$update['profile_id'] = $args['profile_id'] ? absint( $args['profile_id'] ) : null;
		}
		if ( isset( $args['target_supporting_count'] ) ) {
			$update['target_supporting_count'] = max( 3, min( 30, (int) $args['target_supporting_count'] ) );
		}
		if ( isset( $args['semantic_keywords'] ) ) {
			$update['semantic_keywords'] = sanitize_textarea_field( $args['semantic_keywords'] );
		}
		$wpdb->update( RankWriter_AI_Clusters_DB::clusters_table(), $update, array( 'id' => $id ) );
		return true;
	}

	public function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		$wpdb->delete( RankWriter_AI_Clusters_DB::topics_table(), array( 'cluster_id' => $id ) );
		$wpdb->delete( RankWriter_AI_Clusters_DB::clusters_table(), array( 'id' => $id ) );
		return true;
	}

	/**
	 * Fetch a cluster row plus topic count + completion score. Optionally
	 * include the full topic list.
	 */
	public function get( $id, $with_topics = false ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row = $this->hydrate_cluster_row( $row );
		if ( $with_topics ) {
			$row['topics'] = $this->get_topics( $id );
		}
		return $row;
	}

	public function get_by_slug( $slug ) {
		global $wpdb;
		$slug = sanitize_title( $slug );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . ' WHERE slug = %s', $slug ), ARRAY_A );
		return $row ? $this->hydrate_cluster_row( $row ) : null;
	}

	public function get_all( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'limit'   => 100,
			'offset'  => 0,
			'orderby' => 'updated_at',
			'order'   => 'DESC',
			'profile_id' => 0,
		) );

		$orderby = in_array( $args['orderby'], array( 'name', 'updated_at', 'created_at' ), true ) ? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();
		if ( ! empty( $args['profile_id'] ) ) {
			$where    .= ' AND profile_id = %d';
			$params[]  = absint( $args['profile_id'] );
		}

		$sql = 'SELECT * FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . " WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = $this->hydrate_cluster_row( $r );
		}
		return $out;
	}

	public function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RankWriter_AI_Clusters_DB::clusters_table() );
	}

	/* ---------------------- TOPICS ---------------------- */

	public function add_topic( $cluster_id, $topic, $extra = array() ) {
		global $wpdb;
		$cluster_id = absint( $cluster_id );
		$topic      = trim( (string) $topic );
		if ( ! $cluster_id || '' === $topic ) {
			return new WP_Error( 'rwai_bad_topic', __( 'Cluster ID and topic are required.', 'rankwriter-ai' ) );
		}

		// Prevent duplicate topics inside the same cluster (case-insensitive).
		$dup = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE cluster_id = %d AND LOWER(topic) = %s LIMIT 1',
			$cluster_id,
			strtolower( $topic )
		) );
		if ( $dup ) {
			return (int) $dup;
		}

		$row = array(
			'cluster_id'        => $cluster_id,
			'topic'             => sanitize_text_field( $topic ),
			'post_id'           => isset( $extra['post_id'] ) ? absint( $extra['post_id'] ) : null,
			'status'            => isset( $extra['status'] ) && in_array( $extra['status'], self::STATUSES, true ) ? $extra['status'] : 'suggested',
			'semantic_keywords' => isset( $extra['semantic_keywords'] ) ? sanitize_textarea_field( $extra['semantic_keywords'] ) : '',
			'priority'          => isset( $extra['priority'] ) ? (int) $extra['priority'] : 100,
			'created_at'        => current_time( 'mysql' ),
		);
		$wpdb->insert( RankWriter_AI_Clusters_DB::topics_table(), $row );
		$this->touch_cluster( $cluster_id );
		return (int) $wpdb->insert_id;
	}

	public function update_topic( $topic_id, $args ) {
		global $wpdb;
		$topic_id = absint( $topic_id );
		if ( ! $topic_id ) {
			return false;
		}
		$update = array();
		if ( isset( $args['topic'] ) ) {
			$update['topic'] = sanitize_text_field( $args['topic'] );
		}
		if ( array_key_exists( 'post_id', $args ) ) {
			$update['post_id'] = $args['post_id'] ? absint( $args['post_id'] ) : null;
		}
		if ( isset( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$update['status'] = $args['status'];
		}
		if ( isset( $args['semantic_keywords'] ) ) {
			$update['semantic_keywords'] = sanitize_textarea_field( $args['semantic_keywords'] );
		}
		if ( isset( $args['priority'] ) ) {
			$update['priority'] = (int) $args['priority'];
		}
		if ( empty( $update ) ) {
			return true;
		}

		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT cluster_id FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE id = %d', $topic_id ), ARRAY_A );
		$wpdb->update( RankWriter_AI_Clusters_DB::topics_table(), $update, array( 'id' => $topic_id ) );
		if ( $existing ) {
			$this->touch_cluster( (int) $existing['cluster_id'] );
		}
		return true;
	}

	public function delete_topic( $topic_id ) {
		global $wpdb;
		$topic_id = absint( $topic_id );
		if ( ! $topic_id ) {
			return false;
		}
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT cluster_id FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE id = %d', $topic_id ), ARRAY_A );
		$wpdb->delete( RankWriter_AI_Clusters_DB::topics_table(), array( 'id' => $topic_id ) );
		if ( $existing ) {
			$this->touch_cluster( (int) $existing['cluster_id'] );
		}
		return true;
	}

	public function get_topics( $cluster_id ) {
		global $wpdb;
		$cluster_id = absint( $cluster_id );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE cluster_id = %d ORDER BY priority ASC, id ASC',
			$cluster_id
		), ARRAY_A );

		$detector = class_exists( 'RankWriter_AI_Intent_Detector' ) ? new RankWriter_AI_Intent_Detector() : null;
		$out = array();
		foreach ( (array) $rows as $r ) {
			$r['id']         = (int) $r['id'];
			$r['cluster_id'] = (int) $r['cluster_id'];
			$r['post_id']    = $r['post_id'] ? (int) $r['post_id'] : null;
			$r['priority']   = (int) $r['priority'];
			if ( $detector ) {
				$d = $detector->detect( $r['topic'] );
				$r['intent']            = $d['primary'];
				$r['intent_label']      = $d['label'];
				$r['intent_confidence'] = $d['confidence'];
			}
			$out[] = $r;
		}
		return $out;
	}

	public function get_topic( $topic_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE id = %d', absint( $topic_id ) ), ARRAY_A );
		return $row ?: null;
	}

	/* ---------------------- POST <-> CLUSTER LOOKUP ---------------------- */

	/**
	 * Find which cluster (if any) a post belongs to. Checks both:
	 *  - pillar relationship (cluster.pillar_post_id = post)
	 *  - topic relationship (any topic row with this post_id)
	 */
	public function find_cluster_for_post( $post_id ) {
		global $wpdb;
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return null;
		}
		// Pillar?
		$as_pillar = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . ' WHERE pillar_post_id = %d LIMIT 1',
			$post_id
		) );
		if ( $as_pillar ) {
			return array( 'cluster_id' => (int) $as_pillar, 'role' => 'pillar' );
		}
		// Supporting topic?
		$topic_row = $wpdb->get_row( $wpdb->prepare(
			'SELECT cluster_id, id FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE post_id = %d LIMIT 1',
			$post_id
		), ARRAY_A );
		if ( $topic_row ) {
			return array(
				'cluster_id' => (int) $topic_row['cluster_id'],
				'role'       => 'supporting',
				'topic_id'   => (int) $topic_row['id'],
			);
		}
		return null;
	}

	/**
	 * Get all sibling post IDs in a cluster (the pillar + all supporting
	 * posts that have been generated). Used by the Internal Linker to
	 * boost same-cluster link candidates.
	 */
	public function get_cluster_post_ids( $cluster_id ) {
		global $wpdb;
		$cluster_id = absint( $cluster_id );
		if ( ! $cluster_id ) {
			return array();
		}
		$out = array();
		$pillar = $wpdb->get_var( $wpdb->prepare(
			'SELECT pillar_post_id FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . ' WHERE id = %d',
			$cluster_id
		) );
		if ( $pillar ) {
			$out[] = (int) $pillar;
		}
		$rows = $wpdb->get_col( $wpdb->prepare(
			'SELECT post_id FROM ' . RankWriter_AI_Clusters_DB::topics_table() . ' WHERE cluster_id = %d AND post_id IS NOT NULL',
			$cluster_id
		) );
		foreach ( (array) $rows as $pid ) {
			$out[] = (int) $pid;
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/* ---------------------- SCORING + DUPLICATE DETECTION ---------------------- */

	/**
	 * Completion = (pillar present ? 1 : 0) + (published topics) /
	 *              (target_supporting_count + 1).
	 * Returns 0..100 integer percentage.
	 */
	public function completion_score( $cluster ) {
		if ( is_numeric( $cluster ) ) {
			$cluster = $this->get( (int) $cluster, true );
		}
		if ( ! is_array( $cluster ) ) {
			return 0;
		}
		$target    = max( 1, (int) $cluster['target_supporting_count'] );
		$published = 0;
		if ( ! empty( $cluster['topics'] ) ) {
			foreach ( $cluster['topics'] as $t ) {
				if ( 'published' === $t['status'] && ! empty( $t['post_id'] ) ) {
					$published++;
				}
			}
		}
		$pillar_done = ! empty( $cluster['pillar_post_id'] ) ? 1 : 0;
		$score       = ( $pillar_done + min( $published, $target ) ) / ( $target + 1 );
		return (int) round( $score * 100 );
	}

	/**
	 * Fuzzy duplicate check: returns existing cluster IDs whose name has
	 * significant token overlap with the candidate name.
	 */
	public function find_duplicate_clusters( $candidate_name ) {
		$candidate_name = strtolower( trim( $candidate_name ) );
		if ( '' === $candidate_name ) {
			return array();
		}
		$cand_tokens = array_filter( preg_split( '/\s+/', preg_replace( '/[^a-z0-9\s]/i', ' ', $candidate_name ) ), function ( $t ) {
			return strlen( $t ) >= 4;
		} );
		if ( empty( $cand_tokens ) ) {
			return array();
		}

		$all = $this->get_all( array( 'limit' => 500 ) );
		$dups = array();
		foreach ( $all as $c ) {
			$name = strtolower( $c['name'] );
			if ( $name === $candidate_name ) {
				$dups[] = $c['id'];
				continue;
			}
			$tokens = array_filter( preg_split( '/\s+/', preg_replace( '/[^a-z0-9\s]/i', ' ', $name ) ), function ( $t ) {
				return strlen( $t ) >= 4;
			} );
			if ( empty( $tokens ) ) {
				continue;
			}
			$overlap = count( array_intersect( $cand_tokens, $tokens ) );
			$min_len = min( count( $cand_tokens ), count( $tokens ) );
			if ( $min_len > 0 && ( $overlap / $min_len ) >= 0.6 ) {
				$dups[] = $c['id'];
			}
		}
		return $dups;
	}

	/* ---------------------- internal helpers ---------------------- */

	private function ensure_unique_slug( $slug ) {
		global $wpdb;
		$base = $slug ?: 'cluster';
		$n    = 0;
		do {
			$try = $n ? "$base-$n" : $base;
			$exists = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . RankWriter_AI_Clusters_DB::clusters_table() . ' WHERE slug = %s',
				$try
			) );
			$n++;
		} while ( $exists && $n < 200 );
		return $try;
	}

	private function touch_cluster( $cluster_id ) {
		global $wpdb;
		$wpdb->update(
			RankWriter_AI_Clusters_DB::clusters_table(),
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $cluster_id ) )
		);
	}

	private function hydrate_cluster_row( $row ) {
		$row['id']                      = (int) $row['id'];
		$row['pillar_post_id']          = $row['pillar_post_id'] ? (int) $row['pillar_post_id'] : null;
		$row['profile_id']              = $row['profile_id'] ? (int) $row['profile_id'] : null;
		$row['target_supporting_count'] = (int) $row['target_supporting_count'];
		return $row;
	}
}
