<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema printer / legacy build-and-save shim.
 *
 * The real construction logic lives in RankWriter_AI_Schema_Engine — this
 * class is responsible only for:
 *
 *   - The `wp_head` hook that prints the JSON-LD block
 *   - The "don't print if Rank Math / Yoast / SEOPress is active" guard
 *     that prevents duplicate structured data
 *   - Backwards compat: the old `build_and_save( $post_id, $type, $html )`
 *     method still works (delegates to the engine), so older Content
 *     Generator code paths keep working without changes.
 */
class RankWriter_AI_Schema_Injector {

	// Legacy single-schema meta key — kept for backwards compat. New writes
	// land in RankWriter_AI_Schema_Engine::META_GRAPH.
	const META_KEY = '_rwai_schema_jsonld';

	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'maybe_print_schema' ), 30 );
	}

	/**
	 * Legacy entry point. Old callers (older Content Generator paths)
	 * passed a single $schema_type — we now build the full @graph and
	 * use the requested type as the primary node.
	 */
	public function build_and_save( $post_id, $schema_type, $content_html, $focus_keyword = '' ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return false;
		}
		if ( ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			return false;
		}
		$engine = new RankWriter_AI_Schema_Engine();
		$type   = $this->normalize_type( $schema_type );
		$engine->set_primary_type( $post_id, $type );
		return $engine->build_and_save( $post_id );
	}

	public function maybe_print_schema() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		// Allow other code to suppress (e.g. user has a 3rd-party plugin).
		if ( ! apply_filters( 'rwai_should_print_schema', true ) ) {
			return;
		}
		// If a major SEO plugin is active it already prints schema; avoid
		// duplicates — this is the no-conflict guarantee Google asks for.
		if ( class_exists( 'RankWriter_AI_SEO_Integration' ) ) {
			$seo = new RankWriter_AI_SEO_Integration();
			if ( in_array( $seo->detect_plugin(), array( 'rank-math', 'yoast', 'seopress' ), true ) ) {
				return;
			}
		}

		$post_id = get_the_ID();

		// Prefer the new @graph payload built by the Schema Engine.
		if ( class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			$engine  = new RankWriter_AI_Schema_Engine();
			$payload = $engine->get_saved_graph( $post_id );
			if ( empty( $payload['@graph'] ) ) {
				$payload = $engine->build_graph( $post_id );
			}
			if ( ! empty( $payload['@graph'] ) ) {
				echo "\n<!-- RankWriter AI Schema Engine -->\n";
				echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
				return;
			}
		}

		// Fallback: legacy single-schema payload from older posts.
		$legacy = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! empty( $legacy ) && is_array( $legacy ) ) {
			echo "\n<!-- RankWriter AI JSON-LD -->\n";
			echo '<script type="application/ld+json">' . wp_json_encode( $legacy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	private function normalize_type( $type ) {
		$type = trim( (string) $type );
		$allowed = array_keys( RankWriter_AI_Schema_Engine::available_primary_types() );
		$allowed[] = RankWriter_AI_Schema_Engine::TYPE_FAQ;
		foreach ( $allowed as $a ) {
			if ( 0 === strcasecmp( $a, $type ) ) {
				return $a;
			}
		}
		return RankWriter_AI_Schema_Engine::TYPE_ARTICLE;
	}
}
