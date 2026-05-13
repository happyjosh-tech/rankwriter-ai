<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$templates = (array) $data['templates'];
$stats     = (array) $data['stats'];
$cfg       = (array) $data['queue_cfg'];
$next_run  = (string) $data['next_run'];
$msg       = (string) $data['msg'];
$err       = (string) $data['err'];
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Programmatic SEO Engine', 'rankwriter-ai' ); ?></h1>
	<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PSE_SLUG, array( 'new' => 1 ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'New template', 'rankwriter-ai' ); ?></a>
	<form method="post" class="rwai-inline-form" style="display:inline-block;margin-left:6px;">
		<input type="hidden" name="rwai_action" value="pse_restore_presets" />
		<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
		<button type="submit" class="page-title-action" onclick="return confirm('<?php echo esc_js( __( 'Re-seed any deleted starter templates? Custom templates are untouched.', 'rankwriter-ai' ) ); ?>');"><?php esc_html_e( 'Restore starter templates', 'rankwriter-ai' ); ?></button>
	</form>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Build many similar-but-unique SEO pages from a single template + a dataset of entity values. Each row gets a deterministic variant (intro angle, section order, FAQ subset) and a uniqueness check against siblings — so the engine scales coverage without producing doorway-page spam.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'pse-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pse-deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template deleted.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pse-imported' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html__( 'Imported %1$d new rows. Skipped %2$d duplicates.', 'rankwriter-ai' ),
				isset( $_GET['inserted'] ) ? (int) $_GET['inserted'] : 0,
				isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0 );
		?></p></div>
	<?php elseif ( 'pse-batch-done' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html__( 'Generated %1$d articles, %2$d failed.', 'rankwriter-ai' ),
				isset( $_GET['generated'] ) ? (int) $_GET['generated'] : 0,
				isset( $_GET['failed'] ) ? (int) $_GET['failed'] : 0 );
		?></p></div>
	<?php elseif ( 'pse-restored' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html__( 'Restored %d starter template(s).', 'rankwriter-ai' ),
				isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'pse-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<!-- ============== Global stats ============== -->
	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Templates', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['template_count'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Active', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['active_templates'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Total rows', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['total_rows'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Pending', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['pending'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Generated', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['generated'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Failed', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['failed'] ); ?></div>
		</div>
	</div>

	<!-- ============== Queue config ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Queue worker', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-form">
			<input type="hidden" name="rwai_action" value="pse_save_queue" />
			<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwai_pse_enabled"><?php esc_html_e( 'Enable automatic queue worker', 'rankwriter-ai' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="rwai_pse_enabled" name="enabled" value="1" <?php checked( $cfg['enabled'] ); ?> />
						<?php esc_html_e( 'Auto-generate pending rows on a schedule.', 'rankwriter-ai' ); ?></label>
						<?php if ( $next_run ) : ?>
							<p class="description"><?php printf( esc_html__( 'Next run: %s', 'rankwriter-ai' ), esc_html( $next_run ) ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_pse_freq"><?php esc_html_e( 'Frequency', 'rankwriter-ai' ); ?></label></th>
					<td>
						<select id="rwai_pse_freq" name="frequency">
							<?php foreach ( array( 'hourly' => 'Every hour', 'twicedaily' => 'Twice daily', 'daily' => 'Daily', 'weekly' => 'Weekly' ) as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cfg['frequency'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_pse_batch"><?php esc_html_e( 'Batch size', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" min="1" max="10" id="rwai_pse_batch" name="batch_size" value="<?php echo esc_attr( $cfg['batch_size'] ); ?>" />
						<p class="description"><?php esc_html_e( 'How many pages to generate per tick (1-10). Higher values produce more pages per hour but increase API cost.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save queue settings', 'rankwriter-ai' ); ?></button>
			</p>
		</form>

		<form method="post" class="rwai-inline-form" style="display:inline-block;margin-top:6px;">
			<input type="hidden" name="rwai_action" value="pse_run_now" />
			<input type="hidden" name="batch_size" value="<?php echo esc_attr( $cfg['batch_size'] ); ?>" />
			<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
			<button type="submit" class="button"><?php
				/* translators: %d: batch size */
				printf( esc_html__( '⚡ Run next %d now', 'rankwriter-ai' ), (int) $cfg['batch_size'] );
			?></button>
		</form>
	</div>

	<!-- ============== Templates list ============== -->
	<?php if ( empty( $templates ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<p><?php esc_html_e( 'No templates yet. Click "Restore starter templates" above to seed the 5 built-in recipes, or click "New template" to build your own.', 'rankwriter-ai' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped rwai-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Template', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Rows', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Generated', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Pending', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Failed', 'rankwriter-ai' ); ?></th>
					<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $templates as $t ) :
				$counts    = $t['counts'];
				$edit_url  = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PSE_SLUG, array( 'template' => $t['id'] ) );
				$is_paused = 'paused' === $t['status'];
			?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $t['name'] ); ?></a></strong>
						<br><small class="rwai-muted"><?php echo esc_html( $t['title_template'] ); ?></small>
					</td>
					<td><span class="rwai-intent-badge rwai-intent-<?php echo esc_attr( $t['intent'] ); ?>"><?php echo esc_html( ucfirst( $t['intent'] ) ); ?></span></td>
					<td><span class="rwai-pill <?php echo $is_paused ? 'rwai-pill-warn' : 'rwai-pill-ok'; ?>"><?php echo esc_html( $t['status'] ); ?></span></td>
					<td><?php echo esc_html( $counts['total'] ); ?></td>
					<td><?php echo esc_html( $counts['by_status']['generated'] ); ?></td>
					<td><?php echo esc_html( $counts['by_status']['pending'] ); ?></td>
					<td><?php echo esc_html( $counts['by_status']['failed'] ); ?></td>
					<td class="rwai-col-actions">
						<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Open', 'rankwriter-ai' ); ?></a>
						<form method="post" class="rwai-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this template? The dataset rows are deleted too; existing posts are not touched.', 'rankwriter-ai' ) ); ?>');">
							<input type="hidden" name="rwai_action" value="pse_delete_template" />
							<input type="hidden" name="template_id" value="<?php echo esc_attr( $t['id'] ); ?>" />
							<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
