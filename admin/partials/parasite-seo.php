<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$post_id   = (int) ( $data['post_id'] ?? 0 );
$platform  = (string) ( $data['platform'] ?? '' );
$payload   = (array) ( $data['payload'] ?? array() );
$log_rows  = (array) ( $data['log_rows'] ?? array() );
$platforms = (array) ( $data['platforms'] ?? array() );
$post_obj  = $post_id ? get_post( $post_id ) : null;
$msg       = (string) ( $data['msg'] ?? '' );
$err       = (string) ( $data['err'] ?? '' );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Parasite SEO Mode', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Repurpose published posts for Medium, LinkedIn, Quora, Reddit, or any external platform. Each platform gets its own rewrite strategy — full-length with canonical for Medium, substantively-different rewrite for LinkedIn, Q&A format for Quora, summary-only for Reddit. Built-in compliance warnings keep you on the right side of platform rules and Google\'s duplicate-content guidelines.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'syn-generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Generated. Copy from the output panel below into the platform.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'syn-marked' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Marked as published — external URL recorded.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'syn-deleted' === $msg ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Syndication entry deleted.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'syn-error' === $msg && $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Generate repurposed content', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-inline-form">
			<input type="hidden" name="rwai_action" value="parasite_generate" />
			<?php wp_nonce_field( RankWriter_AI_Admin::PARASITE_NONCE ); ?>
			<label><?php esc_html_e( 'Post ID or URL:', 'rankwriter-ai' ); ?></label>
			<input type="text" name="post_ref" class="regular-text" value="<?php echo esc_attr( $post_id ?: '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. 123 or https://yoursite.com/your-post-slug/', 'rankwriter-ai' ); ?>" required />
			<label style="margin-left:10px;"><?php esc_html_e( 'Platform:', 'rankwriter-ai' ); ?></label>
			<select name="platform" required>
				<?php foreach ( $platforms as $key => $cfg ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $platform, $key ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( '✨ Generate', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $payload ) && $post_obj ) :
		$cfg = $platforms[ $payload['platform'] ] ?? null;
	?>
		<div class="rwai-card rwai-card-wide">
			<h2>
				<?php
				/* translators: 1: post title, 2: platform name */
				echo esc_html( sprintf( __( '%1$s → %2$s', 'rankwriter-ai' ), wp_trim_words( $post_obj->post_title, 8 ), $cfg ? $cfg['label'] : ucfirst( $payload['platform'] ) ) );
				?>
			</h2>

			<?php if ( $cfg ) : ?>
				<div class="notice notice-<?php echo $cfg['canonical_safe'] ? 'info' : 'warning'; ?>" style="margin:0 0 14px;">
					<p style="margin:8px 12px;">
						<strong><?php echo $cfg['canonical_safe'] ? esc_html__( 'Canonical OK:', 'rankwriter-ai' ) : esc_html__( 'Canonical NOT supported:', 'rankwriter-ai' ); ?></strong>
						<?php echo esc_html( $cfg['compliance'] ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $payload['compliance_notes'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Generation note:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( $payload['compliance_notes'] ); ?></p>
			<?php endif; ?>

			<h3 style="margin-top:18px;"><?php esc_html_e( 'Title', 'rankwriter-ai' ); ?></h3>
			<textarea readonly rows="2" class="large-text rwai-syn-copy"><?php echo esc_textarea( $payload['title'] ); ?></textarea>

			<h3 style="margin-top:14px;"><?php esc_html_e( 'Body', 'rankwriter-ai' ); ?></h3>
			<textarea readonly rows="18" class="large-text rwai-syn-copy"><?php echo esc_textarea( $payload['body'] ); ?></textarea>

			<h3 style="margin-top:14px;"><?php esc_html_e( 'CTA', 'rankwriter-ai' ); ?></h3>
			<textarea readonly rows="2" class="large-text rwai-syn-copy"><?php echo esc_textarea( $payload['cta'] ); ?></textarea>

			<?php if ( ! empty( $payload['hashtags'] ) ) : ?>
				<h3 style="margin-top:14px;"><?php esc_html_e( 'Hashtags', 'rankwriter-ai' ); ?></h3>
				<p>
					<?php foreach ( $payload['hashtags'] as $h ) :
						$tag = ltrim( (string) $h, '#' );
					?>
						<span class="rwai-pill rwai-pill-ok" style="margin:0 4px 4px 0;">#<?php echo esc_html( $tag ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $payload['canonical_url'] ) ) : ?>
				<h3 style="margin-top:14px;"><?php esc_html_e( 'Canonical URL to paste into the platform', 'rankwriter-ai' ); ?></h3>
				<input type="text" readonly class="large-text rwai-syn-copy" value="<?php echo esc_attr( $payload['canonical_url'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Paste this exact URL into the platform\'s "canonical" / "original source" field so search engines attribute ranking to your blog.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $payload['log_id'] ) ) : ?>
				<h3 style="margin-top:18px;"><?php esc_html_e( 'Once you\'ve published on the platform, record the live URL', 'rankwriter-ai' ); ?></h3>
				<form method="post" class="rwai-inline-form">
					<input type="hidden" name="rwai_action" value="parasite_mark_published" />
					<input type="hidden" name="log_id" value="<?php echo esc_attr( $payload['log_id'] ); ?>" />
					<?php wp_nonce_field( RankWriter_AI_Admin::PARASITE_NONCE ); ?>
					<input type="url" name="external_url" class="regular-text" placeholder="https://medium.com/@you/your-story-slug" required />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Mark published', 'rankwriter-ai' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Syndication log', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $log_rows ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No syndications recorded yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Platform', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Canonical?', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'External URL', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Created', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log_rows as $row ) :
					$plabel = $platforms[ $row['platform'] ]['label'] ?? ucfirst( $row['platform'] );
					$status_pill = 'published' === $row['status'] ? 'rwai-pill-ok' : 'rwai-pill-warn';
				?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ?: ( '#' . (int) $row['post_id'] ) ); ?></a>
						</td>
						<td><?php echo esc_html( $plabel ); ?></td>
						<td><span class="rwai-pill <?php echo esc_attr( $status_pill ); ?>"><?php echo esc_html( strtoupper( $row['status'] ) ); ?></span></td>
						<td><?php echo ! empty( $row['canonical_used'] ) ? '✓' : '—'; ?></td>
						<td>
							<?php if ( ! empty( $row['external_url'] ) ) : ?>
								<a href="<?php echo esc_url( $row['external_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_trim_words( $row['external_url'], 6 ) ); ?></a>
							<?php else : ?>
								<span class="rwai-muted">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['created_at'] ) ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PARASITE_SLUG, array( 'log_id' => $row['id'] ) ) ); ?>"><?php esc_html_e( 'View', 'rankwriter-ai' ); ?></a>
							<form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_attr( __( 'Delete this syndication entry?', 'rankwriter-ai' ) ); ?>');">
								<input type="hidden" name="rwai_action" value="parasite_delete" />
								<input type="hidden" name="log_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
								<?php wp_nonce_field( RankWriter_AI_Admin::PARASITE_NONCE ); ?>
								<button type="submit" class="button button-small" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
