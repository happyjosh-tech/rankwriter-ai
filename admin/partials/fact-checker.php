<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$post_id = (int) ( $data['post_id'] ?? 0 );
$report  = (array) ( $data['report'] ?? array() );
$msg     = (string) ( $data['msg'] ?? '' );

$has_report = ! empty( $report['generated_at'] );
$confidence = (int) ( $report['fact_confidence_score'] ?? 0 );
$freshness  = (int) ( $report['freshness_score'] ?? 0 );
$warnings   = (array) ( $report['warnings'] ?? array() );
$checks     = (array) ( $report['checks'] ?? array() );

$conf_band  = $confidence >= 80 ? 'rwai-tl-bar-ok' : ( $confidence >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
$fresh_band = $freshness  >= 80 ? 'rwai-tl-bar-ok' : ( $freshness  >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Fact Checker', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Validate dates, deadlines, visa terms, salary figures, statistics, and outbound links. Combines free heuristic checks with an optional Claude review pass for nuanced claims.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'fact-checked' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fact check complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'fact-missing' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Post not found.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Check a post', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-inline-form">
			<input type="hidden" name="rwai_action" value="fact_check_post" />
			<?php wp_nonce_field( RankWriter_AI_Admin::FACT_NONCE ); ?>
			<label><?php esc_html_e( 'Post ID or paste a post URL:', 'rankwriter-ai' ); ?></label>
			<input type="text" name="post_ref" class="regular-text" value="<?php echo esc_attr( $post_id ?: '' ); ?>" placeholder="e.g. 123 or https://yoursite.com/your-post-slug/" required />
			<label style="margin-left:12px;"><input type="checkbox" name="use_claude" value="1" checked /> <?php esc_html_e( 'Run Claude review (uses API credit)', 'rankwriter-ai' ); ?></label>
			<button type="submit" class="button button-primary"><?php esc_html_e( '🔍 Run fact check', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<?php if ( $has_report && $post_id ) :
		$post_obj = get_post( $post_id );
	?>
		<div class="rwai-card rwai-card-wide">
			<h2>
				<?php echo esc_html( $post_obj ? $post_obj->post_title : sprintf( __( 'Post #%d', 'rankwriter-ai' ), $post_id ) ); ?>
				<?php if ( $post_obj ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small" style="float:right;"><?php esc_html_e( 'Edit post', 'rankwriter-ai' ); ?></a>
				<?php endif; ?>
			</h2>

			<div class="rwai-grid rwai-grid-2" style="margin-top:14px;">
				<div>
					<strong><?php esc_html_e( 'Fact confidence', 'rankwriter-ai' ); ?></strong>
					<div class="rwai-tl-bar-row" style="margin-top:6px;">
						<div class="rwai-tl-bar-label"><?php echo esc_html( $confidence ); ?>/100</div>
						<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $conf_band ); ?>" style="width:<?php echo esc_attr( $confidence ); ?>%"></span></div>
						<div class="rwai-tl-bar-score">&nbsp;</div>
					</div>
				</div>
				<div>
					<strong><?php esc_html_e( 'Freshness', 'rankwriter-ai' ); ?></strong>
					<div class="rwai-tl-bar-row" style="margin-top:6px;">
						<div class="rwai-tl-bar-label"><?php echo esc_html( $freshness ); ?>/100</div>
						<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $fresh_band ); ?>" style="width:<?php echo esc_attr( $freshness ); ?>%"></span></div>
						<div class="rwai-tl-bar-score">&nbsp;</div>
					</div>
				</div>
			</div>

			<p style="margin-top:14px;" class="rwai-muted">
				<?php echo esc_html( sprintf( __( 'Post age: %1$d days · Generated: %2$s', 'rankwriter-ai' ), (int) ( $report['post_age_days'] ?? 0 ), $report['generated_at'] ) ); ?>
				<?php if ( ! empty( $report['outdated'] ) ) : ?>
					<span class="rwai-pill rwai-pill-bad" style="margin-left:8px;"><?php esc_html_e( 'OUTDATED', 'rankwriter-ai' ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $warnings ) ) : ?>
				<h3 style="margin-top:18px;"><?php esc_html_e( 'Verification warnings', 'rankwriter-ai' ); ?> <span class="rwai-muted">(<?php echo (int) count( $warnings ); ?>)</span></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Severity', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Type', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Claim', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Detail', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Suggestion', 'rankwriter-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $warnings as $w ) :
						$sev   = (string) ( $w['severity'] ?? 'info' );
						$pill  = 'error' === $sev ? 'rwai-pill-bad' : ( 'warning' === $sev ? 'rwai-pill-warn' : 'rwai-pill-ok' );
					?>
						<tr>
							<td><span class="rwai-pill <?php echo esc_attr( $pill ); ?>"><?php echo esc_html( strtoupper( $sev ) ); ?></span></td>
							<td><code><?php echo esc_html( (string) ( $w['type'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( (string) ( $w['text'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $w['detail'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $w['suggestion'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="rwai-muted" style="margin-top:18px;"><?php esc_html_e( 'No warnings — every detected claim passed the validation checks.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>

			<h3 style="margin-top:24px;"><?php esc_html_e( 'Detected claims', 'rankwriter-ai' ); ?></h3>
			<div class="rwai-grid rwai-grid-3">
				<?php foreach ( array(
					'dates'      => __( 'Dates', 'rankwriter-ai' ),
					'deadlines'  => __( 'Deadlines', 'rankwriter-ai' ),
					'visa'       => __( 'Visa terms', 'rankwriter-ai' ),
					'salary'     => __( 'Salary figures', 'rankwriter-ai' ),
					'statistics' => __( 'Statistics', 'rankwriter-ai' ),
					'links'      => __( 'Outbound links', 'rankwriter-ai' ),
				) as $key => $label ) :
					$count = (int) ( $checks[ $key ]['found'] ?? 0 );
				?>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php echo esc_html( $label ); ?></div>
						<div class="rwai-cpc-summary-value"><?php echo esc_html( $count ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $checks['links']['items'] ) ) : ?>
				<h3 style="margin-top:24px;"><?php esc_html_e( 'Outbound link audit', 'rankwriter-ai' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'URL', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Host', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Internal?', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Official source?', 'rankwriter-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $checks['links']['items'] as $l ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_trim_words( $l['url'], 8 ) ); ?></a></td>
								<td><?php echo esc_html( $l['host'] ); ?></td>
								<td><?php echo ! empty( $l['is_internal'] ) ? '✓' : '—'; ?></td>
								<td><?php echo ! empty( $l['is_official'] ) ? '✓' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
