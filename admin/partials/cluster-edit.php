<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$is_new   = (bool) $data['creating'];
$cluster  = isset( $data['cluster'] ) ? (array) $data['cluster'] : array();
$topics   = isset( $cluster['topics'] ) ? (array) $cluster['topics'] : array();
$profiles = (array) $data['profiles'];
$gaps     = isset( $data['gaps'] ) ? (array) $data['gaps'] : array();

// CPC scoring for every topic, using the cluster's profile country if set.
$cluster_country = 'US';
if ( ! empty( $cluster['profile_id'] ) ) {
	foreach ( $profiles as $p ) {
		if ( (int) $p['id'] === (int) $cluster['profile_id'] && ! empty( $p['target_country'] ) ) {
			$cluster_country = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $p['target_country'] ), 0, 2 ) ) ?: 'US';
			break;
		}
	}
}
$cpc_rows    = array();
$cpc_summary = array();
if ( class_exists( 'RankWriter_AI_CPC_Scorer' ) && ! empty( $topics ) ) {
	$scorer = new RankWriter_AI_CPC_Scorer();
	foreach ( $topics as $i => $t ) {
		$hints = array();
		if ( ! empty( $t['intent'] ) ) {
			$hints['intent'] = $t['intent'];
		}
		$row             = $scorer->score( $t['topic'], $cluster_country, $hints );
		$cpc_rows[ $t['id'] ] = $row;
	}
	$cpc_summary = $scorer->summarize( array_values( $cpc_rows ) );
}
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];

$id           = $is_new ? 0 : (int) $cluster['id'];
$name         = $is_new ? '' : (string) $cluster['name'];
$description  = $is_new ? '' : (string) $cluster['description'];
$pillar_pid   = $is_new ? 0 : (int) $cluster['pillar_post_id'];
$profile_id   = $is_new ? 0 : (int) $cluster['profile_id'];
$target_count = $is_new ? 6 : (int) $cluster['target_supporting_count'];
$sem_keys     = $is_new ? '' : (string) $cluster['semantic_keywords'];
$completion   = $is_new ? 0 : (int) $cluster['completion_score'];
?>
<div class="wrap rwai-wrap">
	<h1><?php echo $is_new ? esc_html__( 'New cluster', 'rankwriter-ai' ) : esc_html( $name ); ?>
		<?php if ( ! $is_new ) : ?>
			<span class="rwai-pill <?php echo $completion >= 80 ? 'rwai-pill-ok' : ( $completion >= 50 ? 'rwai-pill-warn' : 'rwai-pill-bad' ); ?>" style="vertical-align:middle;"><?php echo esc_html( $completion . '%' ); ?> <?php esc_html_e( 'complete', 'rankwriter-ai' ); ?></span>
		<?php endif; ?>
	</h1>

	<?php if ( ! $is_new && ! empty( $cpc_summary['count'] ) ) : ?>
		<div class="rwai-cpc-summary-row" style="margin-bottom:14px;">
			<div class="rwai-cpc-summary-card">
				<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Avg CPC (cluster)', 'rankwriter-ai' ); ?></div>
				<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_summary['avg_cpc'], 2 ) ); ?></div>
			</div>
			<div class="rwai-cpc-summary-card">
				<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Top topic CPC', 'rankwriter-ai' ); ?></div>
				<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_summary['max_cpc'], 2 ) ); ?></div>
			</div>
			<div class="rwai-cpc-summary-card">
				<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Predicted RPM', 'rankwriter-ai' ); ?></div>
				<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_summary['avg_rpm'], 0 ) ); ?></div>
			</div>
			<div class="rwai-cpc-summary-card">
				<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Monetization score', 'rankwriter-ai' ); ?></div>
				<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $cpc_summary['avg_score'] ); ?>/100</div>
			</div>
			<div class="rwai-cpc-summary-card">
				<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Dominant tier', 'rankwriter-ai' ); ?></div>
				<div class="rwai-cpc-summary-value">
					<span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $cpc_summary['dominant_tier'] ); ?>"><?php echo esc_html( RankWriter_AI_CPC_Scorer::tier_label( $cpc_summary['dominant_tier'] ) ); ?></span>
				</div>
			</div>
			<?php if ( ! empty( $cpc_summary['priority_count'] ) ) : ?>
				<div class="rwai-cpc-summary-card">
					<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Priority-niche topics', 'rankwriter-ai' ); ?></div>
					<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $cpc_summary['priority_count'] . ' / ' . (int) $cpc_summary['count'] ); ?> ★</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'cluster-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cluster saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'topics-suggested' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html( _n( '%d new topic added.', '%d new topics added.', isset( $_GET['count'] ) ? (int) $_GET['count'] : 0, 'rankwriter-ai' ) ), isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'keywords-generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Semantic keywords generated.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'auto-matched' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html( _n( '%d existing post linked to this cluster.', '%d existing posts linked to this cluster.', isset( $_GET['count'] ) ? (int) $_GET['count'] : 0, 'rankwriter-ai' ) ), isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'cluster-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<!-- ============== CLUSTER FORM ============== -->
	<form method="post" class="rwai-form" data-rwai-ai-context="cluster">
		<input type="hidden" name="rwai_action" value="save_cluster" />
		<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
		<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_cluster_name"><?php esc_html_e( 'Cluster name (the pillar topic)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="rwai_cluster_name" name="name" value="<?php echo esc_attr( $name ); ?>" required data-rwai-ai-target="name" placeholder="<?php esc_attr_e( 'e.g. Study in Canada', 'rankwriter-ai' ); ?>" />
					<p class="description"><?php esc_html_e( 'The broad topic that anchors this cluster. Each supporting article will deep-dive on a sub-topic of this.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_cluster_desc"><?php esc_html_e( 'Description', 'rankwriter-ai' ); ?></label></th>
				<td><textarea class="large-text code" rows="3" id="rwai_cluster_desc" name="description" data-rwai-ai-target="description"><?php echo esc_textarea( $description ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_cluster_profile"><?php esc_html_e( 'Category profile', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_cluster_profile" name="profile_id">
						<option value=""><?php esc_html_e( '— None —', 'rankwriter-ai' ); ?></option>
						<?php foreach ( $profiles as $p ) : ?>
							<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $profile_id, $p['id'] ); ?>><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'New articles generated for this cluster will use this profile\'s tone, monetization, image style, and SEO settings.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_cluster_target"><?php esc_html_e( 'Target supporting articles', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="3" max="30" id="rwai_cluster_target" name="target_supporting_count" value="<?php echo esc_attr( $target_count ); ?>" />
					<p class="description"><?php esc_html_e( 'How many supporting articles you want this cluster to have when "complete". 5-8 is typical.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_cluster_pillar"><?php esc_html_e( 'Pillar article', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_cluster_pillar" name="pillar_post_id">
						<option value=""><?php esc_html_e( '— None yet —', 'rankwriter-ai' ); ?></option>
						<?php
						$pillar_choices = get_posts( array(
							'post_type'      => 'post',
							'post_status'    => array( 'publish', 'draft', 'pending' ),
							'posts_per_page' => 200,
							'orderby'        => 'date',
							'order'          => 'DESC',
						) );
						foreach ( $pillar_choices as $pp ) : ?>
							<option value="<?php echo esc_attr( $pp->ID ); ?>" <?php selected( $pillar_pid, $pp->ID ); ?>><?php echo esc_html( $pp->post_title . ' (#' . $pp->ID . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Pick an existing post as the pillar — or leave blank and generate one from the cluster page.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rwai_cluster_semkw"><?php esc_html_e( 'Semantic keywords', 'rankwriter-ai' ); ?></label>
					<?php if ( ! $is_new ) : ?>
						<br>
						<form method="post" class="rwai-inline-form" style="margin-top:6px;">
							<input type="hidden" name="rwai_action" value="generate_cluster_keywords" />
							<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
							<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
							<button type="submit" class="button button-small"><?php esc_html_e( '✨ Generate', 'rankwriter-ai' ); ?></button>
						</form>
					<?php endif; ?>
				</th>
				<td>
					<textarea class="large-text code" rows="3" id="rwai_cluster_semkw" name="semantic_keywords" data-rwai-ai-target="semantic_keywords" placeholder="<?php esc_attr_e( 'Comma-separated keywords. Click ✨ Generate to populate from Claude.', 'rankwriter-ai' ); ?>"><?php echo esc_textarea( $sem_keys ); ?></textarea>
					<p class="description"><?php esc_html_e( 'These keywords are injected into every article generated for this cluster — they spread the semantic signals search engines use to confirm topical relevance.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php echo $is_new ? esc_html__( 'Create cluster', 'rankwriter-ai' ) : esc_html__( 'Save cluster', 'rankwriter-ai' ); ?></button>
			<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Back to clusters', 'rankwriter-ai' ); ?></a>
		</p>
	</form>

	<?php if ( ! $is_new ) : ?>

	<!-- ============== RELATIONSHIP GRAPH ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Cluster map', 'rankwriter-ai' ); ?></h2>
		<?php
		// Build SVG: pillar in center, supporting topics arranged in a circle.
		$pillar_label = $pillar_pid ? wp_html_excerpt( get_the_title( $pillar_pid ), 40, '…' ) : $name;
		$node_topics  = $topics;
		$n            = max( 1, count( $node_topics ) );
		$cx           = 400;
		$cy           = 280;
		$radius       = $n <= 6 ? 180 : ( $n <= 10 ? 200 : 220 );

		$status_color = array(
			'published' => '#2a7e3b',
			'queued'    => '#dba617',
			'suggested' => '#787c82',
			'skipped'   => '#b32d2e',
		);
		?>
		<div class="rwai-graph-wrap">
			<svg class="rwai-graph" viewBox="0 0 800 560" preserveAspectRatio="xMidYMid meet">
				<!-- Lines pillar → each topic -->
				<?php foreach ( $node_topics as $i => $t ) :
					$angle = ( 2 * M_PI * $i / $n ) - ( M_PI / 2 );
					$x     = $cx + $radius * cos( $angle );
					$y     = $cy + $radius * sin( $angle );
					$color = isset( $status_color[ $t['status'] ] ) ? $status_color[ $t['status'] ] : '#787c82';
					?>
					<line x1="<?php echo esc_attr( $cx ); ?>" y1="<?php echo esc_attr( $cy ); ?>" x2="<?php echo esc_attr( $x ); ?>" y2="<?php echo esc_attr( $y ); ?>" stroke="<?php echo esc_attr( $color ); ?>" stroke-width="<?php echo 'published' === $t['status'] ? 2.5 : 1.5; ?>" stroke-dasharray="<?php echo 'published' === $t['status'] ? 'none' : '5,4'; ?>" opacity="0.6" />
				<?php endforeach; ?>

				<!-- Pillar node -->
				<g class="rwai-graph-pillar">
					<rect x="<?php echo esc_attr( $cx - 120 ); ?>" y="<?php echo esc_attr( $cy - 30 ); ?>" width="240" height="60" rx="10" fill="<?php echo $pillar_pid ? '#2271b1' : '#dba617'; ?>" />
					<text x="<?php echo esc_attr( $cx ); ?>" y="<?php echo esc_attr( $cy - 5 ); ?>" text-anchor="middle" font-size="13" font-weight="700" fill="#fff"><?php echo esc_html( wp_html_excerpt( $pillar_label, 32, '…' ) ); ?></text>
					<text x="<?php echo esc_attr( $cx ); ?>" y="<?php echo esc_attr( $cy + 15 ); ?>" text-anchor="middle" font-size="10" fill="#dbe6f7"><?php echo $pillar_pid ? esc_html__( 'PILLAR', 'rankwriter-ai' ) : esc_html__( 'PILLAR (not set)', 'rankwriter-ai' ); ?></text>
				</g>

				<!-- Topic nodes -->
				<?php foreach ( $node_topics as $i => $t ) :
					$angle = ( 2 * M_PI * $i / $n ) - ( M_PI / 2 );
					$x     = $cx + $radius * cos( $angle );
					$y     = $cy + $radius * sin( $angle );
					$color = isset( $status_color[ $t['status'] ] ) ? $status_color[ $t['status'] ] : '#787c82';
					$label = wp_html_excerpt( $t['topic'], 28, '…' );
					$post_link = ! empty( $t['post_id'] ) ? get_edit_post_link( $t['post_id'] ) : '';
					?>
					<g class="rwai-graph-node" data-topic-id="<?php echo esc_attr( $t['id'] ); ?>">
						<rect x="<?php echo esc_attr( $x - 90 ); ?>" y="<?php echo esc_attr( $y - 22 ); ?>" width="180" height="44" rx="8" fill="#fff" stroke="<?php echo esc_attr( $color ); ?>" stroke-width="2" />
						<?php if ( $post_link ) : ?>
							<a href="<?php echo esc_url( $post_link ); ?>">
								<text x="<?php echo esc_attr( $x ); ?>" y="<?php echo esc_attr( $y - 2 ); ?>" text-anchor="middle" font-size="11" font-weight="600" fill="#1d2327"><?php echo esc_html( $label ); ?></text>
								<text x="<?php echo esc_attr( $x ); ?>" y="<?php echo esc_attr( $y + 13 ); ?>" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $color ); ?>"><?php echo esc_html( strtoupper( $t['status'] ) ); ?></text>
							</a>
						<?php else : ?>
							<text x="<?php echo esc_attr( $x ); ?>" y="<?php echo esc_attr( $y - 2 ); ?>" text-anchor="middle" font-size="11" font-weight="600" fill="#1d2327"><?php echo esc_html( $label ); ?></text>
							<text x="<?php echo esc_attr( $x ); ?>" y="<?php echo esc_attr( $y + 13 ); ?>" text-anchor="middle" font-size="9" fill="<?php echo esc_attr( $color ); ?>"><?php echo esc_html( strtoupper( $t['status'] ) ); ?></text>
						<?php endif; ?>
					</g>
				<?php endforeach; ?>
			</svg>
			<div class="rwai-graph-legend">
				<span><span class="rwai-dot" style="background:#2a7e3b"></span> <?php esc_html_e( 'Published', 'rankwriter-ai' ); ?></span>
				<span><span class="rwai-dot" style="background:#dba617"></span> <?php esc_html_e( 'Queued', 'rankwriter-ai' ); ?></span>
				<span><span class="rwai-dot" style="background:#787c82"></span> <?php esc_html_e( 'Suggested', 'rankwriter-ai' ); ?></span>
				<span><span class="rwai-dot" style="background:#b32d2e"></span> <?php esc_html_e( 'Skipped', 'rankwriter-ai' ); ?></span>
			</div>
		</div>
	</div>

	<!-- ============== TOPIC MANAGEMENT ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Supporting topics', 'rankwriter-ai' ); ?></h2>

		<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
			<form method="post" class="rwai-inline-form">
				<input type="hidden" name="rwai_action" value="suggest_topics" />
				<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( '✨ Suggest more topics with Claude', 'rankwriter-ai' ); ?></button>
			</form>
			<form method="post" class="rwai-inline-form">
				<input type="hidden" name="rwai_action" value="auto_match_posts" />
				<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Auto-match existing posts', 'rankwriter-ai' ); ?></button>
			</form>
		</div>

		<form method="post" class="rwai-inline-form" style="margin-bottom:14px;">
			<input type="hidden" name="rwai_action" value="add_cluster_topic" />
			<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
			<input type="text" name="topic" class="regular-text" placeholder="<?php esc_attr_e( 'Add a custom supporting topic manually…', 'rankwriter-ai' ); ?>" required />
			<button type="submit" class="button"><?php esc_html_e( 'Add topic', 'rankwriter-ai' ); ?></button>
		</form>

		<?php if ( empty( $topics ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No topics yet. Click "✨ Suggest more topics with Claude" — it will read the pillar + your category profile and propose 6-10 supporting articles.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwai-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Topic', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'CPC tier', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Est. CPC', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $topics as $t ) :
					$status_class = 'rwai-pill-warn';
					if ( 'published' === $t['status'] ) {
						$status_class = 'rwai-pill-ok';
					} elseif ( 'skipped' === $t['status'] ) {
						$status_class = 'rwai-pill-bad';
					}
					$intent       = isset( $t['intent'] ) ? $t['intent'] : 'informational';
					$intent_label = isset( $t['intent_label'] ) ? $t['intent_label'] : ucfirst( $intent );
					$intent_conf  = isset( $t['intent_confidence'] ) ? (int) $t['intent_confidence'] : 0;
					?>
					<?php
					$cpc_row    = isset( $cpc_rows[ $t['id'] ] ) ? $cpc_rows[ $t['id'] ] : null;
					$cpc_tier   = $cpc_row ? $cpc_row['tier'] : 'low';
					$cpc_value  = $cpc_row ? (float) $cpc_row['estimated_cpc_usd'] : 0;
					$is_priority = $cpc_row && ! empty( $cpc_row['priority_niche'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $t['topic'] ); ?></strong>
							<?php if ( $is_priority ) : ?> <span class="rwai-priority-star" title="<?php esc_attr_e( 'Priority high-value niche', 'rankwriter-ai' ); ?>">★</span><?php endif; ?>
						</td>
						<td>
							<span class="rwai-intent-badge rwai-intent-<?php echo esc_attr( $intent ); ?>" title="<?php echo esc_attr( sprintf( __( '%d%% confidence', 'rankwriter-ai' ), $intent_conf ) ); ?>"><?php echo esc_html( $intent_label ); ?></span>
							<?php if ( $intent_conf ) : ?><small class="rwai-muted"> <?php echo esc_html( $intent_conf . '%' ); ?></small><?php endif; ?>
						</td>
						<td><span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $cpc_tier ); ?>"><?php echo esc_html( RankWriter_AI_CPC_Scorer::tier_label( $cpc_tier ) ); ?></span></td>
						<td><?php echo $cpc_value ? '$' . esc_html( number_format( $cpc_value, 2 ) ) : '—'; ?></td>
						<td><span class="rwai-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $t['status'] ); ?></span></td>
						<td>
							<?php if ( ! empty( $t['post_id'] ) && get_post( $t['post_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $t['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $t['post_id'] ) ); ?></a>
							<?php else : ?>
								<span class="rwai-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="rwai-col-actions">
							<?php if ( empty( $t['post_id'] ) && 'skipped' !== $t['status'] ) : ?>
								<form method="post" class="rwai-inline-form">
									<input type="hidden" name="rwai_action" value="generate_cluster_topic" />
									<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
									<input type="hidden" name="topic_id" value="<?php echo esc_attr( $t['id'] ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
									<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( 'skipped' !== $t['status'] ) : ?>
								<form method="post" class="rwai-inline-form">
									<input type="hidden" name="rwai_action" value="skip_cluster_topic" />
									<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
									<input type="hidden" name="topic_id" value="<?php echo esc_attr( $t['id'] ); ?>" />
									<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Skip', 'rankwriter-ai' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" class="rwai-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this topic from the cluster?', 'rankwriter-ai' ) ); ?>');">
								<input type="hidden" name="rwai_action" value="delete_cluster_topic" />
								<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $id ); ?>" />
								<input type="hidden" name="topic_id" value="<?php echo esc_attr( $t['id'] ); ?>" />
								<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
								<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- ============== GAPS ============== -->
	<?php if ( ! empty( $gaps['orphan_posts'] ) || ! empty( $gaps['missing_intents'] ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'Topical gaps', 'rankwriter-ai' ); ?></h2>
			<?php if ( ! empty( $gaps['orphan_posts'] ) ) : ?>
				<h3><?php esc_html_e( 'Existing posts that match suggested topics but aren\'t linked yet', 'rankwriter-ai' ); ?></h3>
				<ul>
					<?php foreach ( $gaps['orphan_posts'] as $o ) : ?>
						<li>
							<strong><?php echo esc_html( $o['title'] ); ?></strong> ↔ <em><?php echo esc_html( $o['topic'] ); ?></em>
							<a href="<?php echo esc_url( get_edit_post_link( $o['post_id'] ) ); ?>"><?php esc_html_e( 'Open post', 'rankwriter-ai' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><em><?php esc_html_e( 'Click "Auto-match existing posts" above to link them automatically.', 'rankwriter-ai' ); ?></em></p>
			<?php endif; ?>
			<?php if ( ! empty( $gaps['missing_intents'] ) ) : ?>
				<h3><?php esc_html_e( 'Search intents this cluster doesn\'t cover yet', 'rankwriter-ai' ); ?></h3>
				<p><?php esc_html_e( 'Adding an article for each strengthens topical authority:', 'rankwriter-ai' ); ?></p>
				<p class="rwai-tagcloud">
					<?php foreach ( $gaps['missing_intents'] as $intent ) : ?>
						<span class="rwai-tag rwai-tag-strong"><?php echo esc_html( $intent ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php endif; // ! $is_new ?>
</div>
