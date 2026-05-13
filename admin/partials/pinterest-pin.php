<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$pin    = (array) $data['pin'];
$msg    = (string) $data['msg'];
$err    = (string) $data['err'];
$niches = RankWriter_AI_Pinterest_Engine::supported_niches();

$image_url = $pin['image_attachment_id'] ? wp_get_attachment_image_url( $pin['image_attachment_id'], 'full' ) : '';
$source_post = ! empty( $pin['post_id'] ) ? get_post( $pin['post_id'] ) : null;
?>
<div class="wrap rwai-wrap">
	<h1>
		<?php esc_html_e( 'Pin:', 'rankwriter-ai' ); ?>
		<?php echo esc_html( wp_trim_words( $pin['title'], 14 ) ); ?>
		<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PINTEREST_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( '← All pins', 'rankwriter-ai' ); ?></a>
	</h1>

	<?php if ( 'pin-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pin saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pin-image' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pin image rendered.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pin-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-pin-detail-grid">

		<!-- ============== Pin image ============== -->
		<div class="rwai-pin-detail-image-col">
			<?php if ( $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="rwai-pin-detail-image" />
				<p>
					<a class="button" href="<?php echo esc_url( $image_url ); ?>" download><?php esc_html_e( '⬇ Download image', 'rankwriter-ai' ); ?></a>
				</p>
			<?php else : ?>
				<div class="rwai-pin-detail-image rwai-pin-card-placeholder" style="aspect-ratio:2/3;display:flex;align-items:center;justify-content:center;font-size:80px;">📌</div>
			<?php endif; ?>

			<form method="post" class="rwai-inline-form" style="margin-top:8px;">
				<input type="hidden" name="rwai_action" value="pin_render_image" />
				<input type="hidden" name="pin_id" value="<?php echo esc_attr( $pin['id'] ); ?>" />
				<?php wp_nonce_field( RankWriter_AI_Admin::PINTEREST_NONCE ); ?>
				<button type="submit" class="button button-primary"><?php echo $image_url ? esc_html__( '🔄 Re-render image', 'rankwriter-ai' ) : esc_html__( '🎨 Render 1000×1500 image', 'rankwriter-ai' ); ?></button>
			</form>
			<?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
				<p class="rwai-muted" style="color:#b32d2e;font-size:12px;margin-top:6px;"><?php esc_html_e( 'PHP GD not available — image rendering disabled.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- ============== Pin metadata ============== -->
		<div class="rwai-pin-detail-meta-col">
			<form method="post" class="rwai-form">
				<input type="hidden" name="rwai_action" value="pin_save" />
				<input type="hidden" name="pin_id" value="<?php echo esc_attr( $pin['id'] ); ?>" />
				<?php wp_nonce_field( RankWriter_AI_Admin::PINTEREST_NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwai_pin_title_field"><?php esc_html_e( 'Pin title', 'rankwriter-ai' ); ?></label></th>
						<td><input type="text" class="large-text" id="rwai_pin_title_field" name="title" value="<?php echo esc_attr( $pin['title'] ); ?>" maxlength="100" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_overlay"><?php esc_html_e( 'Overlay text (big bold)', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="rwai_pin_overlay" name="overlay_text" value="<?php echo esc_attr( $pin['overlay_text'] ); ?>" maxlength="80" />
							<p class="description"><?php esc_html_e( '3-7 words. This is what readers see on the pin image in 1 second.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_overlay_sec"><?php esc_html_e( 'Secondary overlay (smaller)', 'rankwriter-ai' ); ?></label></th>
						<td><input type="text" class="large-text" id="rwai_pin_overlay_sec" name="overlay_secondary" value="<?php echo esc_attr( $pin['overlay_secondary'] ?? '' ); ?>" maxlength="80" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_description"><?php esc_html_e( 'Description', 'rankwriter-ai' ); ?></label></th>
						<td><textarea class="large-text code" rows="4" id="rwai_pin_description" name="description" maxlength="500"><?php echo esc_textarea( $pin['description'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_hashtags"><?php esc_html_e( 'Hashtags (one per line, no #)', 'rankwriter-ai' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="4" id="rwai_pin_hashtags" name="hashtags"><?php echo esc_textarea( implode( "\n", (array) $pin['hashtags'] ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_boards"><?php esc_html_e( 'Board suggestions', 'rankwriter-ai' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="3" id="rwai_pin_boards" name="board_suggestions"><?php echo esc_textarea( implode( "\n", (array) $pin['board_suggestions'] ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_image_prompt"><?php esc_html_e( 'Image prompt (for DALL-E / Midjourney)', 'rankwriter-ai' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="3" id="rwai_pin_image_prompt" name="image_prompt"><?php echo esc_textarea( (string) $pin['image_prompt'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Use this if you want a custom hero image. The pin image rendered above uses the post\'s featured image + overlay; this prompt is for replacing it with an AI-generated image.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_niche_sel"><?php esc_html_e( 'Niche', 'rankwriter-ai' ); ?></label></th>
						<td>
							<select id="rwai_pin_niche_sel" name="niche">
								<?php foreach ( $niches as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $pin['niche'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_status"><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></label></th>
						<td>
							<select id="rwai_pin_status" name="status">
								<option value="draft"     <?php selected( $pin['status'], 'draft' ); ?>>Draft</option>
								<option value="scheduled" <?php selected( $pin['status'], 'scheduled' ); ?>>Scheduled</option>
								<option value="ready"     <?php selected( $pin['status'], 'ready' ); ?>>Ready to post</option>
								<option value="posted"    <?php selected( $pin['status'], 'posted' ); ?>>Posted</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_sched"><?php esc_html_e( 'Scheduled at', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="datetime-local" id="rwai_pin_sched" name="scheduled_at" value="<?php echo esc_attr( $pin['scheduled_at'] ? mysql2date( 'Y-m-d\TH:i', $pin['scheduled_at'] ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'When the pin should flip to "Ready". An external automation listening to rwai_pinterest_pin_ready can then publish it to Pinterest.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwai_pin_url"><?php esc_html_e( 'Pin URL (after posting)', 'rankwriter-ai' ); ?></label></th>
						<td><input type="url" class="large-text" id="rwai_pin_url" name="pin_url" value="<?php echo esc_attr( $pin['pin_url'] ?? '' ); ?>" placeholder="https://pinterest.com/pin/…" /></td>
					</tr>
					<?php if ( $source_post ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Source post', 'rankwriter-ai' ); ?></th>
						<td><a href="<?php echo esc_url( get_edit_post_link( $source_post->ID ) ); ?>"><?php echo esc_html( $source_post->post_title ); ?></a></td>
					</tr>
					<?php endif; ?>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save pin', 'rankwriter-ai' ); ?></button>
					<form method="post" class="rwai-inline-form" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this pin?', 'rankwriter-ai' ) ); ?>');">
						<input type="hidden" name="rwai_action" value="pin_delete" />
						<input type="hidden" name="pin_id" value="<?php echo esc_attr( $pin['id'] ); ?>" />
						<?php wp_nonce_field( RankWriter_AI_Admin::PINTEREST_NONCE ); ?>
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
					</form>
				</p>
			</form>

			<!-- One-click "copy to clipboard" panel for the manual Pinterest workflow -->
			<div class="rwai-card">
				<h3><?php esc_html_e( 'Ready-to-paste Pinterest payload', 'rankwriter-ai' ); ?></h3>
				<p class="rwai-muted"><?php esc_html_e( 'Copy and paste into Pinterest\'s "Create Pin" dialog. (RankWriter AI doesn\'t post directly — Pinterest\'s API requires app review. Use this panel + an automation tool like Zapier listening to the rwai_pinterest_pin_ready hook for automated posting.)', 'rankwriter-ai' ); ?></p>
				<p><strong><?php esc_html_e( 'Title:', 'rankwriter-ai' ); ?></strong>
					<input type="text" class="large-text" readonly value="<?php echo esc_attr( $pin['title'] ); ?>" onclick="this.select();" />
				</p>
				<p><strong><?php esc_html_e( 'Description + hashtags:', 'rankwriter-ai' ); ?></strong>
					<?php
					$hash_string = '';
					foreach ( (array) $pin['hashtags'] as $h ) {
						$h = trim( ltrim( $h, '#' ) );
						if ( $h ) { $hash_string .= ' #' . str_replace( ' ', '', $h ); }
					}
					?>
					<textarea class="large-text code" rows="5" readonly onclick="this.select();"><?php echo esc_textarea( $pin['description'] . "\n\n" . trim( $hash_string ) ); ?></textarea>
				</p>
				<?php if ( ! empty( $pin['board_suggestions'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Suggested boards:', 'rankwriter-ai' ); ?></strong>
						<?php foreach ( (array) $pin['board_suggestions'] as $b ) : ?>
							<span class="rwai-tag"><?php echo esc_html( $b ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
