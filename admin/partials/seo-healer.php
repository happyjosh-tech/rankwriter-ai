<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings   = (array) ( $data['settings'] ?? array() );
$counts     = (array) ( $data['counts'] ?? array() );
$severity   = (array) ( $data['severity_totals'] ?? array() );
$total      = (int) ( $data['total_open'] ?? 0 );
$issues     = (array) ( $data['issues'] ?? array() );
$issue_extras = (array) ( $data['issue_extras'] ?? array() );
$repairs    = (array) ( $data['repairs'] ?? array() );
$health     = (int) ( $data['health_score'] ?? 100 );
$next_scan  = (int) ( $data['next_scan'] ?? 0 );
$next_fix   = (int) ( $data['next_fix'] ?? 0 );
$repaired_24h = (int) ( $data['repaired_24h'] ?? 0 );
$msg        = (string) ( $data['msg'] ?? '' );
$err        = (string) ( $data['err'] ?? '' );

$rule_labels = array(
	'broken_internal_link'       => __( 'Broken internal links', 'rankwriter-ai' ),
	'missing_meta_description'   => __( 'Missing meta descriptions', 'rankwriter-ai' ),
	'missing_alt_text'           => __( 'Missing alt text', 'rankwriter-ai' ),
	'orphan_post'                => __( 'Orphan pages', 'rankwriter-ai' ),
	'thin_content'               => __( 'Thin content', 'rankwriter-ai' ),
	'duplicate_title'            => __( 'Duplicate titles', 'rankwriter-ai' ),
	'duplicate_meta_description' => __( 'Duplicate meta descriptions', 'rankwriter-ai' ),
	'missing_schema'             => __( 'Missing schema', 'rankwriter-ai' ),
	'weak_headings'              => __( 'Weak headings', 'rankwriter-ai' ),
	'outdated_seo_settings'      => __( 'Outdated SEO settings', 'rankwriter-ai' ),
);

if ( ! function_exists( 'rwai_healer_sev_pill' ) ) {
	function rwai_healer_sev_pill( $sev ) {
		switch ( $sev ) {
			case 'critical': return 'rwai-pill-bad';
			case 'error':    return 'rwai-pill-bad';
			case 'warning':  return 'rwai-pill-warn';
			default:         return 'rwai-pill-ok';
		}
	}
}

$health_band = $health >= 80 ? 'rwai-tl-bar-ok' : ( $health >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Self-Healing SEO Engine', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />
	<p class="rwai-lede"><?php esc_html_e( 'Background scanner walks your post catalog in small cursor-based batches every hour and auto-repairs the safe issues (missing alt text, missing meta descriptions, missing schema). Every repair is logged with a before/after snapshot so you can roll back in one click.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'healer-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'healer-fixed' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Issue repaired.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'healer-rolled' === $msg ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Repair rolled back.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'healer-scanned' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Manual scan complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'healer-dismissed' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Issue dismissed.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'healer-error' === $msg && $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'SEO health score', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $health ); ?>/100</div>
			<div class="rwai-tl-bar-track" style="margin-top:6px;"><span class="rwai-tl-bar-fill <?php echo esc_attr( $health_band ); ?>" style="width:<?php echo esc_attr( $health ); ?>%"></span></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Open issues', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $total ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Auto-repairs (last 24h)', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $repaired_24h . ' / ' . (int) $settings['daily_fix_quota'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Next scan tick', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;"><?php echo esc_html( $next_scan ? human_time_diff( time(), $next_scan ) : '—' ); ?></div>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Issues by category', 'rankwriter-ai' ); ?>
			<form method="post" style="float:right;margin-top:-4px;">
				<input type="hidden" name="rwai_action" value="healer_scan_now" />
				<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
				<button type="submit" class="button button-small"><?php esc_html_e( '↻ Scan next batch now', 'rankwriter-ai' ); ?></button>
			</form>
		</h2>
		<?php if ( empty( $counts ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No issues recorded yet — the scanner will populate this after the first cron tick. Click "Scan next batch now" to seed.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Issue type', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Critical', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Error', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Warning', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Info', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Total', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rule_labels as $rule => $label ) :
					$row = $counts[ $rule ] ?? array();
				?>
					<tr>
						<td>
							<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::HEALER_SLUG, array( 'rule' => $rule ) ) ); ?>"><strong><?php echo esc_html( $label ); ?></strong></a>
							<br><span class="rwai-muted"><code><?php echo esc_html( $rule ); ?></code></span>
						</td>
						<td><?php echo esc_html( (int) ( $row['critical'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (int) ( $row['error']    ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (int) ( $row['warning']  ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (int) ( $row['info']     ?? 0 ) ); ?></td>
						<td><strong><?php echo esc_html( (int) ( $row['total'] ?? 0 ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2>
			<?php
			$rule_filter = isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '';
			echo esc_html( $rule_filter ? sprintf( __( 'Open issues — %s', 'rankwriter-ai' ), $rule_labels[ $rule_filter ] ?? $rule_filter ) : __( 'Open issues', 'rankwriter-ai' ) );
			?>
			<?php if ( $rule_filter ) : ?>
				<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::HEALER_SLUG ) ); ?>" class="button button-small" style="float:right;"><?php esc_html_e( 'Clear filter', 'rankwriter-ai' ); ?></a>
			<?php endif; ?>
		</h2>
		<?php if ( empty( $issues ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No open issues match.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Severity', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Issue', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Detected', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $issues as $issue ) :
					$pid    = (int) $issue['post_id'];
					$rule   = $issue['rule'];
					$label  = $rule_labels[ $rule ] ?? $rule;
					$ctx    = json_decode( (string) $issue['context_json'], true );
					$broken_links = ( 'broken_internal_link' === $rule && is_array( $ctx ) && ! empty( $ctx['broken'] ) ) ? (array) $ctx['broken'] : array();
					$extras    = (array) ( $issue_extras[ (int) $issue['id'] ] ?? array() );
					$alt_srcs  = ( 'missing_alt_text' === $rule && is_array( $ctx ) && ! empty( $ctx['src_samples'] ) ) ? (array) $ctx['src_samples'] : array();
					$orph_cands = (array) ( $extras['orphan_candidates'] ?? array() );
					$cur_title = (string) ( $extras['current_title'] ?? '' );
					$cur_meta  = (string) ( $extras['current_meta_desc'] ?? '' );
					$has_subform = (
						in_array( $rule, array( 'missing_meta_description', 'duplicate_meta_description', 'duplicate_title', 'outdated_seo_settings', 'thin_content', 'weak_headings' ), true )
						|| ! empty( $broken_links )
						|| ( 'missing_alt_text' === $rule && ! empty( $alt_srcs ) )
						|| ( 'orphan_post' === $rule && ! empty( $orph_cands ) )
					);
				?>
					<tr>
						<td><span class="rwai-pill <?php echo esc_attr( rwai_healer_sev_pill( $issue['severity'] ) ); ?>"><?php echo esc_html( strtoupper( $issue['severity'] ) ); ?></span></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><strong><?php echo esc_html( wp_trim_words( get_the_title( $pid ) ?: ( '#' . $pid ), 10 ) ); ?></strong></a>
							<br><span class="rwai-muted"><?php echo esc_html( $label ); ?></span>
						</td>
						<td><?php echo esc_html( (string) $issue['message'] ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $issue['detected_at'] ) ); ?></td>
						<td>
							<?php if ( ! empty( $issue['auto_fixable'] ) ) : ?>
								<form method="post" style="display:inline-block;">
									<input type="hidden" name="rwai_action" value="healer_fix_issue" />
									<input type="hidden" name="issue_id" value="<?php echo esc_attr( $issue['id'] ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( '🔧 Auto-fix', 'rankwriter-ai' ); ?></button>
								</form>
							<?php elseif ( ! empty( $broken_links ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit post', 'rankwriter-ai' ); ?></a>
							<?php else : ?>
								<span class="rwai-muted"><?php esc_html_e( 'manual', 'rankwriter-ai' ); ?></span>
							<?php endif; ?>
							<form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('<?php echo esc_attr( __( "Dismiss this issue? It will reappear on the next scan if it's still present in the post.", 'rankwriter-ai' ) ); ?>');">
								<input type="hidden" name="rwai_action" value="healer_dismiss_issue" />
								<input type="hidden" name="issue_id" value="<?php echo esc_attr( $issue['id'] ); ?>" />
								<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
								<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Already fixed? Dismiss to clear the notification.', 'rankwriter-ai' ); ?>"><?php esc_html_e( 'Dismiss', 'rankwriter-ai' ); ?></button>
							</form>
						</td>
					</tr>
					<?php if ( ! empty( $broken_links ) ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;padding:0;">
								<table style="width:100%;border-collapse:collapse;font-size:13px;">
									<thead>
										<tr style="background:#f0f0f1;">
											<th style="padding:8px 12px;text-align:left;width:24%;"><?php esc_html_e( 'Anchor text', 'rankwriter-ai' ); ?></th>
											<th style="padding:8px 12px;text-align:left;width:32%;"><?php esc_html_e( 'Broken URL', 'rankwriter-ai' ); ?></th>
											<th style="padding:8px 12px;text-align:left;width:8%;"><?php esc_html_e( 'Count', 'rankwriter-ai' ); ?></th>
											<th style="padding:8px 12px;text-align:left;"><?php esc_html_e( 'Fix it', 'rankwriter-ai' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $broken_links as $bl ) :
										$bl_url    = (string) ( $bl['url'] ?? '' );
										$bl_anchor = (string) ( $bl['anchor_text'] ?? '' );
										$bl_count  = (int) ( $bl['occurrences'] ?? 1 );
									?>
										<tr style="border-top:1px solid #e5e7eb;">
											<td style="padding:10px 12px;"><strong><?php echo esc_html( $bl_anchor ); ?></strong></td>
											<td style="padding:10px 12px;"><code style="background:#f6f7f9;padding:2px 6px;border-radius:3px;word-break:break-all;"><?php echo esc_html( $bl_url ); ?></code></td>
											<td style="padding:10px 12px;"><?php echo esc_html( $bl_count ); ?></td>
											<td style="padding:10px 12px;">
												<form method="post" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;">
													<input type="hidden" name="rwai_action" value="healer_replace_broken_link" />
													<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
													<input type="hidden" name="old_url" value="<?php echo esc_attr( $bl_url ); ?>" />
													<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
													<input type="url" name="new_url" placeholder="<?php esc_attr_e( 'Replacement URL', 'rankwriter-ai' ); ?>" required style="min-width:240px;" />
													<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Replace', 'rankwriter-ai' ); ?></button>
												</form>
												<form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('<?php echo esc_attr( __( 'Remove this broken link from the post? The anchor text is kept.', 'rankwriter-ai' ) ); ?>');">
													<input type="hidden" name="rwai_action" value="healer_delete_broken_link" />
													<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
													<input type="hidden" name="old_url" value="<?php echo esc_attr( $bl_url ); ?>" />
													<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
													<button type="submit" class="button button-small" style="color:#b32d2e;"><?php esc_html_e( 'Delete link', 'rankwriter-ai' ); ?></button>
												</form>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Missing / duplicate meta description ─── */ ?>
					<?php if ( in_array( $rule, array( 'missing_meta_description', 'duplicate_meta_description' ), true ) ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;">
								<form method="post" style="display:flex;flex-direction:column;gap:6px;padding:10px 12px;">
									<input type="hidden" name="rwai_action" value="healer_save_meta_desc" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<input type="hidden" name="rule" value="<?php echo esc_attr( $rule ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<label style="font-size:12px;color:#555;"><?php esc_html_e( 'Write a custom meta description (110–160 chars is the sweet spot):', 'rankwriter-ai' ); ?></label>
									<textarea name="meta_desc" rows="2" maxlength="170" required style="width:100%;font-family:inherit;" placeholder="<?php esc_attr_e( 'Type the meta description for this post…', 'rankwriter-ai' ); ?>"><?php echo esc_textarea( $cur_meta ); ?></textarea>
									<div>
										<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Save meta description', 'rankwriter-ai' ); ?></button>
										<span class="rwai-muted" style="margin-left:8px;font-size:11px;"><?php esc_html_e( 'Or use the Auto-fix button above to let Claude write one.', 'rankwriter-ai' ); ?></span>
									</div>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Missing alt text — per-image inputs ─── */ ?>
					<?php if ( 'missing_alt_text' === $rule && ! empty( $alt_srcs ) ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;padding:0;">
								<form method="post" style="padding:10px 12px;">
									<input type="hidden" name="rwai_action" value="healer_save_alt_texts" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<div style="font-size:12px;color:#555;margin-bottom:6px;"><?php esc_html_e( 'Type alt text for each image (leave blank to skip):', 'rankwriter-ai' ); ?></div>
									<table style="width:100%;border-collapse:collapse;font-size:13px;">
										<?php foreach ( $alt_srcs as $src ) : ?>
											<tr>
												<td style="padding:4px 6px;width:42%;"><code style="background:#f6f7f9;padding:2px 6px;border-radius:3px;word-break:break-all;font-size:11px;"><?php echo esc_html( $src ); ?></code></td>
												<td style="padding:4px 6px;"><input type="text" name="alts[<?php echo esc_attr( $src ); ?>]" placeholder="<?php esc_attr_e( 'Alt text for this image', 'rankwriter-ai' ); ?>" style="width:100%;" /></td>
											</tr>
										<?php endforeach; ?>
									</table>
									<div style="margin-top:8px;">
										<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Save alt texts', 'rankwriter-ai' ); ?></button>
										<span class="rwai-muted" style="margin-left:8px;font-size:11px;"><?php esc_html_e( 'Or use Auto-fix to let Claude generate them.', 'rankwriter-ai' ); ?></span>
									</div>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Duplicate title ─── */ ?>
					<?php if ( 'duplicate_title' === $rule ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;">
								<form method="post" style="display:flex;gap:6px;align-items:center;padding:10px 12px;flex-wrap:wrap;">
									<input type="hidden" name="rwai_action" value="healer_save_title" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<label style="font-size:12px;color:#555;"><?php esc_html_e( 'New unique title:', 'rankwriter-ai' ); ?></label>
									<input type="text" name="new_title" value="<?php echo esc_attr( $cur_title ); ?>" required style="flex:1;min-width:280px;" />
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Save title', 'rankwriter-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Outdated SEO settings — title + meta inline ─── */ ?>
					<?php if ( 'outdated_seo_settings' === $rule ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;">
								<form method="post" style="display:flex;flex-direction:column;gap:8px;padding:10px 12px;">
									<input type="hidden" name="rwai_action" value="healer_save_title_and_meta" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<label style="font-size:12px;color:#555;"><?php esc_html_e( 'Title (≤ 60 chars is best):', 'rankwriter-ai' ); ?></label>
									<input type="text" name="new_title" value="<?php echo esc_attr( $cur_title ); ?>" maxlength="100" style="width:100%;" />
									<label style="font-size:12px;color:#555;"><?php esc_html_e( 'Meta description (110–160 chars):', 'rankwriter-ai' ); ?></label>
									<textarea name="meta_desc" rows="2" maxlength="170" style="width:100%;font-family:inherit;"><?php echo esc_textarea( $cur_meta ); ?></textarea>
									<div>
										<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Save title & meta', 'rankwriter-ai' ); ?></button>
										<span class="rwai-muted" style="margin-left:8px;font-size:11px;"><?php esc_html_e( 'Leave a field empty to skip it.', 'rankwriter-ai' ); ?></span>
									</div>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Orphan post — pick a source to link from ─── */ ?>
					<?php if ( 'orphan_post' === $rule && ! empty( $orph_cands ) ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;padding:10px 12px;">
								<div style="font-size:12px;color:#555;margin-bottom:6px;"><?php esc_html_e( 'Pick a related post — we\'ll add a "Related reading" link from it back to this orphan:', 'rankwriter-ai' ); ?></div>
								<form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
									<input type="hidden" name="rwai_action" value="healer_add_inbound_link" />
									<input type="hidden" name="orphan_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<select name="source_id" required style="min-width:340px;">
										<?php foreach ( $orph_cands as $cand ) : ?>
											<option value="<?php echo esc_attr( $cand['id'] ); ?>"><?php echo esc_html( wp_trim_words( $cand['title'], 14 ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Add inbound link', 'rankwriter-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Thin content — Claude expand ─── */ ?>
					<?php if ( 'thin_content' === $rule ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;">
								<form method="post" style="display:flex;gap:6px;align-items:center;padding:10px 12px;flex-wrap:wrap;" onsubmit="return confirm('<?php echo esc_attr( __( "Send this post to Claude to expand? The current content will be replaced with the expanded version (rollback available from the repair log).", 'rankwriter-ai' ) ); ?>');">
									<input type="hidden" name="rwai_action" value="healer_expand_thin" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<label style="font-size:12px;color:#555;"><?php esc_html_e( 'Expand to at least:', 'rankwriter-ai' ); ?></label>
									<input type="number" name="target_words" value="600" min="400" max="3000" step="50" style="width:90px;" />
									<span style="font-size:12px;color:#555;"><?php esc_html_e( 'words', 'rankwriter-ai' ); ?></span>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( '✨ Expand with Claude', 'rankwriter-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endif; ?>

					<?php /* ─── Weak headings — Claude restructure ─── */ ?>
					<?php if ( 'weak_headings' === $rule ) : ?>
						<tr>
							<td></td>
							<td colspan="4" style="background:#fbfbfc;">
								<form method="post" style="padding:10px 12px;" onsubmit="return confirm('<?php echo esc_attr( __( "Ask Claude to restructure this post's headings? The body will be rewritten with new H2/H3 tags (rollback available).", 'rankwriter-ai' ) ); ?>');">
									<input type="hidden" name="rwai_action" value="healer_rewrite_headings" />
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $pid ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( '✨ Restructure headings with Claude', 'rankwriter-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Repair log', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $repairs ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No repairs recorded yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'When', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Rule', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Source', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Note', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $repairs as $r ) :
					$rolled = ! empty( $r['rolled_back_at'] );
				?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['repaired_at'] ) ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( (int) $r['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $r['post_id'] ) ?: '#' . (int) $r['post_id'] ); ?></a></td>
						<td><code><?php echo esc_html( $r['rule'] ); ?></code></td>
						<td><?php echo esc_html( $r['source'] ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $r['notes'], 14 ) ); ?></td>
						<td>
							<?php if ( $rolled ) : ?>
								<span class="rwai-pill rwai-pill-warn"><?php esc_html_e( 'ROLLED BACK', 'rankwriter-ai' ); ?></span>
							<?php else : ?>
								<span class="rwai-pill rwai-pill-ok"><?php esc_html_e( 'APPLIED', 'rankwriter-ai' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! $rolled ) : ?>
								<form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_attr( __( 'Roll this repair back? The post will be restored to its pre-repair state.', 'rankwriter-ai' ) ); ?>');">
									<input type="hidden" name="rwai_action" value="healer_rollback" />
									<input type="hidden" name="log_id" value="<?php echo esc_attr( $r['id'] ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( '↶ Rollback', 'rankwriter-ai' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Scheduler & detection settings', 'rankwriter-ai' ); ?></h2>
		<form method="post">
			<input type="hidden" name="rwai_action" value="healer_save_settings" />
			<?php wp_nonce_field( RankWriter_AI_Admin::HEALER_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Enable healer', 'rankwriter-ai' ); ?></th>
					<td><label><input type="checkbox" name="rwai_healer[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> /> <?php esc_html_e( 'Run hourly scan + auto-fix ticks', 'rankwriter-ai' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="rwai_healer_batch"><?php esc_html_e( 'Posts per scan tick', 'rankwriter-ai' ); ?></label></th>
					<td><input type="number" id="rwai_healer_batch" name="rwai_healer[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="5" max="200" />
					<p class="description"><?php esc_html_e( 'Cursor-based — keeps cron fast regardless of total post count.', 'rankwriter-ai' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="rwai_healer_thin"><?php esc_html_e( 'Thin-content threshold (words)', 'rankwriter-ai' ); ?></label></th>
					<td><input type="number" id="rwai_healer_thin" name="rwai_healer[thin_threshold_words]" value="<?php echo esc_attr( $settings['thin_threshold_words'] ); ?>" min="100" max="2000" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-fix permissions', 'rankwriter-ai' ); ?></th>
					<td>
						<label style="display:block;"><input type="checkbox" name="rwai_healer[auto_fix_alt]" value="1" <?php checked( $settings['auto_fix_alt'] ); ?> /> <?php esc_html_e( 'Auto-fix missing alt text', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="rwai_healer[auto_fix_meta_desc]" value="1" <?php checked( $settings['auto_fix_meta_desc'] ); ?> /> <?php esc_html_e( 'Auto-fix missing meta descriptions', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="rwai_healer[auto_fix_schema]" value="1" <?php checked( $settings['auto_fix_schema'] ); ?> /> <?php esc_html_e( 'Auto-fix missing schema (rebuilds JSON-LD)', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="rwai_healer[use_claude_for_fixes]" value="1" <?php checked( $settings['use_claude_for_fixes'] ); ?> /> <?php esc_html_e( 'Use Claude for alt-text / meta-desc generation (better quality, uses API credit)', 'rankwriter-ai' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_healer_quota"><?php esc_html_e( 'Daily auto-fix quota', 'rankwriter-ai' ); ?></label></th>
					<td><input type="number" id="rwai_healer_quota" name="rwai_healer[daily_fix_quota]" value="<?php echo esc_attr( $settings['daily_fix_quota'] ); ?>" min="1" max="500" />
					<p class="description"><?php esc_html_e( 'Caps how many Claude-driven repairs run in 24h.', 'rankwriter-ai' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Enabled detection rules', 'rankwriter-ai' ); ?></th>
					<td>
					<?php foreach ( $rule_labels as $rule => $label ) :
						$checked = ! empty( $settings['rules_enabled'][ $rule ] );
					?>
						<label style="display:block;"><input type="checkbox" name="rwai_healer[rules_enabled][<?php echo esc_attr( $rule ); ?>]" value="1" <?php checked( $checked ); ?> /> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'rankwriter-ai' ) ); ?>
		</form>
	</div>
</div>
