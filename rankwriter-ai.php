<?php
/**
 * Plugin Name:       RankWriter AI
 * Plugin URI:        https://github.com/happyjosh-tech/rankwriter-ai
 * Description:       AI-powered content generator that learns from your existing blog and supports unlimited custom category profiles. Built on Anthropic's Claude API.
 * Version:           1.2.7
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            RankWriter AI
 * Author URI:        https://github.com/happyjosh-tech
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rankwriter-ai
 * Domain Path:       /languages
 * Update URI:        https://github.com/happyjosh-tech/rankwriter-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RWAI_VERSION', '1.2.7' );
define( 'RWAI_PLUGIN_FILE', __FILE__ );
define( 'RWAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RWAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RWAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * GitHub repository the self-updater pulls releases from.
 * Change these two constants if you fork / rename the repo.
 * The repo MUST be public for users to auto-update without auth tokens.
 */
if ( ! defined( 'RWAI_GITHUB_USER' ) ) {
	define( 'RWAI_GITHUB_USER', 'happyjosh-tech' );
}
if ( ! defined( 'RWAI_GITHUB_REPO' ) ) {
	define( 'RWAI_GITHUB_REPO', 'rankwriter-ai' );
}

require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-activator.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-deactivator.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-helpers.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-category-profiles.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-blog-analyzer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-style-profile.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-claude-client.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-seo-integration.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-keyword-research.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-internal-linker.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-compliance.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-risk-detector.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-seo-healer-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-seo-healer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-schema-engine.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-schema-injector.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-image-sourcer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-ai-suggester.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-intent-detector.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-cpc-scorer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-title-intelligence.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-discover-optimizer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pse-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pse-manager.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pse-engine.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pse-queue.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pse-presets.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pinterest-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pinterest-engine.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pinterest-image.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-pinterest-scheduler.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-language.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-translator.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-humanizer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-voice-memory.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-syndication-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-parasite-engine.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-gap-detector.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-seasonal-engine.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-clusters-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-cluster-manager.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-cluster-suggester.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-cluster-analyzer.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-content-generator.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-autopilot.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-generation-queue.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-schedule-recovery.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-browser-cron.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-fact-checker.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-refresher-db.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-content-refresher.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-legal-pages.php';
require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai-github-updater.php';

/**
 * Speed Optimizer module (RankWriter Site Speed Optimizer).
 * Each sub-module is its own file so the boot cost stays linear with
 * features enabled — if a user disables CSS optimization, the class
 * still loads but registers zero hooks.
 */
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-speed-logger.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-cache-manager.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-browser-cache.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-css-optimizer.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-js-optimizer.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-image-optimizer.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-database-cleaner.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-core-web-vitals.php';
require_once RWAI_PLUGIN_DIR . 'includes/speed-optimizer/class-rwai-speed-optimizer.php';

require_once RWAI_PLUGIN_DIR . 'includes/class-rankwriter-ai.php';

if ( is_admin() ) {
	require_once RWAI_PLUGIN_DIR . 'admin/class-rankwriter-ai-admin.php';
}

function rwai_register_weekly_schedule( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'rankwriter-ai' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'rwai_register_weekly_schedule' );

register_activation_hook( __FILE__, array( 'RankWriter_AI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RankWriter_AI_Deactivator', 'deactivate' ) );

function rwai_run() {
	$plugin = new RankWriter_AI();
	$plugin->run();
}
add_action( 'plugins_loaded', 'rwai_run' );

/**
 * GitHub-based auto-update. Hooks into WP's standard plugin updater on
 * `admin_init` (admin context only — no need to run on frontend).
 */
function rwai_boot_github_updater() {
	if ( ! is_admin() ) {
		return;
	}
	if ( ! defined( 'RWAI_GITHUB_USER' ) || '' === RWAI_GITHUB_USER ) {
		return;
	}
	$updater = new RankWriter_AI_GitHub_Updater(
		RWAI_GITHUB_USER,
		RWAI_GITHUB_REPO,
		__FILE__
	);
	$updater->register_hooks();
}
add_action( 'admin_init', 'rwai_boot_github_updater' );
