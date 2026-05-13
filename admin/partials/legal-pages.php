<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings = (array) $data['settings'];
$pages    = (array) $data['pages'];
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Blog Legal Pages', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'One-click generator for the mandatory legal pages every monetized blog needs. Each page is written by Claude using your business facts and the detected niche, then saved as a regular WordPress Page. AdSense reviewers expect About / Contact / Privacy at minimum.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'legal-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Legal settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'legal-generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Legal page generated.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'legal-all-generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All applicable legal pages generated.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'legal-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Generation failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Business facts (used in every legal page)', 'rankwriter-ai' ); ?></h2>
	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="save_legal_settings" />
		<?php wp_nonce_field( RankWriter_AI_Admin::LEGAL_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_lg_name"><?php esc_html_e( 'Business / site name', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" class="regular-text" id="rwai_lg_name" name="business_name" value="<?php echo esc_attr( $settings['business_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_lg_email"><?php esc_html_e( 'Contact email', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" id="rwai_lg_email" name="business_email" value="<?php echo esc_attr( $settings['business_email'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Used as the contact point in Contact / Privacy / Terms / DMCA pages.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_lg_addr"><?php esc_html_e( 'Business postal address (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<textarea id="rwai_lg_addr" name="business_address" class="large-text" rows="3"><?php echo esc_textarea( $settings['business_address'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_lg_juris"><?php esc_html_e( 'Legal jurisdiction (governing law)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="rwai_lg_juris" name="legal_jurisdiction" value="<?php echo esc_attr( $settings['legal_jurisdiction'] ); ?>" />
					<p class="description"><?php esc_html_e( 'e.g. "California, United States" or "Nigeria" or "England and Wales". Drives the governing-law clause + privacy framework (GDPR vs CCPA vs NDPR).', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_lg_op"><?php esc_html_e( 'Operator type', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_lg_op" name="operator_type">
						<option value="individual" <?php selected( $settings['operator_type'], 'individual' ); ?>><?php esc_html_e( 'Individual / sole operator', 'rankwriter-ai' ); ?></option>
						<option value="company" <?php selected( $settings['operator_type'], 'company' ); ?>><?php esc_html_e( 'Registered company / LLC', 'rankwriter-ai' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Site uses…', 'rankwriter-ai' ); ?></th>
				<td>
					<label style="display:block;margin:3px 0;"><input type="checkbox" name="uses_adsense" value="1" <?php checked( $settings['uses_adsense'] ); ?> /> <?php esc_html_e( 'Google AdSense advertising', 'rankwriter-ai' ); ?></label>
					<label style="display:block;margin:3px 0;"><input type="checkbox" name="uses_affiliate_links" value="1" <?php checked( $settings['uses_affiliate_links'] ); ?> /> <?php esc_html_e( 'Affiliate links (Amazon Associates, etc.)', 'rankwriter-ai' ); ?></label>
					<label style="display:block;margin:3px 0;"><input type="checkbox" name="uses_cookies" value="1" <?php checked( $settings['uses_cookies'] ); ?> /> <?php esc_html_e( 'Cookies (essential + tracking)', 'rankwriter-ai' ); ?></label>
					<label style="display:block;margin:3px 0;"><input type="checkbox" name="uses_analytics" value="1" <?php checked( $settings['uses_analytics'] ); ?> /> <?php esc_html_e( 'Google Analytics or equivalent', 'rankwriter-ai' ); ?></label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save business facts', 'rankwriter-ai' ); ?></button>
		</p>
	</form>

	<h2><?php esc_html_e( 'Legal pages', 'rankwriter-ai' ); ?></h2>
	<form method="post" style="margin-bottom:14px;">
		<input type="hidden" name="rwai_action" value="generate_all_legal_pages" />
		<?php wp_nonce_field( RankWriter_AI_Admin::LEGAL_NONCE ); ?>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate all applicable pages', 'rankwriter-ai' ); ?></button>
		<span class="rwai-muted"><?php esc_html_e( 'Skips Affiliate Disclosure / Cookie Policy if you\'ve disabled those toggles above.', 'rankwriter-ai' ); ?></span>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Page', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Last generated', 'rankwriter-ai' ); ?></th>
				<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $pages as $type => $row ) :
			$skip = ! empty( $row['config']['only_if'] ) && empty( $settings[ $row['config']['only_if'] ] );
		?>
			<tr>
				<td>
					<strong><?php echo esc_html( $row['config']['title'] ); ?></strong>
					<br><small class="rwai-muted"><?php echo esc_html( $row['config']['description'] ); ?></small>
				</td>
				<td>
					<?php if ( $skip ) : ?>
						<span class="rwai-pill rwai-pill-warn"><?php esc_html_e( 'Not needed (toggle off)', 'rankwriter-ai' ); ?></span>
					<?php elseif ( $row['id'] ) : ?>
						<span class="rwai-pill rwai-pill-ok"><?php esc_html_e( 'Live', 'rankwriter-ai' ); ?></span>
						<?php if ( ! empty( $row['config']['wp_privacy'] ) && (int) get_option( 'wp_page_for_privacy_policy' ) === $row['id'] ) : ?>
							<br><small><?php esc_html_e( 'Registered with WordPress as privacy policy.', 'rankwriter-ai' ); ?></small>
						<?php endif; ?>
					<?php else : ?>
						<span class="rwai-pill rwai-pill-bad"><?php esc_html_e( 'Not generated', 'rankwriter-ai' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $row['generated_at'] ? $row['generated_at'] : '—' ); ?></td>
				<td class="rwai-col-actions">
					<?php if ( ! $skip ) : ?>
						<form method="post" class="rwai-inline-form">
							<input type="hidden" name="rwai_action" value="generate_legal_page" />
							<input type="hidden" name="legal_type" value="<?php echo esc_attr( $type ); ?>" />
							<?php wp_nonce_field( RankWriter_AI_Admin::LEGAL_NONCE ); ?>
							<button type="submit" class="button button-small button-primary"><?php echo $row['id'] ? esc_html__( 'Regenerate', 'rankwriter-ai' ) : esc_html__( 'Generate', 'rankwriter-ai' ); ?></button>
						</form>
						<?php if ( $row['id'] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'rankwriter-ai' ); ?></a>
							<a class="button button-small" href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'rankwriter-ai' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description"><?php esc_html_e( 'Generated pages are reusable templates — not legal advice. Have a licensed attorney review the wording for your jurisdiction before relying on them in disputes.', 'rankwriter-ai' ); ?></p>
</div>
