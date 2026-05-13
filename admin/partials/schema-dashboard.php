<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$audit  = (array) ( $data['audit'] ?? array() );
$org    = (array) ( $data['org'] ?? array() );
$msg    = (string) ( $data['msg'] ?? '' );
$seo_active = (string) ( $data['seo_plugin'] ?? 'none' );
$counts = (array) ( $audit['counts'] ?? array() );
$rows   = (array) ( $audit['rows'] ?? array() );
$problem_count = (int) ( $audit['problem_count'] ?? 0 );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Schema Automation', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />
	<p class="rwai-lede"><?php esc_html_e( 'JSON-LD structured data — auto-detected per post, validated against Google\'s required-field rules, rendered as a single combined @graph block. Suppressed automatically when Rank Math / Yoast / SEOPress is active so you never emit duplicate schema.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'schema-org-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Organization settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'schema-rebuilt' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Schema graph rebuilt for recent posts.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<?php if ( in_array( $seo_active, array( 'rank-math', 'yoast', 'seopress' ), true ) ) : ?>
		<div class="notice notice-warning"><p><?php
			printf(
				/* translators: %s: SEO plugin label */
				esc_html__( '%s is active and already outputs its own schema. RankWriter AI will NOT inject its JSON-LD on the frontend (to avoid duplicates). The graph is still saved per-post so you can preview/validate it here.', 'rankwriter-ai' ),
				'<strong>' . esc_html( $seo_active ) . '</strong>'
			);
		?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Posts audited', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo (int) count( $rows ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Posts with validation errors', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $problem_count ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Schema types in use', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo (int) count( $counts ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Renderer', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;">
				<?php echo in_array( $seo_active, array( 'rank-math', 'yoast', 'seopress' ), true )
					? '<span class="rwai-pill rwai-pill-warn">' . esc_html( $seo_active ) . '</span>'
					: '<span class="rwai-pill rwai-pill-ok">RankWriter</span>'; ?>
			</div>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Schema types in your library', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $counts ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No posts audited yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( '@type', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'Posts', 'rankwriter-ai' ); ?></th></tr></thead>
				<tbody>
				<?php arsort( $counts ); foreach ( $counts as $t => $n ) : ?>
					<tr>
						<td><code><?php echo esc_html( $t ); ?></code></td>
						<td><?php echo (int) $n; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<form method="post" style="margin-top:14px;">
			<input type="hidden" name="rwai_action" value="schema_rebuild_all" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SCHEMA_NONCE ); ?>
			<button type="submit" class="button"><?php esc_html_e( '⚙️ Rebuild graphs for last 100 posts', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Organization (sitewide)', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Identifies your publisher in every schema graph. Configure once — applies across all posts.', 'rankwriter-ai' ); ?></p>
		<form method="post">
			<input type="hidden" name="rwai_action" value="schema_save_org" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SCHEMA_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_org_name"><?php esc_html_e( 'Organization name', 'rankwriter-ai' ); ?></label></th>
					<td><input type="text" id="rwai_org_name" name="rwai_schema_org[name]" class="regular-text" value="<?php echo esc_attr( $org['name'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="rwai_org_logo"><?php esc_html_e( 'Logo URL', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="url" id="rwai_org_logo" name="rwai_schema_org[logo]" class="regular-text" value="<?php echo esc_attr( $org['logo'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Defaults to your Site Icon / Custom Logo if blank. Google asks for a square 600×60+ image.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_org_sameas"><?php esc_html_e( 'sameAs URLs (one per line)', 'rankwriter-ai' ); ?></label></th>
					<td><textarea id="rwai_org_sameas" name="rwai_schema_org[sameas]" rows="4" class="large-text" placeholder="https://twitter.com/yourbrand&#10;https://facebook.com/yourbrand"><?php echo esc_textarea( $org['sameas'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="rwai_org_phone"><?php esc_html_e( 'Phone', 'rankwriter-ai' ); ?></label></th>
					<td><input type="text" id="rwai_org_phone" name="rwai_schema_org[phone]" class="regular-text" value="<?php echo esc_attr( $org['phone'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="rwai_org_email"><?php esc_html_e( 'Email', 'rankwriter-ai' ); ?></label></th>
					<td><input type="email" id="rwai_org_email" name="rwai_schema_org[email]" class="regular-text" value="<?php echo esc_attr( $org['email'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="rwai_org_address"><?php esc_html_e( 'Address', 'rankwriter-ai' ); ?></label></th>
					<td><textarea id="rwai_org_address" name="rwai_schema_org[address]" rows="2" class="large-text"><?php echo esc_textarea( $org['address'] ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save organization', 'rankwriter-ai' ) ); ?>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Per-post schema audit', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $rows ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No published posts found.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Types in graph', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Errors', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Warnings', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $r ) :
					$err_pill  = $r['errors'] > 0 ? 'rwai-pill-bad' : 'rwai-pill-ok';
					$warn_pill = $r['warning_count'] > 0 ? 'rwai-pill-warn' : 'rwai-pill-ok';
				?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>"><strong><?php echo esc_html( wp_trim_words( $r['title'], 10 ) ); ?></strong></a>
							<br><a class="rwai-muted" href="<?php echo esc_url( get_permalink( $r['post_id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View live →', 'rankwriter-ai' ); ?></a>
						</td>
						<td><code><?php echo esc_html( implode( ', ', array_unique( $r['types'] ) ) ); ?></code></td>
						<td><span class="rwai-pill <?php echo esc_attr( $err_pill ); ?>"><?php echo (int) $r['errors']; ?></span></td>
						<td><span class="rwai-pill <?php echo esc_attr( $warn_pill ); ?>"><?php echo (int) $r['warning_count']; ?></span></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SCHEMA_SLUG, array( 'preview' => $r['post_id'] ) ) ); ?>"><?php esc_html_e( '👁 Preview', 'rankwriter-ai' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php
	$preview_id = isset( $_GET['preview'] ) ? absint( $_GET['preview'] ) : 0;
	if ( $preview_id && class_exists( 'RankWriter_AI_Schema_Engine' ) ) :
		$engine_preview = new RankWriter_AI_Schema_Engine();
		$pv_payload     = $engine_preview->get_saved_graph( $preview_id );
		if ( empty( $pv_payload['@graph'] ) ) {
			$pv_payload = $engine_preview->build_graph( $preview_id );
		}
		$pv_warnings = $engine_preview->validate( $pv_payload );
		$pv_post     = get_post( $preview_id );
	?>
		<div class="rwai-card rwai-card-wide">
			<h2>
				<?php esc_html_e( 'JSON-LD preview', 'rankwriter-ai' ); ?>
				— <?php echo esc_html( $pv_post ? $pv_post->post_title : '#' . $preview_id ); ?>
			</h2>

			<?php if ( ! empty( $pv_warnings ) ) : ?>
				<h3 style="margin-top:8px;"><?php esc_html_e( 'Validator', 'rankwriter-ai' ); ?></h3>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Severity', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'Type', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'Field', 'rankwriter-ai' ); ?></th><th><?php esc_html_e( 'Message', 'rankwriter-ai' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $pv_warnings as $w ) :
						$pill = 'error' === ( $w['severity'] ?? '' ) ? 'rwai-pill-bad' : 'rwai-pill-warn';
					?>
						<tr>
							<td><span class="rwai-pill <?php echo esc_attr( $pill ); ?>"><?php echo esc_html( strtoupper( $w['severity'] ?? '' ) ); ?></span></td>
							<td><code><?php echo esc_html( $w['type'] ?? '' ); ?></code></td>
							<td><code><?php echo esc_html( $w['field'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $w['message'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><span class="rwai-pill rwai-pill-ok"><?php esc_html_e( '✓ VALID', 'rankwriter-ai' ); ?></span> <?php esc_html_e( 'Every required field is present.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>

			<h3 style="margin-top:16px;"><?php esc_html_e( 'Generated JSON-LD', 'rankwriter-ai' ); ?></h3>
			<pre class="rwai-schema-preview"><?php echo esc_html( wp_json_encode( $pv_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>

			<p class="rwai-muted" style="margin-top:8px;"><?php
				printf(
					/* translators: %s: Google's Rich Results test URL */
					esc_html__( 'Test live with Google: %s', 'rankwriter-ai' ),
					'<a href="https://search.google.com/test/rich-results?url=' . rawurlencode( get_permalink( $preview_id ) ) . '" target="_blank" rel="noopener">search.google.com/test/rich-results</a>'
				);
			?></p>
		</div>
	<?php endif; ?>
</div>
