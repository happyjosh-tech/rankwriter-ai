<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator. Wires modules into WordPress hooks.
 */
class RankWriter_AI {

	public function run() {

		$profiles = new RankWriter_AI_Category_Profiles();
		$profiles->register_hooks();

		add_action( 'rwai_scheduled_blog_analysis', array( $this, 'cron_run_analysis' ) );

		$autopilot = new RankWriter_AI_Autopilot();
		$autopilot->register_hooks();

		$schema_injector = new RankWriter_AI_Schema_Injector();
		$schema_injector->register_hooks();

		if ( class_exists( 'RankWriter_AI_PSE_Queue' ) ) {
			$pse_queue = new RankWriter_AI_PSE_Queue();
			$pse_queue->register_hooks();
		}
		if ( is_admin() && class_exists( 'RankWriter_AI_PSE_DB' ) ) {
			RankWriter_AI_PSE_DB::maybe_upgrade();
		}

		if ( class_exists( 'RankWriter_AI_Gap_Detector' ) ) {
			$gap = new RankWriter_AI_Gap_Detector();
			$gap->register_hooks();
			$gap->schedule_recurring();
		}

		if ( class_exists( 'RankWriter_AI_Content_Refresher' ) ) {
			$refresher = new RankWriter_AI_Content_Refresher();
			$refresher->register_hooks();
			$s = $refresher->get_settings();
			if ( ! empty( $s['enabled'] ) ) {
				$refresher->schedule_recurring();
			}
		}

		// Soft DB upgrade for the refresher log table on every admin load.
		if ( is_admin() && class_exists( 'RankWriter_AI_Refresher_DB' ) ) {
			RankWriter_AI_Refresher_DB::maybe_upgrade();
		}
		if ( is_admin() && class_exists( 'RankWriter_AI_Syndication_DB' ) ) {
			RankWriter_AI_Syndication_DB::maybe_upgrade();
		}

		if ( class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			$seasonal = new RankWriter_AI_Seasonal_Engine();
			$seasonal->register_hooks();
			$seasonal->schedule_recurring();
		}

		if ( class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			( new RankWriter_AI_Voice_Memory() )->register_hooks();
		}

		if ( class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			$healer = new RankWriter_AI_SEO_Healer();
			$healer->register_hooks();
			$s = $healer->get_settings();
			if ( ! empty( $s['enabled'] ) ) {
				$healer->schedule_recurring();
			}
		}
		if ( is_admin() && class_exists( 'RankWriter_AI_SEO_Healer_DB' ) ) {
			RankWriter_AI_SEO_Healer_DB::maybe_upgrade();
		}

		if ( class_exists( 'RankWriter_AI_Pinterest_Scheduler' ) ) {
			$pin_sched = new RankWriter_AI_Pinterest_Scheduler();
			$pin_sched->register_hooks();
			$pin_sched->schedule_recurring();
		}

		// Multi-language: hreflang + lang attribute on the frontend.
		if ( class_exists( 'RankWriter_AI_Language' ) ) {
			RankWriter_AI_Language::register_hooks();
		}

		// RankWriter Site Speed Optimizer.
		if ( class_exists( 'RankWriter_AI_Speed_Optimizer' ) ) {
			( new RankWriter_AI_Speed_Optimizer() )->register_hooks();
		}

		// Auto-translate on publish (single deferred cron 5 min after publish).
		add_action( 'save_post_post', array( $this, 'maybe_queue_auto_translate' ), 25, 3 );
		add_action( 'rwai_auto_translate_run', array( $this, 'cron_auto_translate' ), 10, 2 );
		if ( is_admin() && class_exists( 'RankWriter_AI_Pinterest_DB' ) ) {
			RankWriter_AI_Pinterest_DB::maybe_upgrade();
		}

		// Run a soft DB upgrade check on every load so cluster tables exist
		// even when the user updates the plugin without re-running activation.
		if ( is_admin() && class_exists( 'RankWriter_AI_Clusters_DB' ) ) {
			RankWriter_AI_Clusters_DB::maybe_upgrade();
		}

		// Auto-link posts to matching cluster topics on save.
		add_action( 'save_post_post', array( $this, 'cluster_link_on_save' ), 20, 3 );
		// Clean up cluster topic linkage when a post is deleted.
		add_action( 'before_delete_post', array( $this, 'cluster_unlink_on_delete' ) );

		load_plugin_textdomain( 'rankwriter-ai', false, dirname( RWAI_PLUGIN_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			$admin = new RankWriter_AI_Admin();
			$admin->register_hooks();
		}
	}

	/**
	 * When a post is saved, attach it to a matching cluster topic if one
	 * exists. Two paths:
	 *
	 *  1. The generator wrote `_rwai_cluster_topic_id` post-meta when
	 *     building the post — we already know the topic id.
	 *  2. Otherwise: fuzzy-match the post title against suggested topics.
	 */
	public function cluster_link_on_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'pending' !== $post->post_status && 'draft' !== $post->post_status ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Cluster_Manager' ) ) {
			return;
		}

		$manager  = new RankWriter_AI_Cluster_Manager();
		$topic_id = (int) get_post_meta( $post_id, RankWriter_AI_Cluster_Manager::META_TOPIC_ID, true );

		if ( $topic_id ) {
			$manager->update_topic( $topic_id, array(
				'post_id' => $post_id,
				'status'  => 'published' === $post->post_status ? 'published' : 'queued',
			) );
			return;
		}

		// No explicit linkage — try an exact-title match against suggested
		// topics. Cheap early-out if no clusters exist at all.
		if ( $manager->count_all() === 0 ) {
			return;
		}
		foreach ( $manager->get_all( array( 'limit' => 50 ) ) as $cluster ) {
			$topics = $manager->get_topics( $cluster['id'] );
			foreach ( $topics as $t ) {
				if ( ! empty( $t['post_id'] ) ) {
					continue;
				}
				$pt = strtolower( wp_strip_all_tags( $post->post_title ) );
				$tt = strtolower( $t['topic'] );
				if ( $pt === $tt ) {
					$manager->update_topic( $t['id'], array(
						'post_id' => $post_id,
						'status'  => 'published' === $post->post_status ? 'published' : 'queued',
					) );
					update_post_meta( $post_id, RankWriter_AI_Cluster_Manager::META_TOPIC_ID, $t['id'] );
					return;
				}
			}
		}
	}

	/**
	 * When a post is published, defer an auto-translate event 5 minutes out
	 * (keeps the publish click instant). The cron handler iterates every
	 * enabled language other than the post's own and creates a draft.
	 */
	public function maybe_queue_auto_translate( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( (int) RankWriter_AI_Helpers::get_setting( 'auto_translate_on_publish', 0 ) !== 1 ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Language' ) ) {
			return;
		}
		$source_lang = RankWriter_AI_Language::get_post_language( $post_id );
		$targets     = array();
		foreach ( RankWriter_AI_Language::enabled_codes() as $code ) {
			if ( $code !== $source_lang ) {
				$targets[] = $code;
			}
		}
		if ( empty( $targets ) ) {
			return;
		}
		if ( wp_next_scheduled( 'rwai_auto_translate_run', array( $post_id, $targets ) ) ) {
			return;
		}
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'rwai_auto_translate_run', array( $post_id, $targets ) );
	}

	public function cron_auto_translate( $post_id, $targets ) {
		if ( ! class_exists( 'RankWriter_AI_Translator' ) ) {
			return;
		}
		$translator = new RankWriter_AI_Translator();
		foreach ( (array) $targets as $code ) {
			$translator->translate_post( $post_id, $code );
		}
	}

	public function cluster_unlink_on_delete( $post_id ) {
		if ( ! class_exists( 'RankWriter_AI_Cluster_Manager' ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			RankWriter_AI_Clusters_DB::topics_table(),
			array( 'post_id' => null, 'status' => 'suggested' ),
			array( 'post_id' => absint( $post_id ) )
		);
		$wpdb->update(
			RankWriter_AI_Clusters_DB::clusters_table(),
			array( 'pillar_post_id' => null ),
			array( 'pillar_post_id' => absint( $post_id ) )
		);
	}

	public function cron_run_analysis() {
		$analyzer = new RankWriter_AI_Blog_Analyzer();
		$signals  = $analyzer->analyze();
		$profile  = new RankWriter_AI_Style_Profile();
		$profile->build_and_save( $signals );
	}
}
