<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$audit  = (array) $data['audit'];
$msg    = (string) $data['msg'];
$err    = (string) $data['err'];

$has_audit = ! empty( $audit['generated_at'] );
$summary   = $audit['summary'] ?? array();
$top       = $audit['top_opportunities'] ?? array();
$comp      = $audit['competitor_keyword_gaps'] ?? array();
$cat       = $audit['category_coverage_gaps'] ?? array();
$cluster   = $audit['cluster_gaps'] ?? array();
$linking   = $audit['internal_link_gaps'] ?? array( 'orphan_posts' => array(), 'sparse_posts' => array() );
$underperf = $audit['underperforming'] ?? array();
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Content Gap Detector', 'rankwriter-ai' ); ?></h1>
	<form method="post" class="rwai-inline-form" style="display:inline-block;margin-left:6px;">
		<input type="hidden" name="rwai_action" value="gap_run_audit" />
		<?php wp_nonce_field( RankWriter_AI_Admin::GAP_NONCE ); ?>
		<button type="submit" class="page-title-action"><?php esc_html_e( '⚡ Run audit now', 'rankwriter-ai' ); ?></button>
	</form>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'A composite audit that compares your existing content against competitor RSS feeds, your keyword research history, your cluster targets, and your top-performing posts. Every gap gets a 0-100 opportunity score based on CPC tier + intent + niche priority + competition + long-tail bonus.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'gap-done' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Audit complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'gap-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Audit failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $has_audit ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'No audit yet', 'rankwriter-ai' ); ?></h2>
			<p><?php esc_html_e( 'Click "⚡ Run audit now" above to scan your site. The audit pulls signals from:', 'rankwriter-ai' ); ?></p>
			<ul style="padding-left:20px;">
				<li><?php esc_html_e( 'Competitor RSS titles + Google Suggest expansions in your Keyword Research history', 'rankwriter-ai' ); ?></li>
				<li><?php esc_html_e( 'Your WordPress categories vs cluster targets', 'rankwriter-ai' ); ?></li>
				<li><?php esc_html_e( 'Your existing clusters via the Cluster Analyzer', 'rankwriter-ai' ); ?></li>
				<li><?php esc_html_e( 'Internal-link orphans + sparsely-linked posts', 'rankwriter-ai' ); ?></li>
				<li><?php esc_html_e( 'Underperforming top-performing posts', 'rankwriter-ai' ); ?></li>
			</ul>
			<p class="rwai-muted"><?php esc_html_e( 'Audits also run automatically every 7 days. No competitor body content is fetched or stored — only topic-level signals.', 'rankwriter-ai' ); ?></p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<!-- Summary tiles -->
	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Total gaps', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) ( $summary['competitor_gap_count'] + $summary['category_gap_count'] + $summary['cluster_gap_count'] ) ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Top opportunity', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $summary['top_opportunity'] ); ?>/100</div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Priority-niche gaps', 'rankwriter-ai' ); ?> ★</div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $summary['priority_count'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Orphan posts', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $summary['orphan_count'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Sparse-link posts', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $summary['sparse_count'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Last run', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $audit['generated_at'] ) ); ?></div>
		</div>
	</div>

	<!-- ============== TOP OPPORTUNITIES ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '🎯 Top opportunities', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Ranked across every gap category by composite opportunity score. Click "Generate" to open Generate Article pre-filled with the topic — the existing content pipeline produces original prose, never copies competitor content.', 'rankwriter-ai' ); ?></p>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Topic', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Type', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Score', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'CPC', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Competition', 'rankwriter-ai' ); ?></th>
				<th class="rwai-col-actions"><?php esc_html_e( 'Action', 'rankwriter-ai' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $top as $o ) :
				$score    = (int) $o['opportunity_score'];
				$band     = $score >= 75 ? 'rwai-pill-ok' : ( $score >= 50 ? 'rwai-pill-warn' : 'rwai-pill-bad' );
				$priority = ! empty( $o['priority_niche'] );
			?>
				<tr>
					<td>
						<strong><?php echo esc_html( $o['topic'] ); ?></strong>
						<?php if ( $priority ) : ?> <span class="rwai-priority-star" title="<?php esc_attr_e( 'Priority niche', 'rankwriter-ai' ); ?>">★</span><?php endif; ?>
					</td>
					<td><small class="rwai-muted"><?php echo esc_html( str_replace( '_', ' ', $o['type'] ) ); ?></small></td>
					<td><span class="rwai-pill <?php echo esc_attr( $band ); ?>"><?php echo esc_html( $score ); ?></span></td>
					<td><?php if ( isset( $o['cpc_tier'] ) ) : ?><span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $o['cpc_tier'] ); ?>"><?php echo esc_html( ucfirst( $o['cpc_tier'] ) ); ?></span><?php if ( ! empty( $o['estimated_cpc'] ) ) : ?> <small>$<?php echo esc_html( number_format( (float) $o['estimated_cpc'], 2 ) ); ?></small><?php endif; ?><?php else : ?>—<?php endif; ?></td>
					<td><?php if ( isset( $o['intent'] ) ) : ?><span class="rwai-intent-badge rwai-intent-<?php echo esc_attr( $o['intent'] ); ?>"><?php echo esc_html( $o['intent_label'] ?? ucfirst( $o['intent'] ) ); ?></span><?php else : ?>—<?php endif; ?></td>
					<td><?php echo isset( $o['competition'] ) ? '<span class="rwai-pill rwai-comp-' . esc_attr( $o['competition'] ) . '">' . esc_html( ucfirst( $o['competition'] ) ) . '</span>' : '—'; ?></td>
					<td class="rwai-col-actions">
						<?php if ( isset( $o['keyword'] ) ) : ?>
							<a class="button button-small button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $o['keyword'] ) ) ) ); ?>"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></a>
						<?php elseif ( isset( $o['cluster_id'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG, array( 'cluster' => $o['cluster_id'] ) ) ); ?>"><?php esc_html_e( 'Open cluster', 'rankwriter-ai' ); ?></a>
						<?php elseif ( isset( $o['term_id'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category&tag_ID=' . (int) $o['term_id'] ) ); ?>"><?php esc_html_e( 'Open category', 'rankwriter-ai' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="rwai-grid rwai-grid-2">

		<!-- ============== CATEGORY COVERAGE ============== -->
		<div class="rwai-card">
			<h2><?php esc_html_e( '📦 Category coverage', 'rankwriter-ai' ); ?></h2>
			<?php if ( empty( $cat ) ) : ?>
				<p class="rwai-muted"><?php esc_html_e( 'All categories at or above target.', 'rankwriter-ai' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Category', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Posts', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'rankwriter-ai' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( array_slice( $cat, 0, 12 ) as $c ) :
						$pct = $c['target'] > 0 ? min( 100, (int) round( ( $c['count'] / $c['target'] ) * 100 ) ) : 0;
						$band = $pct >= 75 ? 'ok' : ( $pct >= 40 ? 'warn' : 'bad' );
					?>
						<tr>
							<td><strong><?php echo esc_html( $c['category'] ); ?></strong></td>
							<td><?php echo esc_html( $c['count'] . ' / ' . $c['target'] ); ?></td>
							<td>
								<div class="rwai-gap-bar"><div class="rwai-gap-bar-fill rwai-tl-bar-<?php echo esc_attr( $band ); ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
								<small><?php echo esc_html( $pct . '%' ); ?></small>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- ============== CLUSTER GAPS ============== -->
		<div class="rwai-card">
			<h2><?php esc_html_e( '🌐 Cluster gaps', 'rankwriter-ai' ); ?></h2>
			<?php if ( empty( $cluster ) ) : ?>
				<p class="rwai-muted"><?php esc_html_e( 'All clusters fully covered.', 'rankwriter-ai' ); ?></p>
			<?php else : ?>
				<ul>
				<?php foreach ( array_slice( $cluster, 0, 8 ) as $cg ) : ?>
					<li>
						<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG, array( 'cluster' => $cg['cluster_id'] ) ) ); ?>"><strong><?php echo esc_html( $cg['cluster_name'] ); ?></strong></a>
						<small class="rwai-muted">— <?php echo esc_html( $cg['completion'] . '% complete' ); ?></small>
						<?php if ( ! empty( $cg['missing_intents'] ) ) : ?>
							<br><small><?php esc_html_e( 'Missing:', 'rankwriter-ai' ); ?>
								<?php foreach ( array_slice( $cg['missing_intents'], 0, 6 ) as $mi ) : ?>
									<span class="rwai-tag"><?php echo esc_html( $mi ); ?></span>
								<?php endforeach; ?>
							</small>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<!-- ============== INTERNAL LINKING ============== -->
		<div class="rwai-card">
			<h2><?php esc_html_e( '🔗 Internal linking weak spots', 'rankwriter-ai' ); ?></h2>
			<?php if ( empty( $linking['orphan_posts'] ) && empty( $linking['sparse_posts'] ) ) : ?>
				<p class="rwai-muted"><?php esc_html_e( 'No orphans or sparse posts detected.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $linking['orphan_posts'] ) ) : ?>
				<h4><?php esc_html_e( 'Orphan posts (no inbound internal links)', 'rankwriter-ai' ); ?></h4>
				<ul>
					<?php foreach ( array_slice( $linking['orphan_posts'], 0, 8 ) as $o ) : ?>
						<li><a href="<?php echo esc_url( get_edit_post_link( $o['id'] ) ); ?>"><?php echo esc_html( $o['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $linking['sparse_posts'] ) ) : ?>
				<h4><?php esc_html_e( 'Sparse-link posts (< 2 outbound internal)', 'rankwriter-ai' ); ?></h4>
				<ul>
					<?php foreach ( array_slice( $linking['sparse_posts'], 0, 8 ) as $sp ) : ?>
						<li><a href="<?php echo esc_url( get_edit_post_link( $sp['id'] ) ); ?>"><?php echo esc_html( $sp['title'] ); ?></a> <small class="rwai-muted">(<?php echo (int) $sp['internal_links']; ?> links)</small></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<!-- ============== UNDERPERFORMING ============== -->
		<div class="rwai-card">
			<h2><?php esc_html_e( '📉 Underperforming topics', 'rankwriter-ai' ); ?></h2>
			<?php if ( empty( $underperf ) ) : ?>
				<p class="rwai-muted"><?php esc_html_e( 'No underperformers detected.', 'rankwriter-ai' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( array_slice( $underperf, 0, 10 ) as $u ) : ?>
						<li>
							<?php if ( ! empty( $u['post_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $u['post_id'] ) ); ?>"><?php echo esc_html( $u['title'] ); ?></a>
							<?php else : ?>
								<strong><?php echo esc_html( $u['title'] ); ?></strong>
							<?php endif; ?>
							<br><small class="rwai-muted"><?php echo esc_html( $u['reason'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<!-- ============== COMPETITOR KEYWORDS (FULL LIST) ============== -->
	<?php if ( ! empty( $comp ) ) : ?>
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '🕷️ Competitor keyword gaps', 'rankwriter-ai' ); ?>
			<small class="rwai-muted" style="font-weight:normal;text-transform:none;letter-spacing:normal;">
				<?php
				/* translators: %d: count */
				printf( esc_html__( '%d topics competitors cover that you don\'t', 'rankwriter-ai' ), count( $comp ) );
				?>
			</small>
		</h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Keyword', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Source', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'CPC', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Competition', 'rankwriter-ai' ); ?></th>
				<th class="rwai-col-actions"><?php esc_html_e( 'Action', 'rankwriter-ai' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( array_slice( $comp, 0, 50 ) as $g ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $g['keyword'] ); ?></strong>
						<?php if ( ! empty( $g['priority_niche'] ) ) : ?> <span class="rwai-priority-star">★</span><?php endif; ?>
					</td>
					<td><small class="rwai-muted"><?php echo esc_html( $g['source'] ); ?></small></td>
					<td><span class="rwai-intent-badge rwai-intent-<?php echo esc_attr( $g['intent'] ); ?>"><?php echo esc_html( $g['intent_label'] ); ?></span></td>
					<td><span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $g['cpc_tier'] ); ?>"><?php echo esc_html( ucfirst( $g['cpc_tier'] ) ); ?></span> $<?php echo esc_html( number_format( (float) $g['estimated_cpc'], 2 ) ); ?></td>
					<td><span class="rwai-pill rwai-comp-<?php echo esc_attr( $g['competition'] ); ?>"><?php echo esc_html( ucfirst( $g['competition'] ) ); ?></span></td>
					<td class="rwai-col-actions">
						<a class="button button-small button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $g['keyword'] ) ) ) ); ?>"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="rwai-muted" style="font-size:12px;margin-top:10px;"><?php esc_html_e( 'Source signals: only TOPIC-level (keywords + titles) — no body content from competitor sites is fetched or stored. Generated articles use original prose written by Claude.', 'rankwriter-ai' ); ?></p>
	</div>
	<?php endif; ?>
</div>
