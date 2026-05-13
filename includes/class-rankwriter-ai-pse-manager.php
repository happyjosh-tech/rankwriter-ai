<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template + Dataset CRUD for the Programmatic SEO Engine.
 *
 * Templates store:
 *   - title_template / slug_template   — variable-interpolated strings
 *   - outline_json                     — section list, intro variants,
 *                                        FAQ pool, section-order variants
 *   - variables_json                   — variable definitions (required,
 *                                        default, type)
 *
 * Dataset rows store one variable-combination each + status (pending,
 * queued, generated, failed, skipped) + the generated post_id.
 *
 * Status lifecycle for a row:
 *   pending  → queued → generated   (happy path)
 *                    → failed       (Claude error / uniqueness fail / etc.)
 *                    → skipped      (user dismissed)
 */
class RankWriter_AI_PSE_Manager {

	const STATUSES = array( 'pending', 'queued', 'generated', 'failed', 'skipped' );

	/* ---------------- TEMPLATES ---------------- */

	public function create_template( array $args ) {
		global $wpdb;
		if ( ! RankWriter_AI_PSE_DB::ready() ) {
			return new WP_Error( 'rwai_no_tables', __( 'PSE tables missing — reactivate the plugin.', 'rankwriter-ai' ) );
		}
		$name = isset( $args['name'] ) ? trim( (string) $args['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rwai_no_name', __( 'Template name is required.', 'rankwriter-ai' ) );
		}
		$slug = isset( $args['slug'] ) ? sanitize_title( $args['slug'] ) : sanitize_title( $name );
		$slug = $this->ensure_unique_slug( $slug );

		$row = array(
			'name'              => sanitize_text_field( $name ),
			'slug'              => $slug,
			'description'       => isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '',
			'title_template'    => isset( $args['title_template'] ) ? sanitize_textarea_field( $args['title_template'] ) : '',
			'slug_template'     => isset( $args['slug_template'] ) ? sanitize_text_field( $args['slug_template'] ) : '',
			'intent'            => isset( $args['intent'] ) ? sanitize_text_field( $args['intent'] ) : 'informational',
			'outline_json'      => wp_json_encode( isset( $args['outline'] ) && is_array( $args['outline'] ) ? $args['outline'] : array() ),
			'variables_json'    => wp_json_encode( isset( $args['variables'] ) && is_array( $args['variables'] ) ? $args['variables'] : array() ),
			'semantic_keywords' => isset( $args['semantic_keywords'] ) ? sanitize_textarea_field( $args['semantic_keywords'] ) : '',
			'profile_id'        => isset( $args['profile_id'] ) && $args['profile_id'] ? absint( $args['profile_id'] ) : null,
			'cluster_id'        => isset( $args['cluster_id'] ) && $args['cluster_id'] ? absint( $args['cluster_id'] ) : null,
			'min_word_count'    => isset( $args['min_word_count'] ) ? max( 600, min( 8000, (int) $args['min_word_count'] ) ) : 1200,
			'min_uniqueness'    => isset( $args['min_uniqueness'] ) ? max( 50, min( 100, (int) $args['min_uniqueness'] ) ) : 70,
			'status'            => isset( $args['status'] ) && in_array( $args['status'], array( 'active', 'paused' ), true ) ? $args['status'] : 'active',
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);
		$wpdb->insert( RankWriter_AI_PSE_DB::templates_table(), $row );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'rwai_insert_failed', __( 'Failed to create template.', 'rankwriter-ai' ) );
		}
		return $id;
	}

	public function update_template( $id, array $args ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'rwai_bad_id', __( 'Invalid template ID.', 'rankwriter-ai' ) );
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $args['name'] ) )              { $update['name']           = sanitize_text_field( $args['name'] ); }
		if ( isset( $args['description'] ) )       { $update['description']    = sanitize_textarea_field( $args['description'] ); }
		if ( isset( $args['title_template'] ) )    { $update['title_template'] = sanitize_textarea_field( $args['title_template'] ); }
		if ( isset( $args['slug_template'] ) )     { $update['slug_template']  = sanitize_text_field( $args['slug_template'] ); }
		if ( isset( $args['intent'] ) )            { $update['intent']         = sanitize_text_field( $args['intent'] ); }
		if ( isset( $args['outline'] ) )           { $update['outline_json']   = wp_json_encode( $args['outline'] ); }
		if ( isset( $args['variables'] ) )         { $update['variables_json'] = wp_json_encode( $args['variables'] ); }
		if ( isset( $args['semantic_keywords'] ) ) { $update['semantic_keywords'] = sanitize_textarea_field( $args['semantic_keywords'] ); }
		if ( array_key_exists( 'profile_id', $args ) ) { $update['profile_id'] = $args['profile_id'] ? absint( $args['profile_id'] ) : null; }
		if ( array_key_exists( 'cluster_id', $args ) ) { $update['cluster_id'] = $args['cluster_id'] ? absint( $args['cluster_id'] ) : null; }
		if ( isset( $args['min_word_count'] ) )    { $update['min_word_count'] = max( 600, min( 8000, (int) $args['min_word_count'] ) ); }
		if ( isset( $args['min_uniqueness'] ) )    { $update['min_uniqueness'] = max( 50, min( 100, (int) $args['min_uniqueness'] ) ); }
		if ( isset( $args['status'] ) && in_array( $args['status'], array( 'active', 'paused' ), true ) ) {
			$update['status'] = $args['status'];
		}
		$wpdb->update( RankWriter_AI_PSE_DB::templates_table(), $update, array( 'id' => $id ) );
		return true;
	}

	public function delete_template( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) { return false; }
		$wpdb->delete( RankWriter_AI_PSE_DB::rows_table(),      array( 'template_id' => $id ) );
		$wpdb->delete( RankWriter_AI_PSE_DB::templates_table(), array( 'id' => $id ) );
		return true;
	}

	public function get_template( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) { return null; }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_PSE_DB::templates_table() . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? $this->hydrate_template( $row ) : null;
	}

	public function get_template_by_slug( $slug ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_PSE_DB::templates_table() . ' WHERE slug = %s', sanitize_title( $slug ) ), ARRAY_A );
		return $row ? $this->hydrate_template( $row ) : null;
	}

	public function get_all_templates() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . RankWriter_AI_PSE_DB::templates_table() . ' ORDER BY updated_at DESC', ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$row              = $this->hydrate_template( $r );
			$row['counts']    = $this->template_row_counts( $row['id'] );
			$out[]            = $row;
		}
		return $out;
	}

	public function template_row_counts( $template_id ) {
		global $wpdb;
		$rows_table = RankWriter_AI_PSE_DB::rows_table();
		$total      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $rows_table WHERE template_id = %d", $template_id ) );
		$by_status  = array();
		foreach ( self::STATUSES as $s ) {
			$by_status[ $s ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $rows_table WHERE template_id = %d AND status = %s", $template_id, $s ) );
		}
		return array( 'total' => $total, 'by_status' => $by_status );
	}

	private function hydrate_template( $row ) {
		$row['id']             = (int) $row['id'];
		$row['profile_id']     = $row['profile_id'] ? (int) $row['profile_id'] : null;
		$row['cluster_id']     = $row['cluster_id'] ? (int) $row['cluster_id'] : null;
		$row['min_word_count'] = (int) $row['min_word_count'];
		$row['min_uniqueness'] = (int) $row['min_uniqueness'];
		$row['outline']        = json_decode( $row['outline_json'],   true ) ?: array();
		$row['variables']      = json_decode( $row['variables_json'], true ) ?: array();
		return $row;
	}

	private function ensure_unique_slug( $slug ) {
		global $wpdb;
		$base = $slug ?: 'pse-template';
		$n    = 0;
		do {
			$try    = $n ? "$base-$n" : $base;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RankWriter_AI_PSE_DB::templates_table() . ' WHERE slug = %s', $try ) );
			$n++;
		} while ( $exists && $n < 200 );
		return $try;
	}

	/* ---------------- DATASET ROWS ---------------- */

	/**
	 * Insert a single dataset row. Idempotent — duplicate value-hashes
	 * are rejected by the DB UNIQUE constraint and return the existing id.
	 *
	 * @return int Inserted or existing row id.
	 */
	public function add_row( $template_id, array $values ) {
		global $wpdb;
		$template_id = absint( $template_id );
		if ( ! $template_id ) {
			return new WP_Error( 'rwai_bad_template', __( 'Missing template ID.', 'rankwriter-ai' ) );
		}
		ksort( $values );
		$canonical = array();
		foreach ( $values as $k => $v ) {
			$canonical[ sanitize_key( $k ) ] = trim( (string) $v );
		}
		$hash    = md5( wp_json_encode( $canonical ) );
		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . RankWriter_AI_PSE_DB::rows_table() . ' WHERE template_id = %d AND values_hash = %s',
			$template_id, $hash
		) );
		if ( $existing ) {
			return (int) $existing;
		}
		$wpdb->insert( RankWriter_AI_PSE_DB::rows_table(), array(
			'template_id' => $template_id,
			'values_json' => wp_json_encode( $canonical ),
			'values_hash' => $hash,
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Bulk insert. Returns counts: { inserted, skipped_duplicates }.
	 */
	public function add_rows_bulk( $template_id, array $rows ) {
		$inserted = 0;
		$dupes    = 0;
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) || empty( $r ) ) {
				continue;
			}
			$before = $this->template_row_counts( $template_id );
			$id     = $this->add_row( $template_id, $r );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$after = $this->template_row_counts( $template_id );
			if ( $after['total'] > $before['total'] ) {
				$inserted++;
			} else {
				$dupes++;
			}
		}
		return array( 'inserted' => $inserted, 'skipped_duplicates' => $dupes );
	}

	public function update_row( $row_id, array $args ) {
		global $wpdb;
		$row_id = absint( $row_id );
		if ( ! $row_id ) { return false; }
		$update = array();
		if ( isset( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$update['status'] = $args['status'];
		}
		if ( array_key_exists( 'post_id', $args ) ) {
			$update['post_id'] = $args['post_id'] ? absint( $args['post_id'] ) : null;
		}
		if ( isset( $args['uniqueness_score'] ) ) {
			$update['uniqueness_score'] = max( 0, min( 100, (int) $args['uniqueness_score'] ) );
		}
		if ( isset( $args['variant_signature'] ) ) {
			$update['variant_signature'] = substr( (string) $args['variant_signature'], 0, 32 );
		}
		if ( array_key_exists( 'error_message', $args ) ) {
			$update['error_message'] = $args['error_message'] ? mb_substr( (string) $args['error_message'], 0, 1000 ) : null;
		}
		if ( isset( $args['attempts_increment'] ) && $args['attempts_increment'] ) {
			$current_attempts = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT attempts FROM ' . RankWriter_AI_PSE_DB::rows_table() . ' WHERE id = %d', $row_id ) );
			$update['attempts'] = $current_attempts + 1;
		}
		if ( isset( $args['queued_at'] ) )    { $update['queued_at']    = $args['queued_at']; }
		if ( isset( $args['generated_at'] ) ) { $update['generated_at'] = $args['generated_at']; }
		if ( empty( $update ) ) { return true; }
		$wpdb->update( RankWriter_AI_PSE_DB::rows_table(), $update, array( 'id' => $row_id ) );
		return true;
	}

	public function delete_row( $row_id ) {
		global $wpdb;
		$wpdb->delete( RankWriter_AI_PSE_DB::rows_table(), array( 'id' => absint( $row_id ) ) );
		return true;
	}

	public function get_row( $row_id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RankWriter_AI_PSE_DB::rows_table() . ' WHERE id = %d', absint( $row_id ) ), ARRAY_A );
		return $r ? $this->hydrate_row( $r ) : null;
	}

	public function get_rows( $template_id, $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'status' => '',
			'limit'  => 50,
			'offset' => 0,
		) );
		$where  = 'template_id = %d';
		$params = array( absint( $template_id ) );
		if ( ! empty( $args['status'] ) ) {
			$where    .= ' AND status = %s';
			$params[]  = $args['status'];
		}
		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );
		$sql = 'SELECT * FROM ' . RankWriter_AI_PSE_DB::rows_table() . " WHERE $where ORDER BY id ASC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = $this->hydrate_row( $r );
		}
		return $out;
	}

	public function next_pending_rows( $limit = 5 ) {
		global $wpdb;
		$sql = 'SELECT r.*, t.status AS template_status FROM ' . RankWriter_AI_PSE_DB::rows_table() . ' r '
			. 'INNER JOIN ' . RankWriter_AI_PSE_DB::templates_table() . ' t ON t.id = r.template_id '
			. 'WHERE r.status = %s AND t.status = %s AND r.attempts < 3 '
			. 'ORDER BY r.id ASC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, 'pending', 'active', absint( $limit ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			unset( $r['template_status'] );
			$out[] = $this->hydrate_row( $r );
		}
		return $out;
	}

	private function hydrate_row( $r ) {
		$r['id']               = (int) $r['id'];
		$r['template_id']      = (int) $r['template_id'];
		$r['post_id']          = $r['post_id'] ? (int) $r['post_id'] : null;
		$r['attempts']         = (int) $r['attempts'];
		$r['uniqueness_score'] = isset( $r['uniqueness_score'] ) ? (int) $r['uniqueness_score'] : null;
		$r['values']           = json_decode( $r['values_json'], true ) ?: array();
		return $r;
	}

	/* ---------------- Variable interpolation ---------------- */

	/**
	 * Interpolate `{var}` placeholders in a string using row values.
	 * Missing variables left as-is so they're visible in the UI.
	 */
	public static function interpolate( $template_string, array $values ) {
		return preg_replace_callback( '/\{([a-zA-Z0-9_]+)\}/', function ( $m ) use ( $values ) {
			$k = $m[1];
			return array_key_exists( $k, $values ) ? (string) $values[ $k ] : $m[0];
		}, (string) $template_string );
	}

	/**
	 * Aggregate stats across all templates for the dashboard header.
	 */
	public function global_stats() {
		global $wpdb;
		$rows  = RankWriter_AI_PSE_DB::rows_table();
		$templ = RankWriter_AI_PSE_DB::templates_table();
		return array(
			'template_count'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $templ" ),
			'active_templates'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM $templ WHERE status = 'active'" ),
			'total_rows'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $rows" ),
			'pending'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $rows WHERE status = 'pending'" ),
			'generated'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $rows WHERE status = 'generated'" ),
			'failed'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $rows WHERE status = 'failed'" ),
		);
	}
}
