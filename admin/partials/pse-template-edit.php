<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$is_new   = (bool) $data['creating'];
$template = isset( $data['template'] ) ? (array) $data['template'] : array();
$profiles = (array) $data['profiles'];
$clusters = (array) $data['clusters'];
$rows     = (array) $data['rows'];
$counts   = isset( $data['counts'] ) ? (array) $data['counts'] : array( 'total' => 0, 'by_status' => array() );
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];

$id              = $is_new ? 0 : (int) $template['id'];
$name            = $is_new ? '' : (string) $template['name'];
$description     = $is_new ? '' : (string) $template['description'];
$title_template  = $is_new ? '' : (string) $template['title_template'];
$slug_template   = $is_new ? '' : (string) $template['slug_template'];
$intent          = $is_new ? 'informational' : (string) $template['intent'];
$semantic_kw     = $is_new ? '' : (string) $template['semantic_keywords'];
$profile_id      = $is_new ? 0 : (int) ( $template['profile_id'] ?: 0 );
$cluster_id      = $is_new ? 0 : (int) ( $template['cluster_id'] ?: 0 );
$status          = $is_new ? 'active' : (string) $template['status'];
$min_word_count  = $is_new ? 1400 : (int) $template['min_word_count'];
$min_uniqueness  = $is_new ? 70 : (int) $template['min_uniqueness'];
$outline_json    = $is_new ? '{}' : wp_json_encode( $template['outline'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$variables_json  = $is_new ? '{}' : wp_json_encode( $template['variables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>
<div class="wrap rwai-wrap">
	<h1><?php echo $is_new ? esc_html__( 'New programmatic template', 'rankwriter-ai' ) : esc_html( $name ); ?></h1>

	<?php if ( 'pse-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pse-imported' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html__( 'Imported %1$d new rows. Skipped %2$d duplicates.', 'rankwriter-ai' ),
				isset( $_GET['inserted'] ) ? (int) $_GET['inserted'] : 0,
				isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0 );
		?></p></div>
	<?php elseif ( 'pse-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<!-- ============== Template form ============== -->
	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="pse_save_template" />
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
		<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_pse_name"><?php esc_html_e( 'Template name', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" class="regular-text" id="rwai_pse_name" name="name" value="<?php echo esc_attr( $name ); ?>" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_title"><?php esc_html_e( 'Title template', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="rwai_pse_title" name="title_template" value="<?php echo esc_attr( $title_template ); ?>" placeholder="<?php esc_attr_e( 'e.g. Highest Paying {profession} Jobs in {city}', 'rankwriter-ai' ); ?>" required />
					<p class="description"><?php esc_html_e( 'Use {variable_name} placeholders. The Variables JSON below defines each one.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_slug"><?php esc_html_e( 'Slug template', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" class="large-text" id="rwai_pse_slug" name="slug_template" value="<?php echo esc_attr( $slug_template ); ?>" placeholder="highest-paying-{profession-slug}-jobs-in-{city-slug}" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_desc"><?php esc_html_e( 'Description', 'rankwriter-ai' ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="rwai_pse_desc" name="description"><?php echo esc_textarea( $description ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_intent"><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_pse_intent" name="intent">
						<option value="informational" <?php selected( $intent, 'informational' ); ?>>Informational</option>
						<option value="commercial" <?php selected( $intent, 'commercial' ); ?>>Commercial</option>
						<option value="transactional" <?php selected( $intent, 'transactional' ); ?>>Transactional</option>
						<option value="navigational" <?php selected( $intent, 'navigational' ); ?>>Navigational</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_profile"><?php esc_html_e( 'Category profile', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_pse_profile" name="profile_id">
						<option value="">— <?php esc_html_e( 'None', 'rankwriter-ai' ); ?> —</option>
						<?php foreach ( $profiles as $p ) : ?>
							<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $profile_id, $p['id'] ); ?>><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_cluster"><?php esc_html_e( 'Attach to cluster (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_pse_cluster" name="cluster_id">
						<option value="">— <?php esc_html_e( 'None', 'rankwriter-ai' ); ?> —</option>
						<?php foreach ( $clusters as $c ) : ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $cluster_id, $c['id'] ); ?>><?php echo esc_html( $c['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'All generated pages link back to the cluster\'s pillar and to sibling cluster posts.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_status"><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_pse_status" name="status">
						<option value="active" <?php selected( $status, 'active' ); ?>>Active</option>
						<option value="paused" <?php selected( $status, 'paused' ); ?>>Paused</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_minword"><?php esc_html_e( 'Min word count', 'rankwriter-ai' ); ?></label></th>
				<td><input type="number" min="600" max="8000" step="100" id="rwai_pse_minword" name="min_word_count" value="<?php echo esc_attr( $min_word_count ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_minunique"><?php esc_html_e( 'Min uniqueness (%)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="50" max="100" id="rwai_pse_minunique" name="min_uniqueness" value="<?php echo esc_attr( $min_uniqueness ); ?>" />
					<p class="description"><?php esc_html_e( 'Pages below this similarity threshold to siblings are flagged as failed for manual review. 70-80 is a good default.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_semkw"><?php esc_html_e( 'Semantic keywords', 'rankwriter-ai' ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="rwai_pse_semkw" name="semantic_keywords"><?php echo esc_textarea( $semantic_kw ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_vars"><?php esc_html_e( 'Variables (JSON)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="6" id="rwai_pse_vars" name="variables_json"><?php echo esc_textarea( $variables_json ); ?></textarea>
					<p class="description"><?php esc_html_e( 'JSON object. Each key is a variable name. Example: {"profession":{"required":true,"type":"string"},"city":{"required":true,"type":"string"}}', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pse_outline"><?php esc_html_e( 'Outline (JSON)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="20" id="rwai_pse_outline" name="outline_json"><?php echo esc_textarea( $outline_json ); ?></textarea>
					<p class="description"><?php esc_html_e( 'JSON with keys: intro_variants[], sections[], section_order_variants[], faq_pool[], conclusion_variants[]. See the starter templates for examples.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo $is_new ? esc_html__( 'Create template', 'rankwriter-ai' ) : esc_html__( 'Save template', 'rankwriter-ai' ); ?></button>
			<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PSE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Back', 'rankwriter-ai' ); ?></a>
		</p>
	</form>

	<?php if ( ! $is_new ) : ?>

	<!-- ============== Dataset import ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Dataset rows', 'rankwriter-ai' ); ?>
			<small class="rwai-muted"><?php
				printf( esc_html__( '%1$d total · %2$d pending · %3$d generated · %4$d failed', 'rankwriter-ai' ),
					(int) $counts['total'],
					(int) ( $counts['by_status']['pending'] ?? 0 ),
					(int) ( $counts['by_status']['generated'] ?? 0 ),
					(int) ( $counts['by_status']['failed'] ?? 0 )
				);
			?></small>
		</h2>

		<form method="post" class="rwai-form">
			<input type="hidden" name="rwai_action" value="pse_import_rows" />
			<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
			<p>
				<label for="rwai_pse_csv"><strong><?php esc_html_e( 'Paste CSV (header row + values)', 'rankwriter-ai' ); ?></strong></label>
			</p>
			<textarea class="large-text code" rows="6" id="rwai_pse_csv" name="csv" placeholder="profession,city,country,currency&#10;Nursing,Austin,United States,USD&#10;Nursing,Houston,United States,USD&#10;Software Engineer,Austin,United States,USD"></textarea>
			<p class="description"><?php esc_html_e( 'First line is the variable names. Each subsequent line becomes one dataset row. Duplicate rows (same values) are skipped automatically.', 'rankwriter-ai' ); ?></p>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import CSV', 'rankwriter-ai' ); ?></button>
			</p>
		</form>

		<h3><?php esc_html_e( 'Recent rows', 'rankwriter-ai' ); ?></h3>
		<?php if ( empty( $rows ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No rows yet. Import a CSV above.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Values', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Uniqueness', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'rankwriter-ai' ); ?></th>
						<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $r ) :
					$status_class = 'rwai-pill-warn';
					if ( 'generated' === $r['status'] ) {
						$status_class = 'rwai-pill-ok';
					} elseif ( in_array( $r['status'], array( 'failed', 'skipped' ), true ) ) {
						$status_class = 'rwai-pill-bad';
					}
				?>
					<tr>
						<td>
							<?php
							$bits = array();
							foreach ( $r['values'] as $k => $v ) {
								if ( '' === $v ) { continue; }
								$bits[] = '<code>' . esc_html( $k ) . '</code>: ' . esc_html( $v );
							}
							echo wp_kses_post( implode( '<br>', $bits ) );
							?>
						</td>
						<td><span class="rwai-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
						<td><?php echo $r['uniqueness_score'] !== null ? esc_html( $r['uniqueness_score'] . '%' ) : '—'; ?></td>
						<td>
							<?php if ( ! empty( $r['post_id'] ) && get_post( $r['post_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $r['post_id'] ) ); ?></a>
							<?php else : ?>
								<span class="rwai-muted">—</span>
							<?php endif; ?>
						</td>
						<td><small class="rwai-muted"><?php echo esc_html( $r['error_message'] ?: '' ); ?></small></td>
						<td class="rwai-col-actions">
							<?php if ( in_array( $r['status'], array( 'pending', 'failed' ), true ) ) : ?>
								<form method="post" class="rwai-inline-form">
									<input type="hidden" name="rwai_action" value="pse_generate_row" />
									<input type="hidden" name="row_id" value="<?php echo esc_attr( $r['id'] ); ?>" />
									<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::PSE_NONCE ); ?>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" class="rwai-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this row?', 'rankwriter-ai' ) ); ?>');">
								<input type="hidden" name="rwai_action" value="pse_delete_row" />
								<input type="hidden" name="row_id" value="<?php echo esc_attr( $r['id'] ); ?>" />
								<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>" />
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
	<?php endif; ?>
</div>
