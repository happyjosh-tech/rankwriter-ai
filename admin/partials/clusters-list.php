<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$clusters = (array) $data['clusters'];
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];
$total    = (int) $data['total'];
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Topical Authority Clusters', 'rankwriter-ai' ); ?></h1>
	<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG, array( 'new' => 1 ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'New cluster', 'rankwriter-ai' ); ?></a>

	<form method="post" class="rwai-inline-form" style="display:inline-block;margin-left:6px;">
		<input type="hidden" name="rwai_action" value="suggest_clusters_from_blog" />
		<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
		<button type="submit" class="page-title-action"><?php esc_html_e( '✨ Suggest clusters from my blog', 'rankwriter-ai' ); ?></button>
	</form>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'A cluster is one pillar article + N supporting articles, all linked back to the pillar. This is the topical-authority architecture Google rewards. Build clusters around the topics your audience actually searches.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'cluster-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cluster saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'cluster-deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cluster deleted.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'cluster-suggested' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			/* translators: %d: number of clusters suggested */
			printf( esc_html( _n( '%d cluster suggestion drafted.', '%d cluster suggestions drafted.', isset( $_GET['count'] ) ? (int) $_GET['count'] : 0, 'rankwriter-ai' ) ), isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'cluster-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $clusters ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'No clusters yet', 'rankwriter-ai' ); ?></h2>
			<p><?php esc_html_e( 'Two ways to start:', 'rankwriter-ai' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG, array( 'new' => 1 ) ) ); ?>"><?php esc_html_e( 'Create one manually', 'rankwriter-ai' ); ?></a>
				<?php esc_html_e( ' — pick a pillar topic, Claude proposes supporting articles.', 'rankwriter-ai' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( '✨ Suggest clusters from my blog', 'rankwriter-ai' ); ?></strong>
				<?php esc_html_e( ' — Claude reads your existing posts (via the Blog Style Profile) and proposes ready-to-build clusters that fit what you\'re already writing about.', 'rankwriter-ai' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="rwai-grid rwai-grid-2">
		<?php foreach ( $clusters as $c ) :
			$completion = (int) $c['completion_score'];
			$pillar_title = ! empty( $c['pillar_post_id'] ) ? get_the_title( $c['pillar_post_id'] ) : '';
			$edit_url = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::CLUSTERS_SLUG, array( 'cluster' => $c['id'] ) );
			?>
			<div class="rwai-card rwai-cluster-card">
				<div class="rwai-cluster-card-head">
					<h2><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $c['name'] ); ?></a></h2>
					<div class="rwai-completion-ring" data-pct="<?php echo esc_attr( $completion ); ?>" title="<?php echo esc_attr( sprintf( __( '%d%% complete', 'rankwriter-ai' ), $completion ) ); ?>">
						<svg viewBox="0 0 36 36">
							<circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e4e7" stroke-width="3.2" />
							<circle cx="18" cy="18" r="15.9" fill="none" stroke="<?php echo $completion >= 80 ? '#2a7e3b' : ( $completion >= 50 ? '#dba617' : '#b32d2e' ); ?>" stroke-width="3.2"
								stroke-dasharray="<?php echo esc_attr( $completion ); ?>, 100" stroke-linecap="round" transform="rotate(-90 18 18)" />
							<text x="18" y="20" text-anchor="middle" font-size="9" font-weight="600" fill="#1d2327"><?php echo esc_html( $completion ); ?>%</text>
						</svg>
					</div>
				</div>
				<?php if ( $pillar_title ) : ?>
					<p class="rwai-muted"><strong><?php esc_html_e( 'Pillar:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( $pillar_title ); ?></p>
				<?php else : ?>
					<p class="rwai-muted rwai-pillar-missing"><?php esc_html_e( 'No pillar set yet — pick or generate one.', 'rankwriter-ai' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $c['description'] ) ) : ?>
					<p><?php echo esc_html( wp_trim_words( $c['description'], 25 ) ); ?></p>
				<?php endif; ?>
				<dl class="rwai-dl rwai-cluster-stats">
					<dt><?php esc_html_e( 'Topics', 'rankwriter-ai' ); ?></dt>
					<dd><?php echo esc_html( (int) $c['topic_count'] . ' / ' . (int) $c['target_supporting_count'] ); ?></dd>
					<dt><?php esc_html_e( 'Published', 'rankwriter-ai' ); ?></dt>
					<dd><?php echo esc_html( (int) $c['published_count'] ); ?></dd>
					<dt><?php esc_html_e( 'Updated', 'rankwriter-ai' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $c['updated_at'] ) ); ?></dd>
				</dl>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Open cluster', 'rankwriter-ai' ); ?></a>
					<form method="post" class="rwai-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this cluster? Posts already generated will NOT be deleted — only the cluster relationships.', 'rankwriter-ai' ) ); ?>');">
						<input type="hidden" name="rwai_action" value="delete_cluster" />
						<input type="hidden" name="cluster_id" value="<?php echo esc_attr( $c['id'] ); ?>" />
						<?php wp_nonce_field( RankWriter_AI_Admin::CLUSTER_NONCE ); ?>
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
					</form>
				</p>
			</div>
		<?php endforeach; ?>
		</div>
		<p class="rwai-muted"><?php
			/* translators: %d: total clusters */
			printf( esc_html( _n( '%d cluster total.', '%d clusters total.', $total, 'rankwriter-ai' ) ), $total );
		?></p>
	<?php endif; ?>
</div>
