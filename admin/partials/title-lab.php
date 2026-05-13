<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$msg = (string) $data['msg'];
$err = (string) $data['err'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Viral Title Intelligence — Title Lab', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Score any headline for emotional triggers, power words, clickbait risk, and per-platform CTR — or generate 15 fresh variants (3 each for SEO, Viral, Google Discover, Pinterest, and Social) from a topic.', 'rankwriter-ai' ); ?></p>

	<?php if ( '' !== $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<!-- ============== Generate variants ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '✨ Generate variants from a topic', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Enter your topic / working title. Claude returns 3 titles per style. Each variant is locally scored for CTR per platform and flagged for clickbait risk.', 'rankwriter-ai' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_tl_topic"><?php esc_html_e( 'Topic / working title', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" id="rwai_tl_topic" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. fully funded undergraduate scholarships in Canada for Nigerian students', 'rankwriter-ai' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_tl_intent"><?php esc_html_e( 'Intent hint (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_tl_intent">
						<option value=""><?php esc_html_e( 'Auto-detect', 'rankwriter-ai' ); ?></option>
						<option value="informational"><?php esc_html_e( 'Informational', 'rankwriter-ai' ); ?></option>
						<option value="commercial"><?php esc_html_e( 'Commercial', 'rankwriter-ai' ); ?></option>
						<option value="transactional"><?php esc_html_e( 'Transactional', 'rankwriter-ai' ); ?></option>
						<option value="navigational"><?php esc_html_e( 'Navigational', 'rankwriter-ai' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="button" class="button button-primary" id="rwai-tl-generate"><?php esc_html_e( 'Generate variants', 'rankwriter-ai' ); ?></button>
			<span id="rwai-tl-generate-status" class="rwai-status"></span>
		</p>

		<div id="rwai-tl-results"></div>
	</div>

	<!-- ============== Analyze any custom title ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '🔬 Analyze a custom title', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Paste any headline to see its emotional triggers, power words, clickbait flags, and CTR scores across all 5 platforms. Pure heuristic — no API call, instant.', 'rankwriter-ai' ); ?></p>

		<p>
			<input type="text" id="rwai-tl-analyze-input" class="large-text" placeholder="<?php esc_attr_e( 'Paste a title to analyze…', 'rankwriter-ai' ); ?>" />
		</p>
		<p class="submit">
			<button type="button" class="button" id="rwai-tl-analyze"><?php esc_html_e( 'Analyze', 'rankwriter-ai' ); ?></button>
		</p>
		<div id="rwai-tl-analyze-result"></div>
	</div>

	<!-- ============== Compare titles ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '⚖️ Compare titles side-by-side', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Enter 2-4 titles, one per line. See per-platform CTR scores ranked best to worst.', 'rankwriter-ai' ); ?></p>

		<p>
			<textarea id="rwai-tl-compare-input" class="large-text" rows="4" placeholder="<?php esc_attr_e( "Title 1\nTitle 2\nTitle 3", 'rankwriter-ai' ); ?>"></textarea>
		</p>
		<p class="submit">
			<button type="button" class="button" id="rwai-tl-compare"><?php esc_html_e( 'Compare', 'rankwriter-ai' ); ?></button>
		</p>
		<div id="rwai-tl-compare-result"></div>
	</div>
</div>

<!-- =============== Template for variant card =============== -->
<script type="text/template" id="rwai-tl-variant-template">
	<div class="rwai-tl-variant" data-title="{{TITLE}}">
		<div class="rwai-tl-variant-head">
			<div class="rwai-tl-variant-title">{{TITLE}}</div>
			<div class="rwai-tl-variant-meta">
				<span class="rwai-tl-meta-item">{{LENGTH}} chars</span>
				<span class="rwai-tl-overall rwai-tl-overall-{{OVERALL_CLASS}}">{{OVERALL}}/100</span>
			</div>
		</div>
		<div class="rwai-tl-variant-bars">{{PLATFORM_BARS}}</div>
		<div class="rwai-tl-variant-tags">{{TRIGGER_TAGS}}{{POWER_TAGS}}{{CLICKBAIT}}</div>
	</div>
</script>
