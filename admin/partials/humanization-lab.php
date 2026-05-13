<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$msg = (string) $data['msg'];
$err = (string) $data['err'];

$strengths     = RankWriter_AI_Humanizer::strengths();
$tones         = RankWriter_AI_Humanizer::tones();
$personalities = RankWriter_AI_Humanizer::personalities();
$readability   = RankWriter_AI_Humanizer::readability_modes();

$defaults = RankWriter_AI_Humanizer::default_options();
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'AI Humanization Lab', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Score any content for AI tells (free, instant) — and rewrite it with a configurable strength, tone, persona, and readability target (one Claude call). All facts, numbers, HTML structure, and internal-link URLs are preserved; only the prose is rewritten.', 'rankwriter-ai' ); ?></p>

	<?php if ( '' !== $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '1. Paste content', 'rankwriter-ai' ); ?></h2>
		<textarea id="rwai-hum-input" class="large-text code" rows="14" placeholder="<?php esc_attr_e( 'Paste HTML or plain text…', 'rankwriter-ai' ); ?>"></textarea>
		<p>
			<button type="button" class="button" id="rwai-hum-analyze"><?php esc_html_e( '🔬 Analyze AI tells (instant)', 'rankwriter-ai' ); ?></button>
			<span class="rwai-muted" style="margin-left:8px;"><?php esc_html_e( 'No API call.', 'rankwriter-ai' ); ?></span>
		</p>
		<div id="rwai-hum-analysis"></div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '2. Configure rewrite', 'rankwriter-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai-hum-strength"><?php esc_html_e( 'Strength', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai-hum-strength">
						<?php foreach ( $strengths as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $defaults['strength'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Light = surface scrub. Medium = ~50% sentences rewritten. Aggressive = full sentence-by-sentence rebuild.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai-hum-tone"><?php esc_html_e( 'Tone', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai-hum-tone">
						<?php foreach ( $tones as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $defaults['tone'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai-hum-persona"><?php esc_html_e( 'Persona', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai-hum-persona">
						<?php foreach ( $personalities as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $defaults['personality'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai-hum-readability"><?php esc_html_e( 'Readability mode', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai-hum-readability">
						<?php foreach ( $readability as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $defaults['readability'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="rwai-hum-rewrite"><?php esc_html_e( '✨ Humanize (one Claude call)', 'rankwriter-ai' ); ?></button>
			<span id="rwai-hum-rewrite-status" class="rwai-status"></span>
		</p>
	</div>

	<div class="rwai-card rwai-card-wide" id="rwai-hum-output-card" style="display:none;">
		<h2><?php esc_html_e( '3. Rewritten content', 'rankwriter-ai' ); ?></h2>
		<div class="rwai-hum-output-grid">
			<div>
				<h3><?php esc_html_e( 'Before', 'rankwriter-ai' ); ?> <span id="rwai-hum-before-score" class="rwai-pill"></span></h3>
				<div id="rwai-hum-before" class="rwai-hum-preview"></div>
			</div>
			<div>
				<h3><?php esc_html_e( 'After', 'rankwriter-ai' ); ?> <span id="rwai-hum-after-score" class="rwai-pill"></span></h3>
				<div id="rwai-hum-after" class="rwai-hum-preview"></div>
				<p>
					<button type="button" class="button" id="rwai-hum-copy"><?php esc_html_e( '📋 Copy HTML', 'rankwriter-ai' ); ?></button>
					<span id="rwai-hum-copy-status" class="rwai-muted"></span>
				</p>
			</div>
		</div>
	</div>
</div>
