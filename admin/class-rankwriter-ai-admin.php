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

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'handle_post_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_rwai_run_analysis', array( $this, 'ajax_run_analysis' ) );
		add_action( 'wp_ajax_rwai_deep_analysis', array( $this, 'ajax_run_deep_analysis' ) );
		add_action( 'wp_ajax_rwai_ai_suggest', array( $this, 'ajax_ai_suggest' ) );

		add_action( 'admin_notices', array( $this, 'maybe_render_compliance_notice' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_compliance_meta_box' ) );

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
				'i18n'           => array(
					'running'     => __( 'Analyzing your blog…', 'rankwriter-ai' ),
					'done'        => __( 'Analysis complete.', 'rankwriter-ai' ),
					'failed'      => __( 'Analysis failed.', 'rankwriter-ai' ),
					'deepRunning' => __( 'Asking Claude to read 8 sample posts…', 'rankwriter-ai' ),
					'deepDone'    => __( 'Deep analysis saved.', 'rankwriter-ai' ),
					'aiFill'      => __( 'AI fill', 'rankwriter-ai' ),
					'aiThinking'  => __( 'Asking Claude…', 'rankwriter-ai' ),
					'aiFailed'    => __( 'AI fill failed.', 'rankwriter-ai' ),
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
		if ( ! current_user_can( 'manage_options' ) && ! ( 'generate_article' === $_POST['rwai_action'] && current_user_can( 'edit_posts' ) ) ) {
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
		}
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
		$data = array(
			'profile_count' => $profiles->count(),
			'style_profile' => $style->get(),
			'last_run'      => $style->last_run(),
			'api_ready'     => $client->is_configured(),
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
}
