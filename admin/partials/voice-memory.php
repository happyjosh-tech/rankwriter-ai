<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$profile = (array) ( $data['profile'] ?? array() );
$tones   = (array) ( $data['tones'] ?? array() );
$presets = (array) ( $data['presets'] ?? array() );
$cats    = (array) ( $data['categories'] ?? array() );
$msg     = (string) ( $data['msg'] ?? '' );
$err     = (string) ( $data['err'] ?? '' );

$fmt = (array) ( $profile['fmt'] ?? array() );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Brand Voice Memory', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />
	<p class="rwai-lede"><?php esc_html_e( 'A unified voice profile assembled from your Blog Style Profile, Humanizer settings, and a sliding-window memory of how recently published posts actually read. Every new generation inherits this voice.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'voice-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Voice profile saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'voice-calibrated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Calibration complete — memory has been rebuilt from your published posts.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'voice-preset-applied' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Preset applied. New articles will follow this voice.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'voice-reset' === $msg ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Voice memory cleared.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'voice-error' === $msg && $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Current primary tone', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:14px;">
				<span class="rwai-pill rwai-pill-ok"><?php echo esc_html( $tones[ $profile['primary_tone'] ]['label'] ?? $profile['primary_tone'] ); ?></span>
			</div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Posts observed', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) ( $fmt['samples_observed'] ?? 0 ) ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Applied preset', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;">
				<?php echo esc_html( $profile['applied_preset'] ? ( $tones[ $profile['applied_preset'] ]['label'] ?? $profile['applied_preset'] ) : '—' ); ?>
			</div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Last calibrated', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;">
				<?php echo esc_html( $profile['last_calibrated'] ? mysql2date( get_option( 'date_format' ), $profile['last_calibrated'] ) : '—' ); ?>
			</div>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Voice presets', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Pick a preset to lock the brand voice. Applying a preset also mirrors compatible settings into the Humanizer so every generation path stays in sync.', 'rankwriter-ai' ); ?></p>
		<div class="rwai-grid rwai-grid-3">
			<?php foreach ( $tones as $key => $info ) :
				$is_active = ( $profile['applied_preset'] === $key ) || ( ! $profile['applied_preset'] && $profile['primary_tone'] === $key );
			?>
				<div class="rwai-card" style="<?php echo $is_active ? 'border:2px solid #2271b1;' : ''; ?>">
					<h3 style="margin-top:0;"><?php echo esc_html( $info['label'] ); ?>
						<?php if ( $is_active ) : ?>
							<span class="rwai-pill rwai-pill-ok" style="float:right;"><?php esc_html_e( 'ACTIVE', 'rankwriter-ai' ); ?></span>
						<?php endif; ?>
					</h3>
					<p class="rwai-muted" style="font-size:13px;"><?php echo esc_html( $info['summary'] ); ?></p>
					<form method="post" style="margin-top:8px;">
						<input type="hidden" name="rwai_action" value="voice_apply_preset" />
						<input type="hidden" name="preset" value="<?php echo esc_attr( $key ); ?>" />
						<?php wp_nonce_field( RankWriter_AI_Admin::VOICE_NONCE ); ?>
						<button type="submit" class="button button-small <?php echo $is_active ? '' : 'button-primary'; ?>"><?php echo esc_html( $is_active ? __( 'Re-apply', 'rankwriter-ai' ) : __( 'Apply preset', 'rankwriter-ai' ) ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Brand identity', 'rankwriter-ai' ); ?></h2>
		<form method="post">
			<input type="hidden" name="rwai_action" value="voice_save_brand" />
			<?php wp_nonce_field( RankWriter_AI_Admin::VOICE_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_voice_tagline"><?php esc_html_e( 'Brand tagline / north star', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="text" id="rwai_voice_tagline" name="rwai_voice[brand_tagline]" class="regular-text" value="<?php echo esc_attr( $profile['brand_tagline'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Practical money guidance for first-generation savers.', 'rankwriter-ai' ); ?>" />
						<p class="description"><?php esc_html_e( 'One short sentence Claude can use to keep every article on-brand.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_voice_pillars"><?php esc_html_e( 'Editorial pillars (one per line)', 'rankwriter-ai' ); ?></label></th>
					<td><textarea id="rwai_voice_pillars" name="rwai_voice[brand_pillars]" rows="4" class="large-text"><?php echo esc_textarea( $profile['brand_pillars'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Core themes your blog returns to. Used to keep articles thematically coherent.', 'rankwriter-ai' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="rwai_voice_avoid"><?php esc_html_e( 'Words / framings to avoid', 'rankwriter-ai' ); ?></label></th>
					<td><textarea id="rwai_voice_avoid" name="rwai_voice[brand_avoid]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. don\'t use hype words, don\'t address readers as "guys"', 'rankwriter-ai' ); ?>"><?php echo esc_textarea( $profile['brand_avoid'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-learn from new posts', 'rankwriter-ai' ); ?></th>
					<td>
						<label><input type="checkbox" name="rwai_voice[auto_learn]" value="1" <?php checked( $profile['auto_learn'] ); ?> /> <?php esc_html_e( 'Update formatting memory each time a post is published.', 'rankwriter-ai' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save brand identity', 'rankwriter-ai' ) ); ?>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Formatting memory', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $fmt['samples_observed'] ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No samples observed yet — click "Calibrate now" to seed memory from your existing posts.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<div class="rwai-grid rwai-grid-3">
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Avg paragraph (words)', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $fmt['avg_paragraph_words'] ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Avg sentence (words)', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $fmt['avg_sentence_words'] ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'H2 / H3 per article', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value" style="font-size:15px;"><?php echo esc_html( (int) $fmt['h2_per_article'] . ' / ' . (int) $fmt['h3_per_article'] ); ?></div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'List usage rate', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) round( ( $fmt['list_usage_rate'] ?? 0 ) * 100 ) ); ?>%</div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'First-person frequency', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( number_format_i18n( ( $fmt['first_person_rate'] ?? 0 ) * 100, 1 ) ); ?>%</div>
				</div>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Samples observed', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $fmt['samples_observed'] ); ?></div>
				</div>
			</div>
		<?php endif; ?>

		<form method="post" style="margin-top:14px;">
			<input type="hidden" name="rwai_action" value="voice_calibrate" />
			<?php wp_nonce_field( RankWriter_AI_Admin::VOICE_NONCE ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( '🎯 Calibrate now (rescan last 25 posts)', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Recent tone history', 'rankwriter-ai' ); ?></h2>
		<?php $history = array_reverse( (array) ( $profile['tone_history'] ?? array() ) ); ?>
		<?php if ( empty( $history ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No published posts have been observed yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'Detected tone', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'When', 'rankwriter-ai' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $history, 0, 15 ) as $row ) :
					$pid = (int) $row['post_id'];
					$tlabel = $tones[ $row['tone'] ]['label'] ?? $row['tone'];
				?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '#' . $pid ); ?></a></td>
						<td><span class="rwai-pill rwai-pill-ok"><?php echo esc_html( $tlabel ); ?></span></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['detected_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Category-specific voice', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Optional. Override the primary tone for individual WordPress categories — useful when one section of your blog should sound different from the rest.', 'rankwriter-ai' ); ?></p>
		<form method="post">
			<input type="hidden" name="rwai_action" value="voice_save_category" />
			<?php wp_nonce_field( RankWriter_AI_Admin::VOICE_NONCE ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Category', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Tone override', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Note (passed to Claude)', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $cats as $cat ) :
					$cid = (int) $cat->term_id;
					$ov  = $profile['category_overrides'][ $cid ] ?? array();
				?>
					<tr>
						<td><strong><?php echo esc_html( $cat->name ); ?></strong><br><span class="rwai-muted"><?php echo esc_html( $cat->slug ); ?></span></td>
						<td>
							<select name="rwai_voice_cat[<?php echo esc_attr( $cid ); ?>][tone]">
								<option value=""><?php esc_html_e( '— Use primary tone —', 'rankwriter-ai' ); ?></option>
								<?php foreach ( $tones as $key => $info ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( ( $ov['tone'] ?? '' ), $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="rwai_voice_cat[<?php echo esc_attr( $cid ); ?>][note]" class="regular-text" value="<?php echo esc_attr( $ov['note'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'optional — e.g. "More formal in this section, cite primary sources."', 'rankwriter-ai' ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save category overrides', 'rankwriter-ai' ) ); ?>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Reset memory', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Wipes the entire voice profile (tone, formatting memory, presets, category overrides). Existing posts are untouched.', 'rankwriter-ai' ); ?></p>
		<form method="post" onsubmit="return confirm('<?php echo esc_attr( __( "Reset voice memory? Existing posts are not affected, but the brand voice profile will be wiped.", 'rankwriter-ai' ) ); ?>');">
			<input type="hidden" name="rwai_action" value="voice_reset" />
			<?php wp_nonce_field( RankWriter_AI_Admin::VOICE_NONCE ); ?>
			<button type="submit" class="button" style="color:#b32d2e;"><?php esc_html_e( '⚠️ Reset voice memory', 'rankwriter-ai' ); ?></button>
		</form>
	</div>
</div>
