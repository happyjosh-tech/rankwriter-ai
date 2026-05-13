<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankWriter_AI_Admin {

	const MENU_SLUG       = 'rankwriter-ai';
	const PROFILES_SLUG   = 'rankwriter-ai-profiles';
	const ANALYZER_SLUG   = 'rankwriter-ai-analyzer';
	const RESEARCH_SLUG   = 'rankwriter-ai-research';
	const AUTOPILOT_SLUG  = 'rankwriter-ai-autopilot';
	const GENERATE_SLUG   = 'rankwriter-ai-generate';
	const CLUSTERS_SLUG   = 'rankwriter-ai-clusters';
	const PSE_SLUG        = 'rankwriter-ai-pse';
	const PINTEREST_SLUG  = 'rankwriter-ai-pinterest';
	const TRANSLATIONS_SLUG = 'rankwriter-ai-translations';
	const HUMANIZER_SLUG  = 'rankwriter-ai-humanizer';
	const TITLE_LAB_SLUG  = 'rankwriter-ai-title-lab';
	const DISCOVER_SLUG   = 'rankwriter-ai-discover';
	const GAP_SLUG        = 'rankwriter-ai-gap-detector';
	const FACT_SLUG       = 'rankwriter-ai-fact-checker';
	const REFRESH_SLUG    = 'rankwriter-ai-refresher';
	const SCHEMA_SLUG     = 'rankwriter-ai-schema';
	const SEASONAL_SLUG   = 'rankwriter-ai-seasonal';
	const VOICE_SLUG      = 'rankwriter-ai-voice';
	const PARASITE_SLUG   = 'rankwriter-ai-parasite';
	const RISK_SLUG       = 'rankwriter-ai-risk';
	const HEALER_SLUG     = 'rankwriter-ai-healer';
	const LEGAL_SLUG      = 'rankwriter-ai-legal';
	const SETTINGS_SLUG   = 'rankwriter-ai-settings';

	const SETTINGS_NONCE  = 'rwai_save_settings';
	const ANALYZER_NONCE  = 'rwai_run_analysis';
	const DEEP_NONCE      = 'rwai_deep_analysis';
	const GENERATE_NONCE  = 'rwai_generate_article';
	const DELETE_NONCE    = 'rwai_delete_profile';
	const RESEARCH_NONCE  = 'rwai_research_keywords';
	const AUTOPILOT_NONCE = 'rwai_autopilot';
	const LEGAL_NONCE     = 'rwai_legal_pages';
	const AI_SUGGEST_NONCE = 'rwai_ai_suggest';
	const CLUSTER_NONCE   = 'rwai_clusters';
	const TITLE_NONCE     = 'rwai_titles';
	const DISCOVER_NONCE  = 'rwai_discover';
	const PSE_NONCE       = 'rwai_pse';
	const PINTEREST_NONCE = 'rwai_pinterest';
	const TRANSLATION_NONCE = 'rwai_translation';
	const HUMANIZER_NONCE = 'rwai_humanizer';
	const GAP_NONCE       = 'rwai_gap';
	const FACT_NONCE      = 'rwai_fact';
	const REFRESH_NONCE   = 'rwai_refresh';
	const SCHEMA_NONCE    = 'rwai_schema';
	const SEASONAL_NONCE  = 'rwai_seasonal';
	const VOICE_NONCE     = 'rwai_voice';
	const PARASITE_NONCE  = 'rwai_parasite';
	const RISK_NONCE      = 'rwai_risk';
	const HEALER_NONCE    = 'rwai_healer';

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'handle_post_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_rwai_run_analysis', array( $this, 'ajax_run_analysis' ) );
		add_action( 'wp_ajax_rwai_deep_analysis', array( $this, 'ajax_run_deep_analysis' ) );
		add_action( 'wp_ajax_rwai_ai_suggest', array( $this, 'ajax_ai_suggest' ) );
		add_action( 'wp_ajax_rwai_title_generate', array( $this, 'ajax_title_generate' ) );
		add_action( 'wp_ajax_rwai_title_analyze',  array( $this, 'ajax_title_analyze' ) );
		add_action( 'wp_ajax_rwai_title_compare',  array( $this, 'ajax_title_compare' ) );
		add_action( 'wp_ajax_rwai_title_swap',     array( $this, 'ajax_title_swap' ) );
		add_action( 'wp_ajax_rwai_discover_score_post',    array( $this, 'ajax_discover_score_post' ) );
		add_action( 'wp_ajax_rwai_discover_score_content', array( $this, 'ajax_discover_score_content' ) );
		add_action( 'wp_ajax_rwai_discover_hooks',         array( $this, 'ajax_discover_hooks' ) );
		add_action( 'wp_ajax_rwai_humanize_analyze', array( $this, 'ajax_humanize_analyze' ) );
		add_action( 'wp_ajax_rwai_humanize_rewrite', array( $this, 'ajax_humanize_rewrite' ) );

		add_action( 'admin_notices', array( $this, 'maybe_render_compliance_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_seasonal_notice' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_compliance_meta_box' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_translations_meta_box' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_freshness_meta_box' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_schema_meta_box' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_parasite_meta_box' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_risk_meta_box' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_risk_publish_warning' ) );

		add_filter( 'plugin_action_links_' . RWAI_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	public function register_menus() {
		add_menu_page(
			__( 'RankWriter AI', 'rankwriter-ai' ),
			__( 'RankWriter AI', 'rankwriter-ai' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-edit-large',
			30
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'rankwriter-ai' ),
			__( 'Dashboard', 'rankwriter-ai' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Category Profiles', 'rankwriter-ai' ),
			__( 'Category Profiles', 'rankwriter-ai' ),
			'manage_options',
			self::PROFILES_SLUG,
			array( $this, 'render_profiles' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Blog Analyzer', 'rankwriter-ai' ),
			__( 'Blog Analyzer', 'rankwriter-ai' ),
			'manage_options',
			self::ANALYZER_SLUG,
			array( $this, 'render_analyzer' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Keyword Research', 'rankwriter-ai' ),
			__( 'Keyword Research', 'rankwriter-ai' ),
			'manage_options',
			self::RESEARCH_SLUG,
			array( $this, 'render_research' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Autopilot', 'rankwriter-ai' ),
			__( 'Autopilot', 'rankwriter-ai' ),
			'manage_options',
			self::AUTOPILOT_SLUG,
			array( $this, 'render_autopilot' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Generate Article', 'rankwriter-ai' ),
			__( 'Generate Article', 'rankwriter-ai' ),
			'edit_posts',
			self::GENERATE_SLUG,
			array( $this, 'render_generate' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Clusters', 'rankwriter-ai' ),
			__( 'Clusters', 'rankwriter-ai' ),
			'manage_options',
			self::CLUSTERS_SLUG,
			array( $this, 'render_clusters' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Programmatic SEO', 'rankwriter-ai' ),
			__( 'Programmatic SEO', 'rankwriter-ai' ),
			'manage_options',
			self::PSE_SLUG,
			array( $this, 'render_pse' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Pinterest', 'rankwriter-ai' ),
			__( 'Pinterest', 'rankwriter-ai' ),
			'edit_posts',
			self::PINTEREST_SLUG,
			array( $this, 'render_pinterest' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Translations', 'rankwriter-ai' ),
			__( 'Translations', 'rankwriter-ai' ),
			'edit_posts',
			self::TRANSLATIONS_SLUG,
			array( $this, 'render_translations' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Humanization Lab', 'rankwriter-ai' ),
			__( 'Humanization Lab', 'rankwriter-ai' ),
			'edit_posts',
			self::HUMANIZER_SLUG,
			array( $this, 'render_humanizer' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Brand Voice', 'rankwriter-ai' ),
			__( 'Brand Voice', 'rankwriter-ai' ),
			'manage_options',
			self::VOICE_SLUG,
			array( $this, 'render_voice' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Parasite SEO', 'rankwriter-ai' ),
			__( 'Parasite SEO', 'rankwriter-ai' ),
			'edit_posts',
			self::PARASITE_SLUG,
			array( $this, 'render_parasite' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Title Lab', 'rankwriter-ai' ),
			__( 'Title Lab', 'rankwriter-ai' ),
			'edit_posts',
			self::TITLE_LAB_SLUG,
			array( $this, 'render_title_lab' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Discover Optimizer', 'rankwriter-ai' ),
			__( 'Discover Optimizer', 'rankwriter-ai' ),
			'edit_posts',
			self::DISCOVER_SLUG,
			array( $this, 'render_discover' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Gap Detector', 'rankwriter-ai' ),
			__( 'Gap Detector', 'rankwriter-ai' ),
			'manage_options',
			self::GAP_SLUG,
			array( $this, 'render_gap_detector' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Fact Checker', 'rankwriter-ai' ),
			__( 'Fact Checker', 'rankwriter-ai' ),
			'manage_options',
			self::FACT_SLUG,
			array( $this, 'render_fact_checker' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Risk Detector', 'rankwriter-ai' ),
			__( 'Risk Detector', 'rankwriter-ai' ),
			'manage_options',
			self::RISK_SLUG,
			array( $this, 'render_risk' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'SEO Healer', 'rankwriter-ai' ),
			__( 'SEO Healer', 'rankwriter-ai' ),
			'manage_options',
			self::HEALER_SLUG,
			array( $this, 'render_healer' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Auto Refresh', 'rankwriter-ai' ),
			__( 'Auto Refresh', 'rankwriter-ai' ),
			'manage_options',
			self::REFRESH_SLUG,
			array( $this, 'render_refresher' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Schema', 'rankwriter-ai' ),
			__( 'Schema', 'rankwriter-ai' ),
			'manage_options',
			self::SCHEMA_SLUG,
			array( $this, 'render_schema' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Seasonal Trends', 'rankwriter-ai' ),
			__( 'Seasonal Trends', 'rankwriter-ai' ),
			'manage_options',
			self::SEASONAL_SLUG,
			array( $this, 'render_seasonal' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Legal Pages', 'rankwriter-ai' ),
			__( 'Legal Pages', 'rankwriter-ai' ),
			'manage_options',
			self::LEGAL_SLUG,
			array( $this, 'render_legal' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'rankwriter-ai' ),
			__( 'Settings', 'rankwriter-ai' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rankwriter-ai' ) ) {
			return;
		}
		wp_enqueue_style( 'rwai-admin', RWAI_PLUGIN_URL . 'admin/css/admin.css', array(), RWAI_VERSION );
		wp_enqueue_script( 'rwai-admin', RWAI_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), RWAI_VERSION, true );
		wp_localize_script(
			'rwai-admin',
			'RWAI',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'analysisNonce'  => wp_create_nonce( self::ANALYZER_NONCE ),
				'deepNonce'      => wp_create_nonce( self::DEEP_NONCE ),
				'aiSuggestNonce' => wp_create_nonce( self::AI_SUGGEST_NONCE ),
				'titleNonce'     => wp_create_nonce( self::TITLE_NONCE ),
				'discoverNonce'  => wp_create_nonce( self::DISCOVER_NONCE ),
				'humanizerNonce' => wp_create_nonce( self::HUMANIZER_NONCE ),
				'i18n'           => array(
					'running'     => __( 'Analyzing your blog…', 'rankwriter-ai' ),
					'done'        => __( 'Analysis complete.', 'rankwriter-ai' ),
					'failed'      => __( 'Analysis failed.', 'rankwriter-ai' ),
					'deepRunning' => __( 'Asking Claude to read 8 sample posts…', 'rankwriter-ai' ),
					'deepDone'    => __( 'Deep analysis saved.', 'rankwriter-ai' ),
					'aiFill'      => __( 'AI fill', 'rankwriter-ai' ),
					'aiThinking'  => __( 'Asking Claude…', 'rankwriter-ai' ),
					'aiFailed'    => __( 'AI fill failed.', 'rankwriter-ai' ),
					'titleGen'      => __( 'Generating 15 title variants…', 'rankwriter-ai' ),
					'titleFail'     => __( 'Title generation failed.', 'rankwriter-ai' ),
					'discoverScore' => __( 'Scoring for Discover…', 'rankwriter-ai' ),
					'discoverHooks' => __( 'Asking Claude for 4 scroll-stopping hooks…', 'rankwriter-ai' ),
					'discoverFail'  => __( 'Discover scoring failed.', 'rankwriter-ai' ),
					'humanizeAnalyze' => __( 'Scanning for AI tells…', 'rankwriter-ai' ),
					'humanizeRewrite' => __( 'Humanizing — one moment…', 'rankwriter-ai' ),
					'humanizeFail'    => __( 'Humanization failed.', 'rankwriter-ai' ),
					'humanizeDone'    => __( 'Done.', 'rankwriter-ai' ),
				),
			)
		);
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( self::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'rankwriter-ai' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function handle_post_actions() {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! isset( $_POST['rwai_action'] ) ) {
			return;
		}
		$editor_actions = array( 'generate_article', 'schema_save_post_meta', 'parasite_generate', 'parasite_mark_published', 'parasite_delete' );
		if ( ! current_user_can( 'manage_options' ) && ! ( in_array( $_POST['rwai_action'], $editor_actions, true ) && current_user_can( 'edit_posts' ) ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rwai_action'] ) );

		switch ( $action ) {
			case 'save_settings':
				$this->handle_save_settings();
				break;
			case 'save_profile':
				$this->handle_save_profile();
				break;
			case 'delete_profile':
				$this->handle_delete_profile();
				break;
			case 'restore_presets':
				$this->handle_restore_presets();
				break;
			case 'run_analysis':
				$this->handle_run_analysis();
				break;
			case 'generate_article':
				$this->handle_generate_article();
				break;
			case 'run_keyword_research':
				$this->handle_run_keyword_research();
				break;
			case 'save_autopilot':
				$this->handle_save_autopilot();
				break;
			case 'refill_autopilot_queue':
				$this->handle_refill_autopilot();
				break;
			case 'clear_autopilot_queue':
				$this->handle_clear_autopilot();
				break;
			case 'save_legal_settings':
				$this->handle_save_legal_settings();
				break;
			case 'generate_legal_page':
				$this->handle_generate_legal_page();
				break;
			case 'generate_all_legal_pages':
				$this->handle_generate_all_legal();
				break;
			case 'save_cluster':
				$this->handle_save_cluster();
				break;
			case 'delete_cluster':
				$this->handle_delete_cluster();
				break;
			case 'suggest_topics':
				$this->handle_suggest_cluster_topics();
				break;
			case 'add_cluster_topic':
				$this->handle_add_cluster_topic();
				break;
			case 'delete_cluster_topic':
				$this->handle_delete_cluster_topic();
				break;
			case 'skip_cluster_topic':
				$this->handle_skip_cluster_topic();
				break;
			case 'generate_cluster_topic':
				$this->handle_generate_cluster_topic();
				break;
			case 'generate_cluster_keywords':
				$this->handle_generate_cluster_keywords();
				break;
			case 'auto_match_posts':
				$this->handle_auto_match_cluster_posts();
				break;
			case 'suggest_clusters_from_blog':
				$this->handle_suggest_clusters_from_blog();
				break;
			case 'pse_save_template':
				$this->handle_pse_save_template();
				break;
			case 'pse_delete_template':
				$this->handle_pse_delete_template();
				break;
			case 'pse_import_rows':
				$this->handle_pse_import_rows();
				break;
			case 'pse_generate_row':
				$this->handle_pse_generate_row();
				break;
			case 'pse_delete_row':
				$this->handle_pse_delete_row();
				break;
			case 'pse_save_queue':
				$this->handle_pse_save_queue();
				break;
			case 'pse_run_now':
				$this->handle_pse_run_now();
				break;
			case 'pse_restore_presets':
				$this->handle_pse_restore_presets();
				break;
			case 'pin_generate_post':
				$this->handle_pin_generate_post();
				break;
			case 'pin_save':
				$this->handle_pin_save();
				break;
			case 'pin_delete':
				$this->handle_pin_delete();
				break;
			case 'pin_render_image':
				$this->handle_pin_render_image();
				break;
			case 'translate_post':
				$this->handle_translate_post();
				break;
			case 'gap_run_audit':
				$this->handle_gap_run_audit();
				break;
			case 'fact_check_post':
				$this->handle_fact_check_post();
				break;
			case 'save_refresher_settings':
				$this->handle_save_refresher_settings();
				break;
			case 'refresh_post_now':
				$this->handle_refresh_post_now();
				break;
			case 'schema_save_org':
				$this->handle_schema_save_org();
				break;
			case 'schema_rebuild_all':
				$this->handle_schema_rebuild_all();
				break;
			case 'schema_save_post_meta':
				$this->handle_schema_save_post_meta();
				break;
			case 'seasonal_refresh_coverage':
				$this->handle_seasonal_refresh_coverage();
				break;
			case 'seasonal_dismiss':
				$this->handle_seasonal_dismiss();
				break;
			case 'voice_save_brand':
				$this->handle_voice_save_brand();
				break;
			case 'voice_apply_preset':
				$this->handle_voice_apply_preset();
				break;
			case 'voice_calibrate':
				$this->handle_voice_calibrate();
				break;
			case 'voice_save_category':
				$this->handle_voice_save_category();
				break;
			case 'voice_reset':
				$this->handle_voice_reset();
				break;
			case 'parasite_generate':
				$this->handle_parasite_generate();
				break;
			case 'parasite_mark_published':
				$this->handle_parasite_mark_published();
				break;
			case 'parasite_delete':
				$this->handle_parasite_delete();
				break;
			case 'risk_scan_post':
				$this->handle_risk_scan_post();
				break;
			case 'risk_bulk_rescan':
				$this->handle_risk_bulk_rescan();
				break;
			case 'healer_save_settings':
				$this->handle_healer_save_settings();
				break;
			case 'healer_scan_now':
				$this->handle_healer_scan_now();
				break;
			case 'healer_fix_issue':
				$this->handle_healer_fix_issue();
				break;
			case 'healer_rollback':
				$this->handle_healer_rollback();
				break;
			case 'healer_replace_broken_link':
				$this->handle_healer_replace_broken_link();
				break;
			case 'healer_delete_broken_link':
				$this->handle_healer_delete_broken_link();
				break;
		}
	}

	private function valid_or_default( $value, array $valid, $default ) {
		$value = strtolower( (string) $value );
		return in_array( $value, $valid, true ) ? $value : $default;
	}

	private function sanitize_enabled_languages( array $raw ) {
		if ( ! class_exists( 'RankWriter_AI_Language' ) ) {
			return 'en';
		}
		$valid  = array_keys( RankWriter_AI_Language::languages() );
		$codes  = array();
		foreach ( $raw as $c ) {
			$c = strtolower( trim( (string) $c ) );
			if ( in_array( $c, $valid, true ) ) {
				$codes[] = $c;
			}
		}
		if ( ! in_array( 'en', $codes, true ) ) {
			array_unshift( $codes, 'en' );
		}
		return implode( ',', array_values( array_unique( $codes ) ) );
	}

	private function handle_save_settings() {
		check_admin_referer( self::SETTINGS_NONCE );

		$raw = isset( $_POST['rwai_settings'] ) ? (array) wp_unslash( $_POST['rwai_settings'] ) : array();
		$values = array(
			'claude_api_key'      => isset( $raw['claude_api_key'] ) ? sanitize_text_field( $raw['claude_api_key'] ) : '',
			'claude_model'        => isset( $raw['claude_model'] ) ? sanitize_text_field( $raw['claude_model'] ) : 'claude-opus-4-7',
			'max_tokens'          => isset( $raw['max_tokens'] ) ? absint( $raw['max_tokens'] ) : 8000,
			'analyze_post_limit'  => isset( $raw['analyze_post_limit'] ) ? absint( $raw['analyze_post_limit'] ) : 200,
			'default_image_style' => isset( $raw['default_image_style'] ) ? sanitize_text_field( $raw['default_image_style'] ) : 'realistic',
			'default_word_count'  => isset( $raw['default_word_count'] ) ? absint( $raw['default_word_count'] ) : 1500,
			'humanize_pass'       => ! empty( $raw['humanize_pass'] ) ? 1 : 0,
			'default_country'     => isset( $raw['default_country'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $raw['default_country'] ), 0, 2 ) ) : 'US',
			'serpapi_key'         => isset( $raw['serpapi_key'] ) ? sanitize_text_field( $raw['serpapi_key'] ) : '',
			'dataforseo_login'    => isset( $raw['dataforseo_login'] ) ? sanitize_text_field( $raw['dataforseo_login'] ) : '',
			'dataforseo_password' => isset( $raw['dataforseo_password'] ) ? sanitize_text_field( $raw['dataforseo_password'] ) : '',
			'competitor_domains'  => isset( $raw['competitor_domains'] ) ? sanitize_textarea_field( $raw['competitor_domains'] ) : '',
			'pexels_api_key'      => isset( $raw['pexels_api_key'] ) ? sanitize_text_field( $raw['pexels_api_key'] ) : '',
			'unsplash_access_key' => isset( $raw['unsplash_access_key'] ) ? sanitize_text_field( $raw['unsplash_access_key'] ) : '',
			'pinterest_auto_generate'      => ! empty( $raw['pinterest_auto_generate'] ) ? 1 : 0,
			'pinterest_pins_per_post'      => isset( $raw['pinterest_pins_per_post'] ) ? max( 1, min( 5, (int) $raw['pinterest_pins_per_post'] ) ) : 3,
			'pinterest_auto_render_images' => ! empty( $raw['pinterest_auto_render_images'] ) ? 1 : 0,
			'pinterest_font_path'          => isset( $raw['pinterest_font_path'] ) ? sanitize_text_field( $raw['pinterest_font_path'] ) : '',
			'enabled_languages'         => $this->sanitize_enabled_languages( isset( $raw['enabled_languages'] ) ? (array) $raw['enabled_languages'] : array() ),
			'auto_translate_on_publish' => ! empty( $raw['auto_translate_on_publish'] ) ? 1 : 0,
			'humanize_strength'    => $this->valid_or_default( $raw['humanize_strength'] ?? '', class_exists( 'RankWriter_AI_Humanizer' ) ? array_keys( RankWriter_AI_Humanizer::strengths() ) : array(), 'medium' ),
			'humanize_tone'        => $this->valid_or_default( $raw['humanize_tone'] ?? '', class_exists( 'RankWriter_AI_Humanizer' ) ? array_keys( RankWriter_AI_Humanizer::tones() ) : array(), 'professional' ),
			'humanize_personality' => $this->valid_or_default( $raw['humanize_personality'] ?? '', class_exists( 'RankWriter_AI_Humanizer' ) ? array_keys( RankWriter_AI_Humanizer::personalities() ) : array(), 'experienced_practitioner' ),
			'humanize_readability' => $this->valid_or_default( $raw['humanize_readability'] ?? '', class_exists( 'RankWriter_AI_Humanizer' ) ? array_keys( RankWriter_AI_Humanizer::readability_modes() ) : array(), 'off' ),
		);

		if ( $values['max_tokens'] < 2000 ) {
			$values['max_tokens'] = 2000;
		}
		if ( $values['max_tokens'] > 64000 ) {
			$values['max_tokens'] = 64000;
		}
		if ( $values['analyze_post_limit'] < 10 ) {
			$values['analyze_post_limit'] = 10;
		}

		RankWriter_AI_Helpers::update_settings( $values );

		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::SETTINGS_SLUG, array( 'rwai_msg' => 'settings-saved' ) ) );
		exit;
	}

	private function handle_save_profile() {
		$profiles = new RankWriter_AI_Category_Profiles();
		$result   = $profiles->save_from_request( wp_unslash( $_POST ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PROFILES_SLUG, array( 'rwai_msg' => 'profile-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PROFILES_SLUG, array( 'rwai_msg' => 'profile-saved', 'profile_id' => $result ) ) );
		exit;
	}

	private function handle_delete_profile() {
		check_admin_referer( self::DELETE_NONCE );
		$id = isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0;
		$profiles = new RankWriter_AI_Category_Profiles();
		$res = $profiles->delete( $id );
		$msg = is_wp_error( $res ) ? 'profile-error' : 'profile-deleted';
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PROFILES_SLUG, array( 'rwai_msg' => $msg ) ) );
		exit;
	}

	private function handle_restore_presets() {
		$nonce_key = RankWriter_AI_Category_Profiles::NONCE_KEY;
		$nonce_act = RankWriter_AI_Category_Profiles::NONCE_ACT;
		if ( empty( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( $_POST[ $nonce_key ], $nonce_act ) ) {
			wp_die( esc_html__( 'Security check failed.', 'rankwriter-ai' ) );
		}
		$profiles = new RankWriter_AI_Category_Profiles();
		$created  = $profiles->seed_presets( false );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PROFILES_SLUG, array(
			'rwai_msg' => 'presets-restored',
			'count'    => (int) $created,
		) ) );
		exit;
	}

	private function handle_run_analysis() {
		check_admin_referer( self::ANALYZER_NONCE );
		$this->run_analysis_pipeline();
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::ANALYZER_SLUG, array( 'rwai_msg' => 'analysis-done' ) ) );
		exit;
	}

	private function run_analysis_pipeline() {
		$analyzer = new RankWriter_AI_Blog_Analyzer();
		$signals  = $analyzer->analyze();
		$style    = new RankWriter_AI_Style_Profile();
		return $style->build_and_save( $signals );
	}

	public function ajax_run_analysis() {
		check_ajax_referer( self::ANALYZER_NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$profile = $this->run_analysis_pipeline();
		wp_send_json_success(
			array(
				'summary'      => isset( $profile['summary'] ) ? $profile['summary'] : '',
				'generated_at' => isset( $profile['generated_at'] ) ? $profile['generated_at'] : '',
			)
		);
	}

	public function maybe_render_compliance_notice() {
		global $pagenow, $post;
		if ( 'post.php' !== $pagenow || ! $post || 'post' !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$compliance = new RankWriter_AI_Compliance();
		$report     = $compliance->get_report( $post->ID );
		if ( empty( $report ) || empty( $report['issues'] ) ) {
			return;
		}
		$errors = 0; $warns = 0;
		foreach ( $report['issues'] as $i ) {
			if ( 'error' === $i['severity'] ) { $errors++; } else { $warns++; }
		}
		$class = $errors > 0 ? 'notice-error' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>' . esc_html__( 'RankWriter AI compliance check', 'rankwriter-ai' ) . '</strong> — ';
		echo esc_html( sprintf( _n( '%d error', '%d errors', $errors, 'rankwriter-ai' ), $errors ) ) . ', ';
		echo esc_html( sprintf( _n( '%d warning', '%d warnings', $warns, 'rankwriter-ai' ), $warns ) );
		echo '. ' . esc_html__( 'See the RankWriter AI panel in the sidebar for details.', 'rankwriter-ai' ) . '</p></div>';
	}

	public function register_translations_meta_box( $post ) {
		if ( ! $post || ! class_exists( 'RankWriter_AI_Language' ) ) {
			return;
		}
		// Only if multi-language is meaningfully enabled (>1 language).
		$enabled = RankWriter_AI_Language::enabled_codes();
		if ( count( $enabled ) < 2 ) {
			return;
		}
		add_meta_box(
			'rwai_translations_box',
			__( 'RankWriter AI — Translations', 'rankwriter-ai' ),
			array( $this, 'render_translations_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_translations_meta_box( $post ) {
		$current_lang = RankWriter_AI_Language::get_post_language( $post->ID );
		$current_cfg  = RankWriter_AI_Language::language( $current_lang );
		$translations = RankWriter_AI_Language::get_translations( $post->ID );
		$existing     = array();
		foreach ( $translations as $t ) {
			$existing[ $t['lang'] ] = $t;
		}
		$enabled = RankWriter_AI_Language::enabled_codes();
		$all     = RankWriter_AI_Language::languages();

		echo '<p><strong>' . esc_html__( 'This post:', 'rankwriter-ai' ) . '</strong> ';
		echo esc_html( $current_cfg ? $current_cfg['name'] . ' (' . $current_cfg['native'] . ')' : strtoupper( $current_lang ) );
		echo '</p>';

		echo '<p><strong>' . esc_html__( 'Existing translations:', 'rankwriter-ai' ) . '</strong></p><ul style="margin:4px 0;padding-left:18px;">';
		foreach ( $translations as $t ) {
			$is_self = $t['post_id'] === $post->ID;
			$cls = 'publish' === $t['status'] ? 'rwai-pill-ok' : 'rwai-pill-warn';
			echo '<li>';
			echo '<span class="rwai-pill ' . esc_attr( $cls ) . '">' . esc_html( strtoupper( $t['lang'] ) ) . '</span> ';
			if ( $is_self ) {
				echo '<em>' . esc_html__( '(this post)', 'rankwriter-ai' ) . '</em>';
			} else {
				echo '<a href="' . esc_url( get_edit_post_link( $t['post_id'] ) ) . '">' . esc_html( wp_trim_words( $t['title'], 8 ) ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';

		$missing = array();
		foreach ( $enabled as $code ) {
			if ( ! isset( $existing[ $code ] ) ) {
				$missing[] = $code;
			}
		}
		if ( empty( $missing ) ) {
			echo '<p class="rwai-muted">' . esc_html__( 'All enabled languages present.', 'rankwriter-ai' ) . '</p>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::TRANSLATIONS_SLUG ) ) . '">';
		echo '<input type="hidden" name="rwai_action" value="translate_post" />';
		echo '<input type="hidden" name="post_id" value="' . esc_attr( $post->ID ) . '" />';
		wp_nonce_field( self::TRANSLATION_NONCE );
		echo '<p><strong>' . esc_html__( 'Generate missing translations:', 'rankwriter-ai' ) . '</strong></p>';
		foreach ( $missing as $code ) {
			$cfg = $all[ $code ] ?? null;
			if ( ! $cfg ) { continue; }
			echo '<label style="display:block;margin:3px 0;">';
			echo '<input type="checkbox" name="targets[]" value="' . esc_attr( $code ) . '" /> ';
			echo esc_html( $cfg['name'] . ' (' . $cfg['native'] . ')' );
			echo '</label>';
		}
		echo '<p><button type="submit" class="button button-primary button-small">' . esc_html__( '✨ Translate selected', 'rankwriter-ai' ) . '</button></p>';
		echo '</form>';
	}

	public function register_compliance_meta_box( $post ) {
		if ( ! $post || ! get_post_meta( $post->ID, '_rwai_generated', true ) ) {
			return;
		}
		add_meta_box(
			'rwai_compliance_box',
			__( 'RankWriter AI — Compliance Report', 'rankwriter-ai' ),
			array( $this, 'render_compliance_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	public function render_compliance_meta_box( $post ) {
		$compliance = new RankWriter_AI_Compliance();
		$report     = $compliance->get_report( $post->ID );
		if ( empty( $report ) ) {
			echo '<p>' . esc_html__( 'No RankWriter AI report on this post.', 'rankwriter-ai' ) . '</p>';
			return;
		}
		$stats  = isset( $report['stats'] ) ? $report['stats'] : array();
		$issues = isset( $report['issues'] ) ? $report['issues'] : array();
		echo '<p><strong>' . esc_html__( 'Status:', 'rankwriter-ai' ) . '</strong> ';
		echo ! empty( $report['passed'] )
			? '<span class="rwai-pill rwai-pill-ok">' . esc_html__( 'Passed', 'rankwriter-ai' ) . '</span>'
			: '<span class="rwai-pill rwai-pill-bad">' . esc_html__( 'Issues found', 'rankwriter-ai' ) . '</span>';
		echo '</p>';

		if ( ! empty( $stats ) ) {
			echo '<dl class="rwai-dl">';
			echo '<dt>' . esc_html__( 'Words', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['word_count'] ) . '</dd>';
			echo '<dt>' . esc_html__( 'H2 / H3', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['h2_count'] . ' / ' . $stats['h3_count'] ) . '</dd>';
			echo '<dt>' . esc_html__( 'Lists', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['list_count'] ) . '</dd>';
			echo '<dt>' . esc_html__( 'Images', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['image_count'] ) . '</dd>';
			echo '<dt>' . esc_html__( 'Links', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['link_count'] ) . '</dd>';
			echo '<dt>' . esc_html__( 'Avg paragraph', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $stats['paragraph_avg'] . ' words' ) . '</dd>';

			$intent     = (string) get_post_meta( $post->ID, '_rwai_intent', true );
			$intent_pct = (int) get_post_meta( $post->ID, '_rwai_intent_confidence', true );
			if ( $intent ) {
				$intent_label = class_exists( 'RankWriter_AI_Intent_Detector' ) ? RankWriter_AI_Intent_Detector::label( $intent ) : ucfirst( $intent );
				echo '<dt>' . esc_html__( 'Search intent', 'rankwriter-ai' ) . '</dt>';
				echo '<dd><span class="rwai-intent-badge rwai-intent-' . esc_attr( $intent ) . '">' . esc_html( $intent_label ) . '</span>';
				if ( $intent_pct ) {
					echo ' <small class="rwai-muted">' . esc_html( $intent_pct . '%' ) . '</small>';
				}
				echo '</dd>';
			}

			$cpc_tier   = (string) get_post_meta( $post->ID, '_rwai_cpc_tier', true );
			$cpc_value  = (float) get_post_meta( $post->ID, '_rwai_cpc_estimated_usd', true );
			$rpm_value  = (float) get_post_meta( $post->ID, '_rwai_rpm_estimated_usd', true );
			$mscore     = (int) get_post_meta( $post->ID, '_rwai_monetization_score', true );
			$priority   = (int) get_post_meta( $post->ID, '_rwai_cpc_priority', true );
			if ( $cpc_tier && class_exists( 'RankWriter_AI_CPC_Scorer' ) ) {
				echo '<dt>' . esc_html__( 'CPC tier', 'rankwriter-ai' ) . '</dt>';
				echo '<dd><span class="rwai-cpc-badge rwai-cpc-' . esc_attr( $cpc_tier ) . '">' . esc_html( RankWriter_AI_CPC_Scorer::tier_label( $cpc_tier ) ) . '</span>';
				if ( $priority ) {
					echo ' <span class="rwai-priority-star" title="' . esc_attr__( 'Priority high-value niche', 'rankwriter-ai' ) . '">★</span>';
				}
				echo '</dd>';
				if ( $cpc_value > 0 ) {
					echo '<dt>' . esc_html__( 'Estimated CPC', 'rankwriter-ai' ) . '</dt><dd>$' . esc_html( number_format( $cpc_value, 2 ) ) . '</dd>';
				}
				if ( $rpm_value > 0 ) {
					echo '<dt>' . esc_html__( 'Predicted RPM', 'rankwriter-ai' ) . '</dt><dd>$' . esc_html( number_format( $rpm_value, 0 ) ) . ' / 1k visits</dd>';
				}
				if ( $mscore > 0 ) {
					echo '<dt>' . esc_html__( 'Monetization score', 'rankwriter-ai' ) . '</dt><dd>' . esc_html( $mscore ) . '/100</dd>';
				}
			}
			echo '</dl>';
		}

		if ( ! empty( $issues ) ) {
			echo '<h4>' . esc_html__( 'Issues', 'rankwriter-ai' ) . '</h4><ul>';
			foreach ( $issues as $i ) {
				$cls = 'error' === $i['severity'] ? 'rwai-pill-bad' : 'rwai-pill-warn';
				echo '<li><span class="rwai-pill ' . esc_attr( $cls ) . '">' . esc_html( $i['severity'] ) . '</span> ';
				echo '<strong>' . esc_html( $i['rule'] ) . '</strong> — ' . esc_html( $i['message'] );
				if ( ! empty( $i['hits'] ) ) {
					echo '<br><small>' . esc_html__( 'Matches:', 'rankwriter-ai' ) . ' ' . esc_html( implode( ', ', $i['hits'] ) ) . '</small>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		// Title Intelligence: live score for the current title + generate
		// alternative variants + one-click swap.
		if ( class_exists( 'RankWriter_AI_Title_Intelligence' ) ) {
			$engine    = new RankWriter_AI_Title_Intelligence();
			$analysis  = $engine->analyze( $post->post_title );
			$overall   = (int) $analysis['overall_score'];
			$band      = $overall >= 75 ? 'ok' : ( $overall >= 50 ? 'warn' : 'bad' );
			echo '<hr>';
			echo '<h4>' . esc_html__( 'Title intelligence', 'rankwriter-ai' ) . '</h4>';
			echo '<p><strong>' . esc_html__( 'Overall CTR score:', 'rankwriter-ai' ) . '</strong> ';
			echo '<span class="rwai-pill rwai-pill-' . esc_attr( $band ) . '">' . esc_html( $overall ) . '/100</span> ';
			echo '<small class="rwai-muted">(' . esc_html( $analysis['length'] ) . ' chars)</small></p>';
			if ( ! empty( $analysis['emotional_triggers'] ) ) {
				echo '<p><strong>' . esc_html__( 'Triggers:', 'rankwriter-ai' ) . '</strong> ' . esc_html( implode( ', ', $analysis['emotional_triggers'] ) ) . '</p>';
			}
			if ( ! empty( $analysis['clickbait']['is_clickbait'] ) ) {
				echo '<p class="rwai-pill rwai-pill-bad">' . esc_html__( 'Clickbait risk detected — consider rewriting.', 'rankwriter-ai' ) . '</p>';
			}
			echo '<p>';
			echo '<button type="button" class="button button-small rwai-title-swap-trigger" data-post-id="' . esc_attr( $post->ID ) . '" data-topic="' . esc_attr( $post->post_title ) . '">' . esc_html__( '✨ Generate alternative titles', 'rankwriter-ai' ) . '</button>';
			echo '</p>';
			echo '<div class="rwai-title-swap-panel" id="rwai-title-swap-' . esc_attr( $post->ID ) . '" style="display:none;"></div>';
		}

		// Discover optimization — 4-dimension score.
		if ( class_exists( 'RankWriter_AI_Discover_Optimizer' ) ) {
			$d = ( new RankWriter_AI_Discover_Optimizer() )->score_post( $post->ID );
			echo '<hr>';
			echo '<h4>' . esc_html__( 'Google Discover readiness', 'rankwriter-ai' ) . '</h4>';
			echo '<div class="rwai-do-mini">';
			echo '<div class="rwai-do-mini-overall rwai-do-band-' . esc_attr( $d['band'] ) . '">' . esc_html( $d['overall'] ) . '<small>/100</small></div>';
			$dims = array(
				'mobile_engagement'    => __( 'Mobile',    'rankwriter-ai' ),
				'freshness'            => __( 'Freshness', 'rankwriter-ai' ),
				'emotional_engagement' => __( 'Emotion',   'rankwriter-ai' ),
				'image_readiness'      => __( 'Image',     'rankwriter-ai' ),
			);
			echo '<div class="rwai-do-mini-bars">';
			foreach ( $dims as $key => $label ) {
				$s = (int) $d[ $key ]['score'];
				$band = $s >= 75 ? 'ok' : ( $s >= 50 ? 'warn' : 'bad' );
				echo '<div class="rwai-do-mini-row">';
				echo '<span class="rwai-do-mini-label">' . esc_html( $label ) . '</span>';
				echo '<span class="rwai-do-mini-track"><span class="rwai-do-mini-fill rwai-tl-bar-' . esc_attr( $band ) . '" style="width:' . esc_attr( $s ) . '%"></span></span>';
				echo '<span class="rwai-do-mini-score">' . esc_html( $s ) . '</span>';
				echo '</div>';
			}
			echo '</div></div>';
			if ( ! empty( $d['recommendations'] ) ) {
				echo '<ul class="rwai-do-recos">';
				foreach ( array_slice( $d['recommendations'], 0, 3 ) as $rec ) {
					echo '<li>' . esc_html( $rec ) . '</li>';
				}
				echo '</ul>';
			}
			echo '<p><a class="button button-small" href="' . esc_url( RankWriter_AI_Helpers::admin_url( self::DISCOVER_SLUG ) ) . '">' . esc_html__( 'Open Discover Optimizer', 'rankwriter-ai' ) . '</a></p>';
		}
	}

	public function ajax_ai_suggest() {
		check_ajax_referer( self::AI_SUGGEST_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}

		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
		$field   = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$payload = isset( $_POST['payload'] ) && is_array( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : array();

		// Sanitize each payload value individually (preserve newlines for textareas).
		$clean_payload = array();
		foreach ( $payload as $k => $v ) {
			$k = sanitize_key( $k );
			$clean_payload[ $k ] = sanitize_textarea_field( (string) $v );
		}

		$suggester = new RankWriter_AI_AI_Suggester();
		$result    = $suggester->suggest( $context, $field, $clean_payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'value' => (string) $result ) );
	}

	public function ajax_run_deep_analysis() {
		check_ajax_referer( self::DEEP_NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$analyzer = new RankWriter_AI_Blog_Analyzer();
		$result   = $analyzer->run_claude_deep_analysis();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'analysis' => (string) $result ) );
	}

	private function handle_run_keyword_research() {
		check_admin_referer( self::RESEARCH_NONCE );
		$seed    = isset( $_POST['seed'] ) ? sanitize_text_field( wp_unslash( $_POST['seed'] ) ) : '';
		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'US';

		$research = new RankWriter_AI_Keyword_Research();
		$comp     = $this->competitor_list();
		$result   = $research->discover( $seed, $country, $comp );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RESEARCH_SLUG, array(
				'rwai_msg' => 'research-error',
				'rwai_err' => rawurlencode( $result->get_error_message() ),
				'seed'     => rawurlencode( $seed ),
				'country'  => rawurlencode( $country ),
			) ) );
			exit;
		}

		set_transient( 'rwai_last_research_' . get_current_user_id(), $result, HOUR_IN_SECONDS );

		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RESEARCH_SLUG, array(
			'rwai_msg' => 'research-done',
			'seed'     => rawurlencode( $seed ),
			'country'  => rawurlencode( $country ),
		) ) );
		exit;
	}

	private function competitor_list() {
		$raw = (string) RankWriter_AI_Helpers::get_setting( 'competitor_domains', '' );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d ) {
				$out[] = $d;
			}
		}
		return $out;
	}

	private function handle_save_autopilot() {
		check_admin_referer( self::AUTOPILOT_NONCE );

		$picker_raw = isset( $_POST['wp_category_id'] ) ? trim( (string) wp_unslash( $_POST['wp_category_id'] ) ) : '';
		$wp_cat_id  = 0;
		$wp_cat_new = '';
		if ( '__new__' === $picker_raw ) {
			$wp_cat_new = isset( $_POST['wp_category_id_new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_category_id_new_name'] ) ) : '';
			if ( '' !== $wp_cat_new ) {
				$existing = get_term_by( 'name', $wp_cat_new, 'category' );
				if ( $existing && ! is_wp_error( $existing ) ) {
					$wp_cat_id  = (int) $existing->term_id;
					$wp_cat_new = '';
				} else {
					$created = wp_insert_term( $wp_cat_new, 'category' );
					if ( ! is_wp_error( $created ) ) {
						$wp_cat_id  = (int) $created['term_id'];
						$wp_cat_new = '';
					}
				}
			}
		} else {
			$wp_cat_id = absint( $picker_raw );
		}

		$pilot = new RankWriter_AI_Autopilot();
		$pilot->save_config( array(
			'enabled'         => ! empty( $_POST['enabled'] ),
			'profile_id'      => isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0,
			'seed_keywords'   => isset( $_POST['seed_keywords'] ) ? wp_unslash( $_POST['seed_keywords'] ) : '',
			'frequency'       => isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) : 'daily',
			'max_per_run'     => isset( $_POST['max_per_run'] ) ? absint( $_POST['max_per_run'] ) : 1,
			'post_status'     => isset( $_POST['post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['post_status'] ) ) : 'draft',
			'country'         => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'US',
			'word_count'      => isset( $_POST['word_count'] ) ? absint( $_POST['word_count'] ) : 0,
			'auto_seo'        => ! empty( $_POST['auto_seo'] ),
			'max_tags'        => isset( $_POST['max_tags'] ) ? absint( $_POST['max_tags'] ) : 2,
			'wp_category_id'  => $wp_cat_id,
			'run_time'        => isset( $_POST['run_time'] ) ? sanitize_text_field( wp_unslash( $_POST['run_time'] ) ) : '09:00',
			'run_day_of_week' => isset( $_POST['run_day_of_week'] ) ? absint( $_POST['run_day_of_week'] ) : 1,
		) );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::AUTOPILOT_SLUG, array( 'rwai_msg' => 'autopilot-saved' ) ) );
		exit;
	}

	private function handle_refill_autopilot() {
		check_admin_referer( self::AUTOPILOT_NONCE );
		$pilot = new RankWriter_AI_Autopilot();
		$res = $pilot->refill_queue();
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::AUTOPILOT_SLUG, array(
				'rwai_msg' => 'autopilot-error',
				'rwai_err' => rawurlencode( $res->get_error_message() ),
			) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::AUTOPILOT_SLUG, array( 'rwai_msg' => 'autopilot-refilled' ) ) );
		exit;
	}

	private function handle_save_legal_settings() {
		check_admin_referer( self::LEGAL_NONCE );
		$legal = new RankWriter_AI_Legal_Pages();
		$legal->save_settings( array(
			'business_name'        => isset( $_POST['business_name'] ) ? wp_unslash( $_POST['business_name'] ) : '',
			'business_email'       => isset( $_POST['business_email'] ) ? wp_unslash( $_POST['business_email'] ) : '',
			'business_address'     => isset( $_POST['business_address'] ) ? wp_unslash( $_POST['business_address'] ) : '',
			'legal_jurisdiction'   => isset( $_POST['legal_jurisdiction'] ) ? wp_unslash( $_POST['legal_jurisdiction'] ) : '',
			'operator_type'        => isset( $_POST['operator_type'] ) ? wp_unslash( $_POST['operator_type'] ) : 'individual',
			'uses_adsense'         => ! empty( $_POST['uses_adsense'] ),
			'uses_affiliate_links' => ! empty( $_POST['uses_affiliate_links'] ),
			'uses_cookies'         => ! empty( $_POST['uses_cookies'] ),
			'uses_analytics'       => ! empty( $_POST['uses_analytics'] ),
		) );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::LEGAL_SLUG, array( 'rwai_msg' => 'legal-saved' ) ) );
		exit;
	}

	private function handle_generate_legal_page() {
		check_admin_referer( self::LEGAL_NONCE );
		$type  = isset( $_POST['legal_type'] ) ? sanitize_key( wp_unslash( $_POST['legal_type'] ) ) : '';
		$legal = new RankWriter_AI_Legal_Pages();
		$res   = $legal->generate( $type );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::LEGAL_SLUG, array(
				'rwai_msg' => 'legal-error',
				'rwai_err' => rawurlencode( $res->get_error_message() ),
			) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::LEGAL_SLUG, array( 'rwai_msg' => 'legal-generated' ) ) );
		exit;
	}

	private function handle_generate_all_legal() {
		check_admin_referer( self::LEGAL_NONCE );
		$legal = new RankWriter_AI_Legal_Pages();
		$errors = array();
		foreach ( array_keys( RankWriter_AI_Legal_Pages::page_types() ) as $type ) {
			$r = $legal->generate( $type );
			if ( is_wp_error( $r ) && 'rwai_disabled' !== $r->get_error_code() ) {
				$errors[] = $type . ': ' . $r->get_error_message();
			}
		}
		$args = array( 'rwai_msg' => 'legal-all-generated' );
		if ( ! empty( $errors ) ) {
			$args['rwai_msg'] = 'legal-error';
			$args['rwai_err'] = rawurlencode( implode( ' | ', $errors ) );
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::LEGAL_SLUG, $args ) );
		exit;
	}

	private function handle_clear_autopilot() {
		check_admin_referer( self::AUTOPILOT_NONCE );
		$pilot = new RankWriter_AI_Autopilot();
		$pilot->clear_queue();
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::AUTOPILOT_SLUG, array( 'rwai_msg' => 'autopilot-cleared' ) ) );
		exit;
	}

	private function handle_generate_article() {
		check_admin_referer( self::GENERATE_NONCE );

		// Resolve the WP-category picker: dropdown either submits a term ID
		// or the literal "__new__" + a sibling "wp_category_id_new_name".
		$picker_raw = isset( $_POST['wp_category_id'] ) ? trim( (string) wp_unslash( $_POST['wp_category_id'] ) ) : '';
		$override_id  = 0;
		$override_new = '';
		if ( '__new__' === $picker_raw ) {
			$override_new = isset( $_POST['wp_category_id_new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_category_id_new_name'] ) ) : '';
		} else {
			$override_id = absint( $picker_raw );
		}

		$gen   = new RankWriter_AI_Content_Generator();
		$post_id = $gen->generate(
			array(
				'profile_id'               => isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0,
				'topic'                    => isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '',
				'word_count'               => isset( $_POST['word_count'] ) ? absint( $_POST['word_count'] ) : 0,
				'extra_context'            => isset( $_POST['extra_context'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra_context'] ) ) : '',
				'override_wp_category_id'  => $override_id,
				'override_wp_category_new' => $override_new,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::GENERATE_SLUG, array( 'rwai_msg' => 'generate-error', 'rwai_err' => rawurlencode( $post_id->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
		exit;
	}

	/* ---------------- Page renderers ---------------- */

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$profiles  = new RankWriter_AI_Category_Profiles();
		$style     = new RankWriter_AI_Style_Profile();
		$client    = new RankWriter_AI_Claude_Client();

		// Blog-wide monetization snapshot: score the 20 most recent post
		// titles to get a rough RPM / CPC potential for the site as it
		// stands today.
		$cpc_dashboard = array();
		if ( class_exists( 'RankWriter_AI_CPC_Scorer' ) ) {
			$default_country = (string) RankWriter_AI_Helpers::get_setting( 'default_country', 'US' );
			$recent          = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );
			$kw_list = array();
			foreach ( $recent as $rp ) {
				$kw_list[] = $rp->post_title;
			}
			if ( ! empty( $kw_list ) ) {
				$scorer        = new RankWriter_AI_CPC_Scorer();
				$rows          = $scorer->score_bulk( $kw_list, $default_country );
				$cpc_dashboard = $scorer->summarize( $rows );
				$cpc_dashboard['country'] = $default_country;
			}
		}

		$data = array(
			'profile_count' => $profiles->count(),
			'style_profile' => $style->get(),
			'last_run'      => $style->last_run(),
			'api_ready'     => $client->is_configured(),
			'cpc_dashboard' => $cpc_dashboard,
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/dashboard.php';
	}

	public function render_profiles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$profiles = new RankWriter_AI_Category_Profiles();
		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$creating = isset( $_GET['new'] );
		$data = array(
			'profiles' => $profiles->get_all(),
			'schema'   => RankWriter_AI_Category_Profiles::field_schema(),
			'editing'  => $edit_id ? $profiles->get( $edit_id ) : null,
			'creating' => $creating,
			'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/category-profiles.php';
	}

	public function render_analyzer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$style = new RankWriter_AI_Style_Profile();
		$data = array(
			'style_profile' => $style->get(),
			'last_run'      => $style->last_run(),
			'msg'           => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/blog-analyzer.php';
	}

	public function render_generate() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$profiles = new RankWriter_AI_Category_Profiles();
		$style    = new RankWriter_AI_Style_Profile();
		$client   = new RankWriter_AI_Claude_Client();
		$data = array(
			'profiles'  => $profiles->get_all(),
			'style'     => $style->get(),
			'api_ready' => $client->is_configured(),
			'msg'       => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'       => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/generate-article.php';
	}

	public function render_research() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$research = new RankWriter_AI_Keyword_Research();
		$seed     = isset( $_GET['seed'] ) ? sanitize_text_field( wp_unslash( $_GET['seed'] ) ) : '';
		$country  = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : (string) RankWriter_AI_Helpers::get_setting( 'default_country', 'US' );
		$result   = get_transient( 'rwai_last_research_' . get_current_user_id() );
		$data = array(
			'result'  => is_array( $result ) ? $result : array(),
			'seed'    => $seed,
			'country' => $country,
			'pool'    => $research->get_pool(),
			'msg'     => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'     => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/keyword-research.php';
	}

	public function render_autopilot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$pilot    = new RankWriter_AI_Autopilot();
		$profiles = new RankWriter_AI_Category_Profiles();
		$data = array(
			'config'   => $pilot->get_config(),
			'profiles' => $profiles->get_all(),
			'queue'    => $pilot->get_queue(),
			'log'      => $pilot->get_log( 30 ),
			'next_run' => $pilot->next_run(),
			'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/autopilot.php';
	}

	/* ---------------- Cluster handlers ---------------- */

	private function require_cluster_nonce() {
		check_admin_referer( self::CLUSTER_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'rankwriter-ai' ) );
		}
	}

	private function redirect_cluster( $args = array() ) {
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::CLUSTERS_SLUG, $args ) );
		exit;
	}

	private function redirect_cluster_edit( $cluster_id, $args = array() ) {
		$args = array_merge( array( 'cluster' => (int) $cluster_id ), $args );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::CLUSTERS_SLUG, $args ) );
		exit;
	}

	private function handle_save_cluster() {
		$this->require_cluster_nonce();
		$manager = new RankWriter_AI_Cluster_Manager();
		$id      = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$payload = array(
			'name'                    => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'description'             => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'pillar_post_id'          => isset( $_POST['pillar_post_id'] ) ? absint( $_POST['pillar_post_id'] ) : 0,
			'profile_id'              => isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0,
			'target_supporting_count' => isset( $_POST['target_supporting_count'] ) ? absint( $_POST['target_supporting_count'] ) : 6,
			'semantic_keywords'       => isset( $_POST['semantic_keywords'] ) ? wp_unslash( $_POST['semantic_keywords'] ) : '',
		);
		if ( $id ) {
			$res = $manager->update( $id, $payload );
		} else {
			$res = $manager->create( $payload );
			if ( ! is_wp_error( $res ) ) {
				$id = (int) $res;
			}
		}
		if ( is_wp_error( $res ) ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( $res->get_error_message() ) ) );
		}
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-saved' ) );
	}

	private function handle_delete_cluster() {
		$this->require_cluster_nonce();
		$id = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		( new RankWriter_AI_Cluster_Manager() )->delete( $id );
		$this->redirect_cluster( array( 'rwai_msg' => 'cluster-deleted' ) );
	}

	private function handle_suggest_cluster_topics() {
		$this->require_cluster_nonce();
		$id      = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$manager = new RankWriter_AI_Cluster_Manager();
		$cluster = $manager->get( $id, true );
		if ( ! $cluster ) {
			$this->redirect_cluster( array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( __( 'Cluster not found.', 'rankwriter-ai' ) ) ) );
		}

		$existing = array_map( function ( $t ) { return $t['topic']; }, $cluster['topics'] );
		$wanted   = max( 1, ( (int) $cluster['target_supporting_count'] ) - count( $existing ) ) + 3;

		$suggester = new RankWriter_AI_Cluster_Suggester();
		$topics    = $suggester->suggest_supporting_topics( $cluster['name'], $wanted, (int) $cluster['profile_id'], $existing );
		if ( is_wp_error( $topics ) ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( $topics->get_error_message() ) ) );
		}

		$added = 0;
		foreach ( (array) $topics as $t ) {
			$res = $manager->add_topic( $id, $t );
			if ( ! is_wp_error( $res ) ) {
				$added++;
			}
		}
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'topics-suggested', 'count' => $added ) );
	}

	private function handle_add_cluster_topic() {
		$this->require_cluster_nonce();
		$id    = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		if ( $id && '' !== $topic ) {
			( new RankWriter_AI_Cluster_Manager() )->add_topic( $id, $topic );
		}
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'topics-suggested', 'count' => 1 ) );
	}

	private function handle_delete_cluster_topic() {
		$this->require_cluster_nonce();
		$id       = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		if ( $topic_id ) {
			( new RankWriter_AI_Cluster_Manager() )->delete_topic( $topic_id );
		}
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-saved' ) );
	}

	private function handle_skip_cluster_topic() {
		$this->require_cluster_nonce();
		$id       = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		if ( $topic_id ) {
			( new RankWriter_AI_Cluster_Manager() )->update_topic( $topic_id, array( 'status' => 'skipped' ) );
		}
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-saved' ) );
	}

	private function handle_generate_cluster_topic() {
		$this->require_cluster_nonce();
		$id       = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		$manager  = new RankWriter_AI_Cluster_Manager();
		$cluster  = $manager->get( $id );
		$topic    = $manager->get_topic( $topic_id );
		if ( ! $cluster || ! $topic ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( __( 'Topic or cluster missing.', 'rankwriter-ai' ) ) ) );
		}
		if ( ! $cluster['profile_id'] ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( __( 'Pick a Category Profile on this cluster before generating articles.', 'rankwriter-ai' ) ) ) );
		}

		$manager->update_topic( $topic_id, array( 'status' => 'queued' ) );

		$generator = new RankWriter_AI_Content_Generator();
		$post_id   = $generator->generate( array(
			'profile_id'          => (int) $cluster['profile_id'],
			'topic'               => $topic['topic'],
			'desired_status'      => 'draft',
			'cluster_id'          => (int) $cluster['id'],
			'cluster_topic_id'    => (int) $topic_id,
		) );
		if ( is_wp_error( $post_id ) ) {
			$manager->update_topic( $topic_id, array( 'status' => 'suggested' ) );
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( $post_id->get_error_message() ) ) );
		}

		$manager->update_topic( $topic_id, array( 'post_id' => $post_id, 'status' => 'published' ) );
		update_post_meta( $post_id, RankWriter_AI_Cluster_Manager::META_TOPIC_ID, $topic_id );

		wp_safe_redirect( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
		exit;
	}

	private function handle_generate_cluster_keywords() {
		$this->require_cluster_nonce();
		$id      = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$manager = new RankWriter_AI_Cluster_Manager();
		$cluster = $manager->get( $id );
		if ( ! $cluster ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( __( 'Cluster not found.', 'rankwriter-ai' ) ) ) );
		}
		$suggester = new RankWriter_AI_Cluster_Suggester();
		$keywords  = $suggester->suggest_semantic_keywords( $cluster['name'], 20 );
		if ( is_wp_error( $keywords ) ) {
			$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( $keywords->get_error_message() ) ) );
		}
		$manager->update( $id, array( 'semantic_keywords' => $keywords ) );
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'keywords-generated' ) );
	}

	private function handle_auto_match_cluster_posts() {
		$this->require_cluster_nonce();
		$id = isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0;
		$analyzer = new RankWriter_AI_Cluster_Analyzer();
		$matched  = $analyzer->auto_match_posts( $id );
		$this->redirect_cluster_edit( $id, array( 'rwai_msg' => 'auto-matched', 'count' => (int) $matched ) );
	}

	private function handle_suggest_clusters_from_blog() {
		$this->require_cluster_nonce();
		$suggester = new RankWriter_AI_Cluster_Suggester();
		$proposals = $suggester->suggest_clusters_from_blog( 5 );
		if ( is_wp_error( $proposals ) ) {
			$this->redirect_cluster( array( 'rwai_msg' => 'cluster-error', 'rwai_err' => rawurlencode( $proposals->get_error_message() ) ) );
		}
		$manager = new RankWriter_AI_Cluster_Manager();
		$created = 0;
		foreach ( (array) $proposals as $proposal ) {
			$dupes = $manager->find_duplicate_clusters( $proposal['pillar'] );
			if ( ! empty( $dupes ) ) {
				continue; // Skip — cluster already exists for this topic.
			}
			$new_id = $manager->create( array(
				'name'                    => $proposal['pillar'],
				'target_supporting_count' => max( 5, count( $proposal['supporting'] ) ),
			) );
			if ( is_wp_error( $new_id ) ) {
				continue;
			}
			foreach ( (array) $proposal['supporting'] as $s ) {
				$manager->add_topic( $new_id, $s );
			}
			$created++;
		}
		$this->redirect_cluster( array( 'rwai_msg' => 'cluster-suggested', 'count' => $created ) );
	}

	public function render_clusters() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$manager = new RankWriter_AI_Cluster_Manager();
		$editing_id = isset( $_GET['cluster'] ) ? absint( $_GET['cluster'] ) : 0;
		$creating   = isset( $_GET['new'] );

		if ( $editing_id || $creating ) {
			$cluster = $editing_id ? $manager->get( $editing_id, true ) : null;
			if ( $editing_id && $cluster ) {
				$cluster['completion_score'] = $manager->completion_score( $cluster );
				$gaps = ( new RankWriter_AI_Cluster_Analyzer() )->find_topical_gaps( $editing_id );
				if ( is_wp_error( $gaps ) ) {
					$gaps = array();
				}
			} else {
				$gaps = array();
			}
			$profiles_obj = new RankWriter_AI_Category_Profiles();
			$data = array(
				'creating' => $creating || ! $cluster,
				'cluster'  => $cluster ?: array(),
				'profiles' => $profiles_obj->get_all(),
				'gaps'     => $gaps,
				'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
				'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
			);
			require RWAI_PLUGIN_DIR . 'admin/partials/cluster-edit.php';
			return;
		}

		$clusters = $manager->get_all();
		foreach ( $clusters as &$c ) {
			$topics             = $manager->get_topics( $c['id'] );
			$c['topic_count']   = count( $topics );
			$published          = 0;
			foreach ( $topics as $t ) {
				if ( 'published' === $t['status'] && ! empty( $t['post_id'] ) ) {
					$published++;
				}
			}
			$c['published_count'] = $published;
			$c['topics']          = $topics;
			$c['completion_score'] = $manager->completion_score( $c );
		}
		unset( $c );

		$data = array(
			'clusters' => $clusters,
			'total'    => $manager->count_all(),
			'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/clusters-list.php';
	}

	/* ---------------- Title Lab handlers ---------------- */

	public function render_title_lab() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$data = array(
			'msg' => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err' => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/title-lab.php';
	}

	public function ajax_title_generate() {
		check_ajax_referer( self::TITLE_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$topic    = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$intent   = isset( $_POST['intent'] ) ? sanitize_text_field( wp_unslash( $_POST['intent'] ) ) : '';
		$cpc_tier = isset( $_POST['cpc_tier'] ) ? sanitize_text_field( wp_unslash( $_POST['cpc_tier'] ) ) : '';
		$count    = isset( $_POST['count'] ) ? max( 1, min( 5, absint( $_POST['count'] ) ) ) : 3;

		$engine  = new RankWriter_AI_Title_Intelligence();
		$result  = $engine->generate_variants( $topic, $intent, $cpc_tier, $count );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array(
			'variants' => $result,
			'styles'   => RankWriter_AI_Title_Intelligence::styles(),
		) );
	}

	public function ajax_title_analyze() {
		check_ajax_referer( self::TITLE_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$engine = new RankWriter_AI_Title_Intelligence();
		wp_send_json_success( $engine->analyze( $title ) );
	}

	public function ajax_title_compare() {
		check_ajax_referer( self::TITLE_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$titles_raw = isset( $_POST['titles'] ) ? (array) wp_unslash( $_POST['titles'] ) : array();
		$titles     = array();
		foreach ( $titles_raw as $t ) {
			$t = sanitize_text_field( $t );
			if ( '' !== $t ) {
				$titles[] = $t;
			}
		}
		if ( count( $titles ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Provide at least 2 titles.', 'rankwriter-ai' ) ), 400 );
		}
		$engine = new RankWriter_AI_Title_Intelligence();
		$rows   = $engine->analyze_bulk( $titles );
		wp_send_json_success( array(
			'rows'   => $rows,
			'styles' => RankWriter_AI_Title_Intelligence::styles(),
		) );
	}

	public function ajax_title_swap() {
		check_ajax_referer( self::TITLE_NONCE, 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $post_id || '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Missing post ID or title.', 'rankwriter-ai' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$updated = wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => wp_slash( $title ),
		), true );
		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ), 500 );
		}
		// Keep SEO meta in sync — push the new title into Rank Math/Yoast/AIOSEO/SEOPress.
		if ( class_exists( 'RankWriter_AI_SEO_Integration' ) ) {
			$seo = new RankWriter_AI_SEO_Integration();
			$seo->write_meta( $post_id, array(
				'title'         => $title,
				'description'   => (string) get_post_field( 'post_excerpt', $post_id ),
				'focus_keyword' => (string) get_post_meta( $post_id, '_rwai_topic', true ),
				'og_title'      => $title,
				'og_description'=> (string) get_post_field( 'post_excerpt', $post_id ),
				'schema_type'   => 'Article',
			) );
		}
		wp_send_json_success( array( 'post_id' => $post_id, 'title' => $title ) );
	}

	/* ---------------- Discover Optimizer handlers ---------------- */

	public function render_discover() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$data = array(
			'msg' => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err' => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/discover-optimizer.php';
	}

	public function ajax_discover_score_post() {
		check_ajax_referer( self::DISCOVER_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing post ID.', 'rankwriter-ai' ) ), 400 );
		}
		$optimizer = new RankWriter_AI_Discover_Optimizer();
		wp_send_json_success( $optimizer->score_post( $post_id ) );
	}

	public function ajax_discover_score_content() {
		check_ajax_referer( self::DISCOVER_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$html   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$image  = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$optimizer = new RankWriter_AI_Discover_Optimizer();
		wp_send_json_success( $optimizer->score_content( $title, $html, $image, '' ) );
	}

	public function ajax_discover_hooks() {
		check_ajax_referer( self::DISCOVER_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$topic  = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$intent = isset( $_POST['intent'] ) ? sanitize_text_field( wp_unslash( $_POST['intent'] ) ) : '';
		$optimizer = new RankWriter_AI_Discover_Optimizer();
		$result    = $optimizer->recommend_hooks( $topic, $intent );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'hooks' => $result ) );
	}

	/* ---------------- Programmatic SEO handlers ---------------- */

	private function require_pse_nonce() {
		check_admin_referer( self::PSE_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'rankwriter-ai' ) );
		}
	}
	private function redirect_pse( $args = array() ) {
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PSE_SLUG, $args ) );
		exit;
	}
	private function redirect_pse_edit( $template_id, $args = array() ) {
		$args = array_merge( array( 'template' => (int) $template_id ), $args );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PSE_SLUG, $args ) );
		exit;
	}

	private function handle_pse_save_template() {
		$this->require_pse_nonce();
		$manager = new RankWriter_AI_PSE_Manager();
		$id      = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		$outline   = json_decode( isset( $_POST['outline_json'] )   ? (string) wp_unslash( $_POST['outline_json'] )   : '{}', true );
		$variables = json_decode( isset( $_POST['variables_json'] ) ? (string) wp_unslash( $_POST['variables_json'] ) : '{}', true );
		if ( ! is_array( $outline ) || ! is_array( $variables ) ) {
			$this->redirect_pse_edit( $id, array( 'rwai_msg' => 'pse-error', 'rwai_err' => rawurlencode( __( 'Outline / Variables must be valid JSON.', 'rankwriter-ai' ) ) ) );
		}

		$payload = array(
			'name'              => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'description'       => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'title_template'    => isset( $_POST['title_template'] ) ? wp_unslash( $_POST['title_template'] ) : '',
			'slug_template'     => isset( $_POST['slug_template'] ) ? wp_unslash( $_POST['slug_template'] ) : '',
			'intent'            => isset( $_POST['intent'] ) ? sanitize_text_field( wp_unslash( $_POST['intent'] ) ) : 'informational',
			'outline'           => $outline,
			'variables'         => $variables,
			'semantic_keywords' => isset( $_POST['semantic_keywords'] ) ? wp_unslash( $_POST['semantic_keywords'] ) : '',
			'profile_id'        => isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0,
			'cluster_id'        => isset( $_POST['cluster_id'] ) ? absint( $_POST['cluster_id'] ) : 0,
			'status'            => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'min_word_count'    => isset( $_POST['min_word_count'] ) ? absint( $_POST['min_word_count'] ) : 1400,
			'min_uniqueness'    => isset( $_POST['min_uniqueness'] ) ? absint( $_POST['min_uniqueness'] ) : 70,
		);

		if ( $id ) {
			$res = $manager->update_template( $id, $payload );
		} else {
			$res = $manager->create_template( $payload );
			if ( ! is_wp_error( $res ) ) { $id = (int) $res; }
		}
		if ( is_wp_error( $res ) ) {
			$this->redirect_pse_edit( $id, array( 'rwai_msg' => 'pse-error', 'rwai_err' => rawurlencode( $res->get_error_message() ) ) );
		}
		$this->redirect_pse_edit( $id, array( 'rwai_msg' => 'pse-saved' ) );
	}

	private function handle_pse_delete_template() {
		$this->require_pse_nonce();
		$id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		( new RankWriter_AI_PSE_Manager() )->delete_template( $id );
		$this->redirect_pse( array( 'rwai_msg' => 'pse-deleted' ) );
	}

	private function handle_pse_import_rows() {
		$this->require_pse_nonce();
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$csv         = isset( $_POST['csv'] ) ? (string) wp_unslash( $_POST['csv'] ) : '';
		if ( ! $template_id || '' === trim( $csv ) ) {
			$this->redirect_pse_edit( $template_id, array( 'rwai_msg' => 'pse-error', 'rwai_err' => rawurlencode( __( 'Paste a CSV first.', 'rankwriter-ai' ) ) ) );
		}
		$lines = preg_split( "/\r?\n/", trim( $csv ) );
		if ( count( $lines ) < 2 ) {
			$this->redirect_pse_edit( $template_id, array( 'rwai_msg' => 'pse-error', 'rwai_err' => rawurlencode( __( 'CSV needs a header row + at least one data row.', 'rankwriter-ai' ) ) ) );
		}
		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( function ( $h ) { return sanitize_key( trim( (string) $h ) ); }, $header );
		$rows   = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) { continue; }
			$values = str_getcsv( $line );
			$row    = array();
			foreach ( $header as $i => $key ) {
				if ( ! $key ) { continue; }
				$row[ $key ] = isset( $values[ $i ] ) ? trim( (string) $values[ $i ] ) : '';
			}
			// Auto-fill *-slug variables from their base if user didn't include them.
			$augmented = array();
			foreach ( $row as $k => $v ) {
				$augmented[ $k ] = $v;
				$slug_key        = $k . '-slug';
				if ( ! isset( $row[ $slug_key ] ) && ! isset( $header[ array_search( $slug_key, $header, true ) ] ) ) {
					$augmented[ $slug_key ] = sanitize_title( $v );
				}
			}
			$rows[] = $augmented;
		}
		$counts = ( new RankWriter_AI_PSE_Manager() )->add_rows_bulk( $template_id, $rows );
		$this->redirect_pse_edit( $template_id, array(
			'rwai_msg' => 'pse-imported',
			'inserted' => (int) $counts['inserted'],
			'skipped'  => (int) $counts['skipped_duplicates'],
		) );
	}

	private function handle_pse_generate_row() {
		$this->require_pse_nonce();
		$row_id      = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$engine      = new RankWriter_AI_PSE_Engine();
		$result      = $engine->generate_row( $row_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_pse_edit( $template_id, array( 'rwai_msg' => 'pse-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) );
		}
		$this->redirect_pse_edit( $template_id, array( 'rwai_msg' => 'pse-saved' ) );
	}

	private function handle_pse_delete_row() {
		$this->require_pse_nonce();
		$row_id      = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		( new RankWriter_AI_PSE_Manager() )->delete_row( $row_id );
		$this->redirect_pse_edit( $template_id, array( 'rwai_msg' => 'pse-saved' ) );
	}

	private function handle_pse_save_queue() {
		$this->require_pse_nonce();
		RankWriter_AI_PSE_Queue::save_config( array(
			'enabled'    => ! empty( $_POST['enabled'] ),
			'frequency'  => isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) : 'hourly',
			'batch_size' => isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 3,
		) );
		$this->redirect_pse( array( 'rwai_msg' => 'pse-saved' ) );
	}

	private function handle_pse_run_now() {
		$this->require_pse_nonce();
		$batch  = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 3;
		$result = ( new RankWriter_AI_PSE_Queue() )->run_batch( $batch );
		$this->redirect_pse( array(
			'rwai_msg'  => 'pse-batch-done',
			'generated' => (int) $result['generated'],
			'failed'    => (int) $result['failed'],
		) );
	}

	private function handle_pse_restore_presets() {
		$this->require_pse_nonce();
		$created = RankWriter_AI_PSE_Presets::seed( false );
		$this->redirect_pse( array( 'rwai_msg' => 'pse-restored', 'count' => (int) $created ) );
	}

	public function render_pse() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$manager      = new RankWriter_AI_PSE_Manager();
		$is_edit      = isset( $_GET['template'] ) || isset( $_GET['new'] );
		if ( $is_edit ) {
			$tid      = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
			$template = $tid ? $manager->get_template( $tid ) : null;
			$creating = ! $template;
			$rows     = $tid ? $manager->get_rows( $tid, array( 'limit' => 50 ) ) : array();
			$counts   = $tid ? $manager->template_row_counts( $tid ) : array( 'total' => 0, 'by_status' => array() );
			$profiles_obj = new RankWriter_AI_Category_Profiles();
			$clusters_mgr = class_exists( 'RankWriter_AI_Cluster_Manager' ) ? new RankWriter_AI_Cluster_Manager() : null;
			$data = array(
				'creating' => $creating,
				'template' => $template ?: array(),
				'profiles' => $profiles_obj->get_all(),
				'clusters' => $clusters_mgr ? $clusters_mgr->get_all() : array(),
				'rows'     => $rows,
				'counts'   => $counts,
				'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
				'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
			);
			require RWAI_PLUGIN_DIR . 'admin/partials/pse-template-edit.php';
			return;
		}

		$queue_obj = new RankWriter_AI_PSE_Queue();
		$data = array(
			'templates' => $manager->get_all_templates(),
			'stats'     => $manager->global_stats(),
			'queue_cfg' => RankWriter_AI_PSE_Queue::get_config(),
			'next_run'  => $queue_obj->next_run(),
			'msg'       => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'       => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/pse-templates-list.php';
	}

	/* ---------------- Pinterest handlers ---------------- */

	private function require_pin_nonce() {
		check_admin_referer( self::PINTEREST_NONCE );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'rankwriter-ai' ) );
		}
	}
	private function redirect_pin( $args = array() ) {
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PINTEREST_SLUG, $args ) );
		exit;
	}
	private function redirect_pin_edit( $pin_id, $args = array() ) {
		$args = array_merge( array( 'pin' => (int) $pin_id ), $args );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PINTEREST_SLUG, $args ) );
		exit;
	}

	private function handle_pin_generate_post() {
		$this->require_pin_nonce();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$niche   = isset( $_POST['niche'] ) ? sanitize_text_field( wp_unslash( $_POST['niche'] ) ) : '';
		$count   = isset( $_POST['count'] ) ? max( 1, min( 5, absint( $_POST['count'] ) ) ) : 3;
		if ( ! $post_id ) {
			$this->redirect_pin( array( 'rwai_msg' => 'pin-error', 'rwai_err' => rawurlencode( __( 'Pick a post first.', 'rankwriter-ai' ) ) ) );
		}
		$engine = new RankWriter_AI_Pinterest_Engine();
		$ids    = $engine->generate_for_post( $post_id, $count, $niche );
		if ( is_wp_error( $ids ) ) {
			$this->redirect_pin( array( 'rwai_msg' => 'pin-error', 'rwai_err' => rawurlencode( $ids->get_error_message() ) ) );
		}
		// Auto-render images for the new pins if GD is available + setting on.
		if ( RankWriter_AI_Pinterest_Scheduler::auto_render_images_enabled() && function_exists( 'imagecreatetruecolor' ) ) {
			$image = new RankWriter_AI_Pinterest_Image();
			foreach ( (array) $ids as $pid ) {
				$image->render_for_pin( $pid );
			}
		}
		$this->redirect_pin( array( 'rwai_msg' => 'pin-generated', 'count' => count( (array) $ids ) ) );
	}

	private function handle_pin_save() {
		$this->require_pin_nonce();
		$pin_id   = isset( $_POST['pin_id'] ) ? absint( $_POST['pin_id'] ) : 0;
		$hashtags = isset( $_POST['hashtags'] ) ? wp_unslash( $_POST['hashtags'] ) : '';
		$boards   = isset( $_POST['board_suggestions'] ) ? wp_unslash( $_POST['board_suggestions'] ) : '';

		$args = array(
			'title'             => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'overlay_text'      => isset( $_POST['overlay_text'] ) ? sanitize_text_field( wp_unslash( $_POST['overlay_text'] ) ) : '',
			'overlay_secondary' => isset( $_POST['overlay_secondary'] ) ? sanitize_text_field( wp_unslash( $_POST['overlay_secondary'] ) ) : '',
			'description'       => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'image_prompt'      => isset( $_POST['image_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['image_prompt'] ) ) : '',
			'niche'             => isset( $_POST['niche'] ) ? sanitize_text_field( wp_unslash( $_POST['niche'] ) ) : 'general',
			'status'            => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft',
			'pin_url'           => isset( $_POST['pin_url'] ) ? esc_url_raw( wp_unslash( $_POST['pin_url'] ) ) : '',
			'hashtags'          => array_filter( array_map( function ( $t ) { return ltrim( trim( $t ), '#' ); }, preg_split( "/\r?\n/", (string) $hashtags ) ) ),
			'board_suggestions' => array_filter( array_map( 'trim', preg_split( "/\r?\n/", (string) $boards ) ) ),
		);
		if ( ! empty( $_POST['scheduled_at'] ) ) {
			$sched = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ) );
			$ts    = strtotime( $sched );
			$args['scheduled_at'] = $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
		} else {
			$args['scheduled_at'] = null;
		}
		if ( 'posted' === $args['status'] ) {
			$args['posted_at'] = current_time( 'mysql' );
		}
		( new RankWriter_AI_Pinterest_Engine() )->update_pin( $pin_id, $args );
		$this->redirect_pin_edit( $pin_id, array( 'rwai_msg' => 'pin-saved' ) );
	}

	private function handle_pin_delete() {
		$this->require_pin_nonce();
		$pin_id = isset( $_POST['pin_id'] ) ? absint( $_POST['pin_id'] ) : 0;
		( new RankWriter_AI_Pinterest_Engine() )->delete_pin( $pin_id );
		$this->redirect_pin( array( 'rwai_msg' => 'pin-deleted' ) );
	}

	private function handle_pin_render_image() {
		$this->require_pin_nonce();
		$pin_id = isset( $_POST['pin_id'] ) ? absint( $_POST['pin_id'] ) : 0;
		$image  = new RankWriter_AI_Pinterest_Image();
		$result = $image->render_for_pin( $pin_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_pin_edit( $pin_id, array( 'rwai_msg' => 'pin-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) );
		}
		$this->redirect_pin_edit( $pin_id, array( 'rwai_msg' => 'pin-image' ) );
	}

	public function render_pinterest() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$engine = new RankWriter_AI_Pinterest_Engine();

		// Single-pin view.
		if ( ! empty( $_GET['pin'] ) ) {
			$pin = $engine->get_pin( (int) $_GET['pin'] );
			if ( ! $pin ) {
				$this->redirect_pin( array( 'rwai_msg' => 'pin-error', 'rwai_err' => rawurlencode( __( 'Pin not found.', 'rankwriter-ai' ) ) ) );
			}
			$data = array(
				'pin' => $pin,
				'msg' => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
				'err' => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
			);
			require RWAI_PLUGIN_DIR . 'admin/partials/pinterest-pin.php';
			return;
		}

		$data = array(
			'pins'  => $engine->get_pins( array( 'limit' => 50 ) ),
			'stats' => $engine->global_stats(),
			'msg'   => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'   => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/pinterest-list.php';
	}

	/* ---------------- Translation handlers ---------------- */

	private function handle_translate_post() {
		check_admin_referer( self::TRANSLATION_NONCE );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'rankwriter-ai' ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$targets = isset( $_POST['targets'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['targets'] ) ) : array();
		if ( ! $post_id || empty( $targets ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::TRANSLATIONS_SLUG, array( 'rwai_msg' => 'tr-error', 'rwai_err' => rawurlencode( __( 'Pick a post and at least one target language.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$translator = new RankWriter_AI_Translator();
		$results    = $translator->translate_post_to_many( $post_id, $targets );
		$errors     = array();
		foreach ( $results as $code => $r ) {
			if ( is_wp_error( $r ) ) {
				$errors[] = strtoupper( $code ) . ': ' . $r->get_error_message();
			}
		}
		$args = array( 'rwai_msg' => 'tr-saved' );
		if ( ! empty( $errors ) ) {
			$args = array( 'rwai_msg' => 'tr-error', 'rwai_err' => rawurlencode( implode( ' | ', $errors ) ) );
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::TRANSLATIONS_SLUG, $args ) );
		exit;
	}

	public function render_translations() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		// Build list of translation groups by scanning posts that have the group meta.
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT meta_value AS grp FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 200",
			RankWriter_AI_Language::META_GROUP
		), ARRAY_A );
		$groups = array();
		foreach ( (array) $rows as $r ) {
			$group_id = $r['grp'];
			$post_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE pm.meta_key = %s AND pm.meta_value = %s
				 AND p.post_status IN ('publish','draft','pending')
				 ORDER BY p.post_date DESC LIMIT 20",
				RankWriter_AI_Language::META_GROUP, $group_id
			) );
			if ( empty( $post_ids ) ) {
				continue;
			}
			$primary = null;
			$translations = array();
			foreach ( $post_ids as $pid ) {
				$lang  = RankWriter_AI_Language::get_post_language( $pid );
				$row   = array(
					'post_id' => (int) $pid,
					'lang'    => $lang,
					'title'   => get_the_title( $pid ),
				);
				$translations[] = $row;
				if ( 'en' === $lang && ! $primary ) {
					$primary = $row;
				}
			}
			if ( ! $primary ) {
				$primary = $translations[0];
			}
			$groups[] = array( 'group' => $group_id, 'primary' => $primary, 'translations' => $translations );
		}
		$data = array(
			'groups'  => $groups,
			'enabled' => RankWriter_AI_Language::enabled_codes(),
			'msg'     => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'     => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/translations.php';
	}

	/* ---------------- Humanization Lab ---------------- */

	public function render_humanizer() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$data = array(
			'msg' => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err' => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/humanization-lab.php';
	}

	public function ajax_humanize_analyze() {
		check_ajax_referer( self::HUMANIZER_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$content = wp_kses_post( $content );
		$result  = ( new RankWriter_AI_Humanizer() )->analyze_ai_tells( $content );
		wp_send_json_success( $result );
	}

	public function ajax_humanize_rewrite() {
		check_ajax_referer( self::HUMANIZER_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'rankwriter-ai' ) ), 403 );
		}
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		if ( strlen( $content ) < 200 ) {
			wp_send_json_error( array( 'message' => __( 'Content too short — at least 200 characters required.', 'rankwriter-ai' ) ), 400 );
		}
		$options = array(
			'strength'    => isset( $_POST['strength'] ) ? sanitize_text_field( wp_unslash( $_POST['strength'] ) ) : 'medium',
			'tone'        => isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'professional',
			'personality' => isset( $_POST['personality'] ) ? sanitize_text_field( wp_unslash( $_POST['personality'] ) ) : 'experienced_practitioner',
			'readability' => isset( $_POST['readability'] ) ? sanitize_text_field( wp_unslash( $_POST['readability'] ) ) : 'off',
		);
		$humanizer = new RankWriter_AI_Humanizer();
		$rewritten = $humanizer->humanize( $content, $options );
		if ( null === $rewritten ) {
			wp_send_json_error( array( 'message' => __( 'Humanization failed — check your Claude API key and try again.', 'rankwriter-ai' ) ), 500 );
		}
		$before = $humanizer->analyze_ai_tells( $content );
		$after  = $humanizer->analyze_ai_tells( $rewritten );
		wp_send_json_success( array(
			'before_score' => $before['score'],
			'after_score'  => $after['score'],
			'rewritten'    => $rewritten,
		) );
	}

	public function render_legal() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$legal = new RankWriter_AI_Legal_Pages();
		$data = array(
			'settings' => $legal->settings(),
			'pages'    => $legal->list_pages(),
			'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'      => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/legal-pages.php';
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = array(
			'settings' => RankWriter_AI_Helpers::get_settings(),
			'msg'      => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	private function handle_gap_run_audit() {
		check_admin_referer( self::GAP_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Gap_Detector' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::GAP_SLUG, array( 'rwai_msg' => 'gap-missing' ) ) );
			exit;
		}
		$detector = new RankWriter_AI_Gap_Detector();
		$detector->run_audit( true );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::GAP_SLUG, array( 'rwai_msg' => 'gap-audited' ) ) );
		exit;
	}

	public function render_gap_detector() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Gap_Detector' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Gap Detector', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Gap Detector module is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$detector = new RankWriter_AI_Gap_Detector();
		$audit    = $detector->get_last_audit();
		$data = array(
			'audit' => $audit,
			'msg'   => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/gap-detector.php';
	}

	/* ============================ Fact Checker ============================ */

	private function resolve_post_ref( $ref ) {
		$ref = trim( (string) $ref );
		if ( '' === $ref ) { return 0; }
		if ( ctype_digit( $ref ) ) {
			return (int) $ref;
		}
		$id = url_to_postid( $ref );
		return $id ? (int) $id : 0;
	}

	private function handle_fact_check_post() {
		check_admin_referer( self::FACT_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Fact_Checker' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::FACT_SLUG, array( 'rwai_msg' => 'fact-missing' ) ) );
			exit;
		}
		$ref     = isset( $_POST['post_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ref'] ) ) : '';
		$post_id = $this->resolve_post_ref( $ref );
		if ( ! $post_id ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::FACT_SLUG, array( 'rwai_msg' => 'fact-missing' ) ) );
			exit;
		}
		$use_claude = ! empty( $_POST['use_claude'] );
		( new RankWriter_AI_Fact_Checker() )->check_post( $post_id, $use_claude );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::FACT_SLUG, array( 'rwai_msg' => 'fact-checked', 'post_id' => $post_id ) ) );
		exit;
	}

	public function render_fact_checker() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Fact_Checker' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Fact Checker', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Fact Checker module is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$checker = new RankWriter_AI_Fact_Checker();
		$report  = $post_id ? $checker->get_report( $post_id ) : array();
		$data = array(
			'post_id' => $post_id,
			'report'  => $report,
			'msg'     => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/fact-checker.php';
	}

	/* ============================ Content Refresher ============================ */

	private function handle_save_refresher_settings() {
		check_admin_referer( self::REFRESH_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Content_Refresher' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-error', 'rwai_err' => rawurlencode( __( 'Refresher module unavailable.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$raw       = isset( $_POST['rwai_refresher'] ) ? (array) wp_unslash( $_POST['rwai_refresher'] ) : array();
		$refresher = new RankWriter_AI_Content_Refresher();
		$refresher->save_settings( $raw );
		if ( ! empty( $raw['enabled'] ) ) {
			$refresher->schedule_recurring();
		} else {
			RankWriter_AI_Content_Refresher::clear_schedules();
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-saved' ) ) );
		exit;
	}

	private function handle_refresh_post_now() {
		check_admin_referer( self::REFRESH_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Content_Refresher' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-error', 'rwai_err' => rawurlencode( __( 'Refresher module unavailable.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-error', 'rwai_err' => rawurlencode( __( 'No post specified.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_Content_Refresher() )->refresh_post( $post_id, array( 'source' => 'manual' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::REFRESH_SLUG, array( 'rwai_msg' => 'refresher-ran' ) ) );
		exit;
	}

	public function register_freshness_meta_box( $post ) {
		if ( ! $post || ! class_exists( 'RankWriter_AI_Fact_Checker' ) ) {
			return;
		}
		add_meta_box(
			'rwai_freshness_box',
			__( 'RankWriter AI — Freshness & Facts', 'rankwriter-ai' ),
			array( $this, 'render_freshness_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_freshness_meta_box( $post ) {
		$checker = new RankWriter_AI_Fact_Checker();
		$report  = $checker->get_report( $post->ID );
		$score   = $checker->get_freshness( $post->ID );

		if ( empty( $report ) ) {
			echo '<p class="rwai-muted">' . esc_html__( 'No fact report yet for this post.', 'rankwriter-ai' ) . '</p>';
		} else {
			$conf = (int) ( $report['fact_confidence_score'] ?? 0 );
			$fresh = (int) ( $report['freshness_score'] ?? 0 );
			$outdated = ! empty( $report['outdated'] );

			$conf_band  = $conf  >= 80 ? 'rwai-pill-ok' : ( $conf  >= 50 ? 'rwai-pill-warn' : 'rwai-pill-bad' );
			$fresh_band = $fresh >= 80 ? 'rwai-pill-ok' : ( $fresh >= 50 ? 'rwai-pill-warn' : 'rwai-pill-bad' );

			echo '<p><strong>' . esc_html__( 'Confidence:', 'rankwriter-ai' ) . '</strong> <span class="rwai-pill ' . esc_attr( $conf_band ) . '">' . esc_html( $conf ) . '/100</span></p>';
			echo '<p><strong>' . esc_html__( 'Freshness:', 'rankwriter-ai' ) . '</strong> <span class="rwai-pill ' . esc_attr( $fresh_band ) . '">' . esc_html( $fresh ) . '/100</span>';
			if ( $outdated ) {
				echo ' <span class="rwai-pill rwai-pill-bad">' . esc_html__( 'OUTDATED', 'rankwriter-ai' ) . '</span>';
			}
			echo '</p>';

			$wcount = (int) count( $report['warnings'] ?? array() );
			if ( $wcount > 0 ) {
				echo '<p class="rwai-muted">' . esc_html( sprintf( _n( '%d verification warning', '%d verification warnings', $wcount, 'rankwriter-ai' ), $wcount ) ) . '</p>';
			}
		}

		echo '<p style="margin-top:10px;">';
		echo '<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( self::FACT_SLUG, array( 'post_id' => $post->ID ) ) ) . '" class="button button-small">🔍 ' . esc_html__( 'Open fact report', 'rankwriter-ai' ) . '</a> ';
		if ( class_exists( 'RankWriter_AI_Content_Refresher' ) ) :
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::REFRESH_SLUG ) ); ?>" style="display:inline-block;margin-left:6px;">
			<input type="hidden" name="rwai_action" value="refresh_post_now" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
			<?php wp_nonce_field( self::REFRESH_NONCE ); ?>
			<button type="submit" class="button button-small button-primary">⟳ <?php esc_html_e( 'Refresh now', 'rankwriter-ai' ); ?></button>
		</form>
		<?php
		endif;
		echo '</p>';

		$last_refresh = get_post_meta( $post->ID, RankWriter_AI_Content_Refresher::META_LAST_REFRESH, true );
		$count        = (int) get_post_meta( $post->ID, RankWriter_AI_Content_Refresher::META_REFRESH_COUNT, true );
		if ( $last_refresh ) {
			echo '<p class="rwai-muted">' . esc_html( sprintf( __( 'Last refreshed: %1$s · total %2$d', 'rankwriter-ai' ), mysql2date( get_option( 'date_format' ), $last_refresh ), $count ) ) . '</p>';
		}
	}

	public function render_refresher() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Content_Refresher' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Auto Update Old Articles', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Content Refresher module is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$refresher = new RankWriter_AI_Content_Refresher();
		$settings  = $refresher->get_settings();
		// Limit inventory pass to a reasonable batch — page load can't hang
		// on scoring 1000 posts via the Fact Checker.
		$inventory = $refresher->stale_inventory( 50 );
		$log       = class_exists( 'RankWriter_AI_Refresher_DB' ) ? RankWriter_AI_Refresher_DB::recent( 30 ) : array();
		$quota     = class_exists( 'RankWriter_AI_Refresher_DB' ) ? RankWriter_AI_Refresher_DB::count_in_window( 24 ) : 0;
		$next_run  = wp_next_scheduled( RankWriter_AI_Content_Refresher::CRON_HOOK );
		$data = array(
			'settings'        => $settings,
			'inventory'       => $inventory,
			'log'             => $log,
			'quota_used_24h'  => $quota,
			'next_run_ts'     => $next_run,
			'msg'             => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'             => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/content-refresher.php';
	}

	/* ============================ Schema Engine ============================ */

	private function handle_schema_save_org() {
		check_admin_referer( self::SCHEMA_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::SCHEMA_SLUG ) );
			exit;
		}
		$raw = isset( $_POST['rwai_schema_org'] ) ? (array) wp_unslash( $_POST['rwai_schema_org'] ) : array();
		RankWriter_AI_Schema_Engine::save_organization_settings( $raw );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::SCHEMA_SLUG, array( 'rwai_msg' => 'schema-org-saved' ) ) );
		exit;
	}

	private function handle_schema_rebuild_all() {
		check_admin_referer( self::SCHEMA_NONCE );
		if ( class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			$engine = new RankWriter_AI_Schema_Engine();
			$posts  = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			) );
			foreach ( $posts as $pid ) {
				$engine->build_and_save( (int) $pid );
			}
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::SCHEMA_SLUG, array( 'rwai_msg' => 'schema-rebuilt' ) ) );
		exit;
	}

	private function handle_schema_save_post_meta() {
		check_admin_referer( self::SCHEMA_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			return;
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type     = isset( $_POST['rwai_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rwai_schema_type'] ) ) : '';
		$skip_faq = ! empty( $_POST['rwai_schema_skip_faq'] ) ? 1 : 0;

		$engine = new RankWriter_AI_Schema_Engine();
		if ( $type ) {
			$engine->set_primary_type( $post_id, $type );
		}
		update_post_meta( $post_id, RankWriter_AI_Schema_Engine::META_FAQ_OPTOUT, $skip_faq );
		$engine->build_and_save( $post_id );

		wp_safe_redirect( add_query_arg( array( 'rwai_msg' => 'schema-saved' ), get_edit_post_link( $post_id, 'redirect' ) ) );
		exit;
	}

	public function register_schema_meta_box( $post ) {
		if ( ! $post || ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			return;
		}
		add_meta_box(
			'rwai_schema_box',
			__( 'RankWriter AI — Schema', 'rankwriter-ai' ),
			array( $this, 'render_schema_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_schema_meta_box( $post ) {
		$engine        = new RankWriter_AI_Schema_Engine();
		$primary       = $engine->get_primary_type( $post->ID );
		$detected      = $primary ?: $engine->detect_primary_type( $post );
		$skip_faq      = (int) get_post_meta( $post->ID, RankWriter_AI_Schema_Engine::META_FAQ_OPTOUT, true );
		$types_options = RankWriter_AI_Schema_Engine::available_primary_types();

		$payload = $engine->get_saved_graph( $post->ID );
		if ( empty( $payload['@graph'] ) ) {
			$payload = $engine->build_graph( $post->ID );
		}
		$present_types = array();
		foreach ( (array) ( $payload['@graph'] ?? array() ) as $node ) {
			if ( ! empty( $node['@type'] ) ) {
				$present_types[] = $node['@type'];
			}
		}
		$present_types = array_unique( $present_types );
		$warnings      = $engine->validate( $payload );
		$errors        = array_filter( $warnings, function( $w ) { return ( $w['severity'] ?? '' ) === 'error'; } );

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SCHEMA_SLUG ) ); ?>">
			<input type="hidden" name="rwai_action" value="schema_save_post_meta" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
			<?php wp_nonce_field( self::SCHEMA_NONCE ); ?>
			<p>
				<label for="rwai_schema_type"><strong><?php esc_html_e( 'Primary @type', 'rankwriter-ai' ); ?></strong></label>
				<select id="rwai_schema_type" name="rwai_schema_type" style="width:100%;">
					<?php foreach ( $types_options as $t => $label ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $detected, $t ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! $primary ) : ?>
					<small class="rwai-muted"><?php
						/* translators: %s: detected schema type */
						printf( esc_html__( 'Auto-detected: %s. Override above if needed.', 'rankwriter-ai' ), '<code>' . esc_html( $detected ) . '</code>' );
					?></small>
				<?php endif; ?>
			</p>
			<p>
				<label><input type="checkbox" name="rwai_schema_skip_faq" value="1" <?php checked( $skip_faq, 1 ); ?> /> <?php esc_html_e( 'Skip auto-FAQ schema', 'rankwriter-ai' ); ?></label>
			</p>
			<p>
				<strong><?php esc_html_e( 'In graph:', 'rankwriter-ai' ); ?></strong><br>
				<?php foreach ( $present_types as $t ) : ?>
					<span class="rwai-pill rwai-pill-ok" style="margin:2px 2px 0 0; display:inline-block;"><?php echo esc_html( $t ); ?></span>
				<?php endforeach; ?>
			</p>
			<?php if ( ! empty( $errors ) ) : ?>
				<p><span class="rwai-pill rwai-pill-bad"><?php echo esc_html( sprintf( _n( '%d validation error', '%d validation errors', count( $errors ), 'rankwriter-ai' ), count( $errors ) ) ); ?></span></p>
				<ul style="margin-left:0;font-size:12px;">
				<?php foreach ( array_slice( $errors, 0, 4 ) as $w ) : ?>
					<li class="rwai-muted">• <?php echo esc_html( $w['message'] ?? '' ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><span class="rwai-pill rwai-pill-ok"><?php esc_html_e( '✓ Valid', 'rankwriter-ai' ); ?></span></p>
			<?php endif; ?>
			<p>
				<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Save & rebuild', 'rankwriter-ai' ); ?></button>
				<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( self::SCHEMA_SLUG, array( 'preview' => $post->ID ) ) ); ?>"><?php esc_html_e( 'Preview', 'rankwriter-ai' ); ?></a>
			</p>
		</form>
		<?php
	}

	public function render_schema() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Schema_Engine' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Schema', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Schema Engine is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$engine = new RankWriter_AI_Schema_Engine();
		// Audit limited to 30 posts on each page-load to keep the request
		// responsive. The "Rebuild last 100 posts" button does the heavier
		// pass and persists the results.
		$audit  = $engine->audit_recent( 30 );
		$org    = RankWriter_AI_Schema_Engine::organization_settings();
		$seo    = class_exists( 'RankWriter_AI_SEO_Integration' ) ? ( new RankWriter_AI_SEO_Integration() )->detect_plugin() : 'none';
		$data = array(
			'audit'      => $audit,
			'org'        => $org,
			'seo_plugin' => $seo,
			'msg'        => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/schema-dashboard.php';
	}

	/* ============================ Seasonal Trend Engine ============================ */

	private function handle_seasonal_refresh_coverage() {
		check_admin_referer( self::SEASONAL_NONCE );
		if ( class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			( new RankWriter_AI_Seasonal_Engine() )->refresh_coverage_cache();
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::SEASONAL_SLUG, array( 'rwai_msg' => 'seasonal-refreshed' ) ) );
		exit;
	}

	private function handle_seasonal_dismiss() {
		check_admin_referer( self::SEASONAL_NONCE );
		$event_id = isset( $_POST['event_id'] ) ? sanitize_key( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id && class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			( new RankWriter_AI_Seasonal_Engine() )->dismiss_event( $event_id );
		}
		$back = wp_get_referer() ?: RankWriter_AI_Helpers::admin_url( self::SEASONAL_SLUG );
		wp_safe_redirect( $back );
		exit;
	}

	public function maybe_render_seasonal_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Only show on the main RankWriter screens + dashboard. The post
		// editor would be too noisy.
		if ( ! $screen || ! preg_match( '/^(dashboard|rankwriter-ai_page_|toplevel_page_rankwriter-ai)/', (string) $screen->id ) ) {
			return;
		}
		// Suppress on the seasonal page itself — the data is already there.
		if ( false !== strpos( (string) $screen->id, self::SEASONAL_SLUG ) ) {
			return;
		}
		$engine    = new RankWriter_AI_Seasonal_Engine();
		$dismissed = $engine->dismissed_event_ids();
		$niches    = $engine->detect_niches();
		$upcoming  = $engine->upcoming( 60, $niches, true );
		foreach ( $upcoming as $row ) {
			$ev = $row['event'];
			if ( isset( $dismissed[ $ev['id'] ] ) && ( time() - (int) $dismissed[ $ev['id'] ] ) < ( 14 * DAY_IN_SECONDS ) ) {
				continue; // dismissed within last 14 days
			}
			// Only nag for ungated hot opportunities with no coverage.
			if ( $row['heat'] < 70 || ( $row['coverage']['count'] ?? 0 ) > 0 ) {
				continue;
			}
			?>
			<div class="notice notice-info">
				<p>
					<strong>📅 <?php echo esc_html( $ev['name'] ); ?></strong> —
					<?php echo esc_html( sprintf( __( '%1$d days away. Heat %2$d/100. You have no posts covering this — ideal to publish by %3$s.', 'rankwriter-ai' ), $row['days_until_event'], $row['heat'], $row['window']['ideal_publish'] ) ); ?>
					&nbsp;
					<a class="button button-small button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( self::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $row['topic_suggestions'][0] ?? $ev['name'] ) ) ) ); ?>"><?php esc_html_e( '✨ Generate now', 'rankwriter-ai' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( self::SEASONAL_SLUG ) ); ?>"><?php esc_html_e( 'See all', 'rankwriter-ai' ); ?></a>
					<form method="post" style="display:inline-block;margin-left:6px;">
						<input type="hidden" name="rwai_action" value="seasonal_dismiss" />
						<input type="hidden" name="event_id" value="<?php echo esc_attr( $ev['id'] ); ?>" />
						<?php wp_nonce_field( self::SEASONAL_NONCE ); ?>
						<button type="submit" class="button-link rwai-muted"><?php esc_html_e( 'Dismiss', 'rankwriter-ai' ); ?></button>
					</form>
				</p>
			</div>
			<?php
			return; // one notice at a time
		}
	}

	public function render_seasonal() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Seasonal Trends', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Seasonal Trend Engine is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$engine        = new RankWriter_AI_Seasonal_Engine();
		$niche_filter  = isset( $_GET['niche'] ) ? array( sanitize_key( wp_unslash( $_GET['niche'] ) ) ) : array();
		$detected      = $engine->detect_niches();
		$upcoming      = $engine->upcoming( 120, $niche_filter ?: array(), true );
		$insights      = $engine->niche_insights( $niche_filter ?: $detected, 120 );
		$year          = (int) date( 'Y' );
		$calendar      = $engine->calendar_year( $year );

		$data = array(
			'upcoming'        => $upcoming,
			'insights'        => $insights,
			'calendar'        => $calendar,
			'detected_niches' => $detected,
			'year'            => $year,
			'msg'             => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/seasonal-calendar.php';
	}

	/* ============================ Voice Memory ============================ */

	private function handle_voice_save_brand() {
		check_admin_referer( self::VOICE_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG ) );
			exit;
		}
		$mem  = new RankWriter_AI_Voice_Memory();
		$prof = $mem->get_profile();
		$raw  = isset( $_POST['rwai_voice'] ) ? (array) wp_unslash( $_POST['rwai_voice'] ) : array();
		$prof['brand_tagline'] = sanitize_text_field( $raw['brand_tagline'] ?? '' );
		$prof['brand_pillars'] = sanitize_textarea_field( $raw['brand_pillars'] ?? '' );
		$prof['brand_avoid']   = sanitize_textarea_field( $raw['brand_avoid'] ?? '' );
		$prof['auto_learn']    = ! empty( $raw['auto_learn'] ) ? 1 : 0;
		$mem->save_profile( $prof );
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-saved' ) ) );
		exit;
	}

	private function handle_voice_apply_preset() {
		check_admin_referer( self::VOICE_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG ) );
			exit;
		}
		$preset = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		$result = ( new RankWriter_AI_Voice_Memory() )->apply_preset( $preset );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-preset-applied' ) ) );
		exit;
	}

	private function handle_voice_calibrate() {
		check_admin_referer( self::VOICE_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG ) );
			exit;
		}
		$result = ( new RankWriter_AI_Voice_Memory() )->calibrate( 25 );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-calibrated' ) ) );
		exit;
	}

	private function handle_voice_save_category() {
		check_admin_referer( self::VOICE_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG ) );
			exit;
		}
		$mem = new RankWriter_AI_Voice_Memory();
		$raw = isset( $_POST['rwai_voice_cat'] ) ? (array) wp_unslash( $_POST['rwai_voice_cat'] ) : array();
		foreach ( $raw as $cid => $vals ) {
			$tone = isset( $vals['tone'] ) ? sanitize_key( $vals['tone'] ) : '';
			$note = isset( $vals['note'] ) ? sanitize_textarea_field( $vals['note'] ) : '';
			$mem->set_category_override( (int) $cid, $tone, $note );
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-saved' ) ) );
		exit;
	}

	private function handle_voice_reset() {
		check_admin_referer( self::VOICE_NONCE );
		if ( class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			( new RankWriter_AI_Voice_Memory() )->reset();
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::VOICE_SLUG, array( 'rwai_msg' => 'voice-reset' ) ) );
		exit;
	}

	public function render_voice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Voice_Memory' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Brand Voice', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Voice Memory module is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$mem = new RankWriter_AI_Voice_Memory();
		$data = array(
			'profile'    => $mem->get_profile(),
			'tones'      => RankWriter_AI_Voice_Memory::supported_tones(),
			'presets'    => RankWriter_AI_Voice_Memory::presets(),
			'categories' => get_categories( array( 'hide_empty' => false, 'number' => 200 ) ),
			'msg'        => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'        => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/voice-memory.php';
	}

	/* ============================ Parasite SEO ============================ */

	private function handle_parasite_generate() {
		check_admin_referer( self::PARASITE_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Parasite_Engine' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array( 'rwai_msg' => 'syn-error', 'rwai_err' => rawurlencode( __( 'Parasite engine unavailable.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$ref      = isset( $_POST['post_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ref'] ) ) : '';
		$platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
		$post_id  = $this->resolve_post_ref( $ref );
		if ( ! $post_id ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array( 'rwai_msg' => 'syn-error', 'rwai_err' => rawurlencode( __( 'Could not resolve post.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_Parasite_Engine() )->generate( $post_id, $platform );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array( 'rwai_msg' => 'syn-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		// We need to re-fetch the payload via the log row when rendering,
		// since redirect drops the in-memory data. Stash key params in the URL.
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array(
			'rwai_msg' => 'syn-generated',
			'log_id'   => (int) ( $result['log_id'] ?? 0 ),
			'post_id'  => (int) $post_id,
			'platform' => $platform,
		) ) );
		exit;
	}

	private function handle_parasite_mark_published() {
		check_admin_referer( self::PARASITE_NONCE );
		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$url    = isset( $_POST['external_url'] ) ? esc_url_raw( wp_unslash( $_POST['external_url'] ) ) : '';
		if ( $log_id && $url && class_exists( 'RankWriter_AI_Parasite_Engine' ) ) {
			( new RankWriter_AI_Parasite_Engine() )->mark_published( $log_id, $url );
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array( 'rwai_msg' => 'syn-marked' ) ) );
		exit;
	}

	private function handle_parasite_delete() {
		check_admin_referer( self::PARASITE_NONCE );
		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		if ( $log_id && class_exists( 'RankWriter_AI_Parasite_Engine' ) ) {
			( new RankWriter_AI_Parasite_Engine() )->delete_syndication( $log_id );
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::PARASITE_SLUG, array( 'rwai_msg' => 'syn-deleted' ) ) );
		exit;
	}

	public function register_parasite_meta_box( $post ) {
		if ( ! $post || ! class_exists( 'RankWriter_AI_Parasite_Engine' ) ) {
			return;
		}
		add_meta_box(
			'rwai_parasite_box',
			__( 'RankWriter AI — Parasite SEO', 'rankwriter-ai' ),
			array( $this, 'render_parasite_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_parasite_meta_box( $post ) {
		$rows = class_exists( 'RankWriter_AI_Syndication_DB' ) ? RankWriter_AI_Syndication_DB::for_post( $post->ID, 10 ) : array();
		$plats = RankWriter_AI_Parasite_Engine::platforms();
		echo '<p>' . esc_html__( 'Repurpose this post for external platforms.', 'rankwriter-ai' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::PARASITE_SLUG ) ) . '">';
		echo '<input type="hidden" name="rwai_action" value="parasite_generate" />';
		echo '<input type="hidden" name="post_ref" value="' . esc_attr( $post->ID ) . '" />';
		wp_nonce_field( self::PARASITE_NONCE );
		echo '<p><label for="rwai_par_plat"><strong>' . esc_html__( 'Platform', 'rankwriter-ai' ) . '</strong></label>';
		echo '<select id="rwai_par_plat" name="platform" style="width:100%;">';
		foreach ( $plats as $k => $cfg ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $cfg['label'] ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><button type="submit" class="button button-primary button-small">' . esc_html__( '✨ Generate', 'rankwriter-ai' ) . '</button></p>';
		echo '</form>';

		if ( ! empty( $rows ) ) {
			echo '<p style="margin-top:10px;"><strong>' . esc_html__( 'Syndicated to:', 'rankwriter-ai' ) . '</strong></p>';
			echo '<ul style="margin:4px 0 0;padding-left:18px;font-size:12px;">';
			foreach ( $rows as $r ) {
				$plabel = $plats[ $r['platform'] ]['label'] ?? ucfirst( $r['platform'] );
				$cls    = 'published' === $r['status'] ? 'rwai-pill-ok' : 'rwai-pill-warn';
				echo '<li><span class="rwai-pill ' . esc_attr( $cls ) . '">' . esc_html( $plabel ) . '</span> ';
				if ( ! empty( $r['external_url'] ) ) {
					echo '<a href="' . esc_url( $r['external_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'view', 'rankwriter-ai' ) . '</a>';
				} else {
					echo '<span class="rwai-muted">' . esc_html__( 'draft', 'rankwriter-ai' ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
	}

	public function render_parasite() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Parasite_Engine' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Parasite SEO', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Parasite engine is unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$platforms = RankWriter_AI_Parasite_Engine::platforms();
		$log_id    = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0;
		$post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$platform  = isset( $_GET['platform'] ) ? sanitize_key( wp_unslash( $_GET['platform'] ) ) : '';
		$payload   = array();

		// Reload the most recent generation by log_id so the user sees the
		// output panel after the redirect.
		if ( $log_id && class_exists( 'RankWriter_AI_Syndication_DB' ) ) {
			$row = RankWriter_AI_Syndication_DB::get( $log_id );
			if ( $row ) {
				$tags = json_decode( (string) $row['generated_hashtags'], true );
				if ( ! is_array( $tags ) ) { $tags = array(); }
				$cfg = $platforms[ $row['platform'] ] ?? null;
				$payload = array(
					'log_id'           => (int) $row['id'],
					'platform'         => $row['platform'],
					'title'            => (string) $row['generated_title'],
					'body'             => (string) $row['generated_body'],
					'cta'              => (string) $row['generated_cta'],
					'hashtags'         => $tags,
					'compliance_notes' => (string) $row['compliance_notes'],
					'canonical_url'    => $cfg && ! empty( $cfg['canonical_safe'] ) ? get_permalink( (int) $row['post_id'] ) : '',
				);
				$post_id = (int) $row['post_id'];
			}
		}

		$data = array(
			'post_id'   => $post_id,
			'platform'  => $platform,
			'payload'   => $payload,
			'log_rows'  => class_exists( 'RankWriter_AI_Syndication_DB' ) ? RankWriter_AI_Syndication_DB::recent( 50 ) : array(),
			'platforms' => $platforms,
			'msg'       => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'       => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/parasite-seo.php';
	}

	/* ============================ Risk Detector ============================ */

	private function handle_risk_scan_post() {
		check_admin_referer( self::RISK_NONCE );
		if ( ! class_exists( 'RankWriter_AI_Risk_Detector' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'rwai_msg' => 'risk-missing' ) ) );
			exit;
		}
		$ref = isset( $_POST['post_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ref'] ) ) : '';
		$pid = $this->resolve_post_ref( $ref );
		if ( ! $pid ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'rwai_msg' => 'risk-missing' ) ) );
			exit;
		}
		$use_claude = ! empty( $_POST['use_claude'] );
		$result = ( new RankWriter_AI_Risk_Detector() )->scan_post( $pid, array( 'use_claude' => $use_claude ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'rwai_msg' => 'risk-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'rwai_msg' => 'risk-scanned', 'post_id' => $pid ) ) );
		exit;
	}

	private function handle_risk_bulk_rescan() {
		check_admin_referer( self::RISK_NONCE );
		if ( class_exists( 'RankWriter_AI_Risk_Detector' ) ) {
			$detector = new RankWriter_AI_Risk_Detector();
			$posts    = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			) );
			foreach ( $posts as $pid ) {
				$detector->scan_post( (int) $pid );
			}
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'rwai_msg' => 'risk-scanned' ) ) );
		exit;
	}

	public function register_risk_meta_box( $post ) {
		if ( ! $post || ! class_exists( 'RankWriter_AI_Risk_Detector' ) ) {
			return;
		}
		add_meta_box(
			'rwai_risk_box',
			__( 'RankWriter AI — Content Risk', 'rankwriter-ai' ),
			array( $this, 'render_risk_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public function render_risk_meta_box( $post ) {
		$detector = new RankWriter_AI_Risk_Detector();
		$report   = $detector->get_report( $post->ID );
		$risk     = (int) get_post_meta( $post->ID, RankWriter_AI_Risk_Detector::META_SCORE, true );
		$adsense  = (int) get_post_meta( $post->ID, RankWriter_AI_Risk_Detector::META_ADSENSE, true );

		if ( empty( $report ) ) {
			echo '<p class="rwai-muted">' . esc_html__( 'No risk scan yet for this post.', 'rankwriter-ai' ) . '</p>';
		} else {
			$band   = $report['risk_band'] ?? 'safe';
			$rpill  = $risk    >= 70 ? 'rwai-pill-bad' : ( $risk    >= 35 ? 'rwai-pill-warn' : 'rwai-pill-ok' );
			$apill  = $adsense >= 80 ? 'rwai-pill-ok'  : ( $adsense >= 50 ? 'rwai-pill-warn' : 'rwai-pill-bad' );
			echo '<p><strong>' . esc_html__( 'Risk:', 'rankwriter-ai' ) . '</strong> <span class="rwai-pill ' . esc_attr( $rpill ) . '">' . esc_html( $risk ) . '/100</span> <span class="rwai-muted">(' . esc_html( strtoupper( $band ) ) . ')</span></p>';
			echo '<p><strong>' . esc_html__( 'AdSense:', 'rankwriter-ai' ) . '</strong> <span class="rwai-pill ' . esc_attr( $apill ) . '">' . esc_html( $adsense ) . '/100</span></p>';
			$total = count( $report['warnings'] ?? array() );
			$crits = count( array_filter( (array) ( $report['warnings'] ?? array() ), function( $w ) { return ( $w['severity'] ?? '' ) === 'critical'; } ) );
			if ( $total > 0 ) {
				echo '<p class="rwai-muted">' . esc_html( sprintf( _n( '%1$d finding (%2$d critical)', '%1$d findings (%2$d critical)', $total, 'rankwriter-ai' ), $total, $crits ) ) . '</p>';
			}
			if ( ! empty( $report['should_block_publish'] ) ) {
				echo '<p><span class="rwai-pill rwai-pill-bad">' . esc_html__( 'HIGH-RISK PUBLISH', 'rankwriter-ai' ) . '</span></p>';
			}
		}
		echo '<p style="margin-top:10px;"><a class="button button-small" href="' . esc_url( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'post_id' => $post->ID ) ) ) . '">🛡️ ' . esc_html__( 'Open risk report', 'rankwriter-ai' ) . '</a></p>';
	}

	/**
	 * Show a publish-blocking warning banner on the post-edit screen when a
	 * high-risk post is still in draft. We can't actually block the click
	 * without JS hooks, but the warning is conspicuous + the meta box pill
	 * reinforces it.
	 */
	public function maybe_render_risk_publish_warning() {
		global $pagenow, $post;
		if ( 'post.php' !== $pagenow || ! $post || 'post' !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		if ( 'publish' === $post->post_status ) {
			return;
		}
		$report = class_exists( 'RankWriter_AI_Risk_Detector' )
			? ( new RankWriter_AI_Risk_Detector() )->get_report( $post->ID )
			: array();
		if ( empty( $report['should_block_publish'] ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>🛡️ ' . esc_html__( 'RankWriter AI — high-risk content detected.', 'rankwriter-ai' ) . '</strong> ';
		echo esc_html( sprintf(
			/* translators: 1: risk score, 2: adsense score */
			__( 'Risk score %1$d/100, AdSense compliance %2$d/100. Publishing as-is may trigger Google policy review or AdSense demonetization.', 'rankwriter-ai' ),
			(int) ( $report['risk_score'] ?? 0 ),
			(int) ( $report['adsense_compliance_score'] ?? 0 )
		) );
		echo ' <a href="' . esc_url( RankWriter_AI_Helpers::admin_url( self::RISK_SLUG, array( 'post_id' => $post->ID ) ) ) . '">' . esc_html__( 'Review findings →', 'rankwriter-ai' ) . '</a></p></div>';
	}

	public function render_risk() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_Risk_Detector' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Risk Detector', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'Risk Detector module unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$detector = new RankWriter_AI_Risk_Detector();
		$post_id  = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$report   = $post_id ? $detector->get_report( $post_id ) : array();
		$data = array(
			'post_id'   => $post_id,
			'report'    => $report,
			'inventory' => $detector->bulk_audit( 30 ),
			'msg'       => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'       => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/risk-dashboard.php';
	}

	/* ============================ SEO Healer ============================ */

	private function handle_healer_save_settings() {
		check_admin_referer( self::HEALER_NONCE );
		if ( ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG ) );
			exit;
		}
		$raw    = isset( $_POST['rwai_healer'] ) ? (array) wp_unslash( $_POST['rwai_healer'] ) : array();
		$healer = new RankWriter_AI_SEO_Healer();
		$healer->save_settings( $raw );
		if ( ! empty( $raw['enabled'] ) ) {
			$healer->schedule_recurring();
		} else {
			RankWriter_AI_SEO_Healer::clear_schedules();
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-saved' ) ) );
		exit;
	}

	private function handle_healer_scan_now() {
		check_admin_referer( self::HEALER_NONCE );
		if ( class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			( new RankWriter_AI_SEO_Healer() )->scan_tick();
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-scanned' ) ) );
		exit;
	}

	private function handle_healer_fix_issue() {
		check_admin_referer( self::HEALER_NONCE );
		$issue_id = isset( $_POST['issue_id'] ) ? absint( $_POST['issue_id'] ) : 0;
		if ( ! $issue_id || ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( __( 'Issue not specified.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_SEO_Healer() )->auto_fix_issue( $issue_id, 'manual' );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-fixed' ) ) );
		exit;
	}

	private function handle_healer_rollback() {
		check_admin_referer( self::HEALER_NONCE );
		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		if ( ! $log_id || ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( __( 'Repair entry not specified.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_SEO_Healer() )->rollback_repair( $log_id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-rolled' ) ) );
		exit;
	}

	private function handle_healer_replace_broken_link() {
		check_admin_referer( self::HEALER_NONCE );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$old_url = isset( $_POST['old_url'] ) ? wp_unslash( $_POST['old_url'] ) : '';
		$new_url = isset( $_POST['new_url'] ) ? wp_unslash( $_POST['new_url'] ) : '';
		if ( ! $post_id || ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( __( 'Post not specified.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_SEO_Healer() )->replace_link_in_post( $post_id, $old_url, $new_url );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-fixed' ) ) );
		exit;
	}

	private function handle_healer_delete_broken_link() {
		check_admin_referer( self::HEALER_NONCE );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$old_url = isset( $_POST['old_url'] ) ? wp_unslash( $_POST['old_url'] ) : '';
		if ( ! $post_id || ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( __( 'Post not specified.', 'rankwriter-ai' ) ) ) ) );
			exit;
		}
		$result = ( new RankWriter_AI_SEO_Healer() )->delete_link_in_post( $post_id, $old_url );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-error', 'rwai_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( RankWriter_AI_Helpers::admin_url( self::HEALER_SLUG, array( 'rwai_msg' => 'healer-fixed' ) ) );
		exit;
	}

	public function render_healer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RankWriter_AI_SEO_Healer' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'SEO Healer', 'rankwriter-ai' ) . '</h1><p>' . esc_html__( 'SEO Healer module unavailable.', 'rankwriter-ai' ) . '</p></div>';
			return;
		}
		$healer = new RankWriter_AI_SEO_Healer();
		$filter = array();
		if ( ! empty( $_GET['rule'] ) ) {
			$filter['rule'] = sanitize_key( wp_unslash( $_GET['rule'] ) );
		}
		$data = array(
			'settings'        => $healer->get_settings(),
			'counts'          => RankWriter_AI_SEO_Healer_DB::counts_by_rule(),
			'severity_totals' => RankWriter_AI_SEO_Healer_DB::severity_totals(),
			'total_open'      => RankWriter_AI_SEO_Healer_DB::total_open(),
			'issues'          => RankWriter_AI_SEO_Healer_DB::open_issues( 100, $filter ),
			'repairs'         => RankWriter_AI_SEO_Healer_DB::recent_repairs( 30 ),
			'health_score'    => $healer->health_score(),
			'next_scan'       => wp_next_scheduled( RankWriter_AI_SEO_Healer::CRON_HOOK_SCAN ),
			'next_fix'        => wp_next_scheduled( RankWriter_AI_SEO_Healer::CRON_HOOK_FIX ),
			'repaired_24h'    => RankWriter_AI_SEO_Healer_DB::count_repairs_in_window( 24 ),
			'msg'             => isset( $_GET['rwai_msg'] ) ? sanitize_key( $_GET['rwai_msg'] ) : '',
			'err'             => isset( $_GET['rwai_err'] ) ? wp_unslash( $_GET['rwai_err'] ) : '',
		);
		require RWAI_PLUGIN_DIR . 'admin/partials/seo-healer.php';
	}
}
