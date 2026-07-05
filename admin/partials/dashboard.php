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

	<?php
	$cpc_d = isset( $data['cpc_dashboard'] ) ? (array) $data['cpc_dashboard'] : array();
	if ( ! empty( $cpc_d['count'] ) ) :
	?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'Blog monetization snapshot', 'rankwriter-ai' ); ?>
				<small class="rwai-muted" style="font-weight:normal;text-transform:none;letter-spacing:normal;">
					<?php
					/* translators: 1: country code, 2: number of posts scored */
					printf( esc_html__( 'Based on your most recent %2$d posts scored for %1$s.', 'rankwriter-ai' ), esc_html( isset( $cpc_d['country'] ) ? $cpc_d['country'] : 'US' ), (int) $cpc_d['count'] );
					?>
				</small>
			</h2>
			<div class="rwai-cpc-summary-row">
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Avg estimated CPC', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_d['avg_cpc'], 2 ) ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Top recent CPC', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_d['max_cpc'], 2 ) ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Predicted RPM', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_d['avg_rpm'], 0 ) ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Monetization score', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $cpc_d['avg_score'] ); ?>/100</div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Dominant tier', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value">
						<span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $cpc_d['dominant_tier'] ); ?>"><?php echo esc_html( RankWriter_AI_CPC_Scorer::tier_label( $cpc_d['dominant_tier'] ) ); ?></span>
					</div>
				</div>
				<?php if ( ! empty( $cpc_d['priority_count'] ) ) : ?>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Priority-niche posts', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $cpc_d['priority_count'] . ' / ' . (int) $cpc_d['count'] ); ?> ★</div>
					</div>
				<?php endif; ?>
			</div>
			<p class="rwai-muted" style="margin-top:10px;font-size:12px;">
				<?php esc_html_e( 'Estimates are based on heuristic niche + country + intent modeling. With a DataForSEO key configured in Settings, real Google Ads data blends in for accuracy.', 'rankwriter-ai' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php
	$bb = isset( $data['bot_blocker'] ) ? (array) $data['bot_blocker'] : array();
	if ( ! empty( $bb ) ) :
		$bb_url = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::BOT_BLOCKER_SLUG );
	?>
		<div class="rwai-card rwai-card-wide">
			<h2>🛡️ <?php esc_html_e( 'Bot Blocker', 'rankwriter-ai' ); ?>
				<a href="<?php echo esc_url( $bb_url ); ?>" class="page-title-action" style="float:right;"><?php esc_html_e( 'Open Bot Blocker', 'rankwriter-ai' ); ?></a>
			</h2>
			<p>
				<?php if ( empty( $bb['enabled'] ) ) : ?>
					<span class="rwai-pill rwai-pill-warn"><?php esc_html_e( 'OFF', 'rankwriter-ai' ); ?></span>
				<?php else : ?>
					<span class="rwai-pill rwai-pill-ok"><?php esc_html_e( 'ON', 'rankwriter-ai' ); ?></span>
				<?php endif; ?>
				<?php
				printf(
					/* translators: 1: number of countries, 2: number of IPs, 3: blocked visits in the last 24h, 4: "allowed" or "blocked" */
					esc_html__( '%1$d %4$s countries · %2$d blocked IP/CIDR entries · %3$d blocked visits in the last 24h', 'rankwriter-ai' ),
					(int) $bb['country_count'],
					(int) $bb['ip_count'],
					(int) $bb['blocked_24h'],
					'whitelist' === $bb['mode'] ? esc_html__( 'allowed', 'rankwriter-ai' ) : esc_html__( 'blocked', 'rankwriter-ai' )
				);
				?>
			</p>
			<?php if ( ! empty( $bb['countries'] ) ) : ?>
				<p>
					<?php foreach ( $bb['countries'] as $code ) : ?>
						<span style="display:inline-block;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;padding:2px 8px;margin:0 6px 6px 0;font-size:12px;"><?php echo esc_html( RankWriter_AI_Bot_Blocker_DB::country_name( $code ) . ' (' . $code . ')' ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

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

	<?php
	// Content Gap Detector — surface the top 3 opportunities right on the
	// dashboard so users see actionable next-topic suggestions without
	// drilling into the Gap Detector page.
	if ( class_exists( 'RankWriter_AI_Gap_Detector' ) ) :
		$gap_audit = ( new RankWriter_AI_Gap_Detector() )->get_last_audit();
		$gap_top   = isset( $gap_audit['top_opportunities'] ) ? array_slice( (array) $gap_audit['top_opportunities'], 0, 3 ) : array();
		$gap_url   = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GAP_SLUG );
	?>
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Top content gaps', 'rankwriter-ai' ); ?>
			<a href="<?php echo esc_url( $gap_url ); ?>" class="page-title-action" style="float:right;"><?php esc_html_e( 'Open Gap Detector', 'rankwriter-ai' ); ?></a>
		</h2>
		<?php if ( empty( $gap_top ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'Run an audit on the Gap Detector page to surface ranked content opportunities.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<ul class="rwai-gap-mini-list">
				<?php foreach ( $gap_top as $g ) : ?>
					<li>
						<span class="rwai-pill rwai-pill-ok"><?php echo esc_html( (int) $g['opportunity_score'] ); ?></span>
						<strong><?php echo esc_html( $g['keyword'] ); ?></strong>
						<span class="rwai-muted"><?php echo esc_html( ucfirst( (string) $g['intent'] ) ); ?> · <?php echo esc_html( strtoupper( (string) $g['cpc_tier'] ) ); ?></span>
						<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $g['keyword'] ) ) ) ); ?>"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RankWriter_AI_Seasonal_Engine' ) ) :
		$se      = new RankWriter_AI_Seasonal_Engine();
		$niches  = $se->detect_niches();
		$top_evs = array_slice( $se->upcoming( 90, $niches, true ), 0, 3 );
		$cal_url = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SEASONAL_SLUG );
	?>
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Upcoming seasonal opportunities', 'rankwriter-ai' ); ?>
			<a href="<?php echo esc_url( $cal_url ); ?>" class="page-title-action" style="float:right;"><?php esc_html_e( 'Open calendar', 'rankwriter-ai' ); ?></a>
		</h2>
		<?php if ( empty( $top_evs ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No upcoming hot events in the next 90 days for your detected niches.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<ul class="rwai-gap-mini-list">
				<?php foreach ( $top_evs as $row ) :
					$heat = (int) $row['heat'];
					$pill = $heat >= 70 ? 'rwai-pill-bad' : ( $heat >= 40 ? 'rwai-pill-warn' : 'rwai-pill-ok' );
					$seed = $row['topic_suggestions'][0] ?? $row['event']['name'];
				?>
					<li>
						<span class="rwai-pill <?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $heat ); ?></span>
						<strong><?php echo esc_html( $row['event']['name'] ); ?></strong>
						<span class="rwai-muted"><?php echo esc_html( sprintf( __( 'in %1$d days · publish by %2$s · %3$s coverage', 'rankwriter-ai' ), $row['days_until_event'], $row['window']['ideal_publish'], (int) ( $row['coverage']['count'] ?? 0 ) === 0 ? __( 'no', 'rankwriter-ai' ) : (int) $row['coverage']['count'] ) ); ?></span>
						<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $seed ) ) ) ); ?>"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
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
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG ) ); ?>"><?php esc_html_e( 'Build topical authority clusters (pillar + supporting articles for topic silos)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PSE_SLUG ) ); ?>"><?php esc_html_e( 'Scale with Programmatic SEO — one template + a CSV of entities = hundreds of unique pages', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PINTEREST_SLUG ) ); ?>"><?php esc_html_e( 'Spin Pinterest pins from every article (title + description + hashtags + 1000×1500 image)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::TRANSLATIONS_SLUG ) ); ?>"><?php esc_html_e( 'Multi-language: translate posts (English / French / Spanish / German / Portuguese / Arabic)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::HUMANIZER_SLUG ) ); ?>"><?php esc_html_e( 'Humanization Lab: score any content for AI tells and rewrite it with a chosen tone + persona', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::VOICE_SLUG ) ); ?>"><?php esc_html_e( 'Brand Voice: calibrate tone + formatting memory so every new article matches your blog identity', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PARASITE_SLUG ) ); ?>"><?php esc_html_e( 'Parasite SEO: repurpose posts for Medium / LinkedIn / Quora / Reddit with platform-specific rewrites', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GAP_SLUG ) ); ?>"><?php esc_html_e( 'Gap Detector: audit competitor RSS + clusters + linking to surface ranked content opportunities', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::FACT_SLUG ) ); ?>"><?php esc_html_e( 'Fact Checker: validate dates, deadlines, visa info, stats, salary figures + official sources', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::RISK_SLUG ) ); ?>"><?php esc_html_e( 'Risk Detector: scan posts for medical / financial / immigration overclaims + AdSense compliance', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::HEALER_SLUG ) ); ?>"><?php esc_html_e( 'SEO Healer: background scanner finds + auto-repairs broken links, missing alts, missing meta, missing schema', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::REFRESH_SLUG ) ); ?>"><?php esc_html_e( 'Auto Update: refresh stale posts in the background — preserves URLs and rankings', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SCHEMA_SLUG ) ); ?>"><?php esc_html_e( 'Schema: auto-build JSON-LD @graph (Article + Breadcrumb + FAQ + Recipe + Job + Event + Review)', 'rankwriter-ai' ); ?></a></li>
			<li><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SEASONAL_SLUG ) ); ?>"><?php esc_html_e( 'Seasonal Trends: ride annual traffic spikes (Black Friday, tax season, back-to-school)', 'rankwriter-ai' ); ?></a></li>
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
