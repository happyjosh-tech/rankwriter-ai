<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$profile_count = (int) $data['profile_count'];
$style         = (array) $data['style_profile'];
$last_run      = (string) $data['last_run'];
$api_ready     = (bool) $data['api_ready'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'RankWriter AI', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'AI-powered article generation that learns from your existing blog and supports unlimited custom category profiles.', 'rankwriter-ai' ); ?></p>

	<div class="rwai-grid rwai-grid-3">
		<div class="rwai-card">
			<h2><?php esc_html_e( 'Claude API', 'rankwriter-ai' ); ?></h2>
			<p class="rwai-stat"><?php echo $api_ready ? '<span class="rwai-pill rwai-pill-ok">' . esc_html__( 'Connected', 'rankwriter-ai' ) . '</span>' : '<span class="rwai-pill rwai-pill-bad">' . esc_html__( 'Not configured', 'rankwriter-ai' ) . '</span>'; ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Configure', 'rankwriter-ai' ); ?></a></p>
		</div>
		<div class="rwai-card">
			<h2><?php esc_html_e( 'Category Profiles', 'rankwriter-ai' ); ?></h2>
			<p class="rwai-stat"><?php echo esc_html( $profile_count ); ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG ) ); ?>"><?php esc_html_e( 'Manage profiles', 'rankwriter-ai' ); ?></a></p>
		</div>
		<div class="rwai-card">
			<h2><?php esc_html_e( 'Blog Style Profile', 'rankwriter-ai' ); ?></h2>
			<p class="rwai-stat"><?php echo $last_run ? '<span class="rwai-pill rwai-pill-ok">' . esc_html__( 'Ready', 'rankwriter-ai' ) . '</span>' : '<span class="rwai-pill rwai-pill-warn">' . esc_html__( 'Not built yet', 'rankwriter-ai' ) . '</span>'; ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::ANALYZER_SLUG ) ); ?>"><?php esc_html_e( 'Open analyzer', 'rankwriter-ai' ); ?></a></p>
		</div>
	</div>

	<?php if ( ! empty( $style['summary'] ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'Current Blog Style Profile', 'rankwriter-ai' ); ?></h2>
			<p class="rwai-muted"><?php
				/* translators: %s: ISO timestamp */
				printf( esc_html__( 'Last built: %s', 'rankwriter-ai' ), esc_html( $last_run ) );
			?></p>
			<p><?php echo esc_html( $style['summary'] ); ?></p>
			<dl class="rwai-dl">
				<dt><?php esc_html_e( 'Preferred tone', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['preferred_tone'] ) ? $style['preferred_tone'] : '' ); ?></dd>
				<dt><?php esc_html_e( 'Headline style', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['common_headline_style'] ) ? $style['common_headline_style'] : '' ); ?></dd>
				<dt><?php esc_html_e( 'Avg. word count', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['average_word_count'] ) ? RankWriter_AI_Helpers::format_number( $style['average_word_count'] ) : '0' ); ?></dd>
				<dt><?php esc_html_e( 'Formatting', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['preferred_formatting']['description'] ) ? $style['preferred_formatting']['description'] : '' ); ?></dd>
				<dt><?php esc_html_e( 'Monetization', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['monetization_patterns']['dominant_strategy'] ) ? $style['monetization_patterns']['dominant_strategy'] : '' ); ?></dd>
				<dt><?php esc_html_e( 'Audience intent', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['audience_intent']['dominant'] ) ? $style['audience_intent']['dominant'] : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Image style', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['image_style_guess'] ) && $style['image_style_guess'] ? $style['image_style_guess'] : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Content gaps', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo esc_html( isset( $style['content_gaps'] ) ? count( $style['content_gaps'] ) . ' detected' : '0 detected' ); ?></dd>
				<dt><?php esc_html_e( 'Deep analysis', 'rankwriter-ai' ); ?></dt>
				<dd><?php echo ! empty( $style['claude_deep_analysis'] ) ? '<span class="rwai-pill rwai-pill-ok">' . esc_html__( 'Ready', 'rankwriter-ai' ) . '</span>' : '<span class="rwai-pill rwai-pill-warn">' . esc_html__( 'Not run yet', 'rankwriter-ai' ) . '</span>'; ?></dd>
			</dl>
		</div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Quick start', 'rankwriter-ai' ); ?></h2>
		<ol>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Add your Claude API key (and optional SerpAPI / DataForSEO + competitor domains)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::ANALYZER_SLUG ) ); ?>"><?php esc_html_e( 'Run the Blog Analyzer to build your Style Profile', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG, array( 'new' => 1 ) ) ); ?>"><?php esc_html_e( 'Create a Category Profile for your niche', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::RESEARCH_SLUG ) ); ?>"><?php esc_html_e( 'Run Keyword Research on a seed topic to pull fresh, live keywords', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG ) ); ?>"><?php esc_html_e( 'Generate your first AI article (SEO meta written automatically)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::AUTOPILOT_SLUG ) ); ?>"><?php esc_html_e( 'Enable Autopilot to run continuously on a schedule', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::LEGAL_SLUG ) ); ?>"><?php esc_html_e( 'Generate your AdSense-required legal pages (About / Contact / Privacy Policy)', 'rankwriter-ai' ); ?></a></li>
		</ol>
		<?php $seo = new RankWriter_AI_SEO_Integration(); ?>
		<p class="rwai-muted"><?php
			/* translators: %s: SEO plugin name */
			printf( esc_html__( 'Detected SEO plugin: %s — generated posts will write their meta here automatically.', 'rankwriter-ai' ), '<strong>' . esc_html( $seo->detected_label() ) . '</strong>' );
		?></p>
	</div>
</div>
