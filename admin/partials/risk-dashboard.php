<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$post_id = (int) ( $data['post_id'] ?? 0 );
$report  = (array) ( $data['report'] ?? array() );
$inventory = (array) ( $data['inventory'] ?? array() );
$msg     = (string) ( $data['msg'] ?? '' );
$err     = (string) ( $data['err'] ?? '' );

$category_labels = array(
	'medical'      => __( 'Medical safety', 'rankwriter-ai' ),
	'financial'    => __( 'Financial promises', 'rankwriter-ai' ),
	'immigration'  => __( 'Immigration guarantees', 'rankwriter-ai' ),
	'adult'        => __( 'Adult content', 'rankwriter-ai' ),
	'spam'         => __( 'Spam / clickbait', 'rankwriter-ai' ),
	'misleading'   => __( 'Misleading claims', 'rankwriter-ai' ),
	'copyright'    => __( 'Copyright risk', 'rankwriter-ai' ),
	'plagiarism'   => __( 'Plagiarism risk', 'rankwriter-ai' ),
	'claude_review'=> __( 'Claude deep review', 'rankwriter-ai' ),
);

if ( ! function_exists( 'rwai_risk_pill' ) ) {
	function rwai_risk_pill( $band ) {
		switch ( $band ) {
			case 'high':   return 'rwai-pill-bad';
			case 'medium': return 'rwai-pill-warn';
			case 'low':    return 'rwai-pill-warn';
			default:       return 'rwai-pill-ok';
		}
	}
}

if ( ! function_exists( 'rwai_sev_pill' ) ) {
	function rwai_sev_pill( $sev ) {
		switch ( $sev ) {
			case 'critical': return 'rwai-pill-bad';
			case 'error':    return 'rwai-pill-bad';
			case 'warning':  return 'rwai-pill-warn';
			default:         return 'rwai-pill-ok';
		}
	}
}
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Content Risk Detector', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Scans posts for medical / financial / immigration over-promises, fake urgency, copyright exposure, internal plagiarism, and Google YMYL / AdSense policy violations. Every newly generated post is auto-scanned; you can also audit any post manually.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'risk-scanned' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Scan complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'risk-missing' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Post not found.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'risk-error' === $msg && $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Scan a post', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-inline-form">
			<input type="hidden" name="rwai_action" value="risk_scan_post" />
			<?php wp_nonce_field( RankWriter_AI_Admin::RISK_NONCE ); ?>
			<label><?php esc_html_e( 'Post ID or URL:', 'rankwriter-ai' ); ?></label>
			<input type="text" name="post_ref" class="regular-text" value="<?php echo esc_attr( $post_id ?: '' ); ?>" required />
			<label style="margin-left:10px;"><input type="checkbox" name="use_claude" value="1" checked /> <?php esc_html_e( 'Claude deep review (costs API credit)', 'rankwriter-ai' ); ?></label>
			<button type="submit" class="button button-primary"><?php esc_html_e( '🛡️ Run risk scan', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $report ) && $post_id ) :
		$post_obj = get_post( $post_id );
		$risk    = (int) ( $report['risk_score'] ?? 0 );
		$adsense = (int) ( $report['adsense_compliance_score'] ?? 100 );
		$band    = (string) ( $report['risk_band'] ?? 'safe' );
		$risk_band  = $risk >= 70 ? 'rwai-tl-bar-bad' : ( $risk >= 35 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-ok' );
		$ads_band   = $adsense >= 80 ? 'rwai-tl-bar-ok' : ( $adsense >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
		$warnings   = (array) ( $report['warnings'] ?? array() );
	?>
		<div class="rwai-card rwai-card-wide">
			<h2>
				<?php echo esc_html( $post_obj ? wp_trim_words( $post_obj->post_title, 12 ) : '#' . $post_id ); ?>
				<?php if ( $post_obj ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small" style="float:right;"><?php esc_html_e( 'Edit post', 'rankwriter-ai' ); ?></a>
				<?php endif; ?>
			</h2>

			<div class="rwai-grid rwai-grid-2" style="margin-top:14px;">
				<div>
					<strong><?php esc_html_e( 'Risk score', 'rankwriter-ai' ); ?></strong>
					<span class="rwai-pill <?php echo esc_attr( rwai_risk_pill( $band ) ); ?>" style="margin-left:8px;"><?php echo esc_html( strtoupper( $band ) ); ?></span>
					<div class="rwai-tl-bar-row" style="margin-top:6px;">
						<div class="rwai-tl-bar-label"><?php echo esc_html( $risk ); ?>/100</div>
						<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $risk_band ); ?>" style="width:<?php echo esc_attr( $risk ); ?>%"></span></div>
						<div class="rwai-tl-bar-score">&nbsp;</div>
					</div>
				</div>
				<div>
					<strong><?php esc_html_e( 'AdSense compliance', 'rankwriter-ai' ); ?></strong>
					<div class="rwai-tl-bar-row" style="margin-top:6px;">
						<div class="rwai-tl-bar-label"><?php echo esc_html( $adsense ); ?>/100</div>
						<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $ads_band ); ?>" style="width:<?php echo esc_attr( $adsense ); ?>%"></span></div>
						<div class="rwai-tl-bar-score">&nbsp;</div>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $report['should_block_publish'] ) ) : ?>
				<div class="notice notice-error" style="margin:14px 0 0;">
					<p style="margin:8px 12px;"><strong><?php esc_html_e( 'Publish risk: HIGH', 'rankwriter-ai' ); ?></strong> — <?php esc_html_e( 'This post will trigger a publish-time warning. Resolve the critical issues below first.', 'rankwriter-ai' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $warnings ) ) :
				$by_cat = array();
				foreach ( $warnings as $w ) {
					$by_cat[ $w['category'] ?? 'other' ][] = $w;
				}
			?>
				<h3 style="margin-top:18px;"><?php esc_html_e( 'Findings', 'rankwriter-ai' ); ?></h3>
				<?php foreach ( $by_cat as $cat => $items ) : ?>
					<h4 style="margin-top:14px;"><?php echo esc_html( $category_labels[ $cat ] ?? ucfirst( $cat ) ); ?> <span class="rwai-muted">(<?php echo (int) count( $items ); ?>)</span></h4>
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Severity', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Rule', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Match', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Why', 'rankwriter-ai' ); ?></th>
							<th><?php esc_html_e( 'Fix', 'rankwriter-ai' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $items as $w ) : ?>
							<tr>
								<td><span class="rwai-pill <?php echo esc_attr( rwai_sev_pill( $w['severity'] ?? '' ) ); ?>"><?php echo esc_html( strtoupper( $w['severity'] ?? '' ) ); ?></span></td>
								<td><code><?php echo esc_html( $w['rule'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( wp_trim_words( $w['text'] ?? '', 10 ) ); ?></td>
								<td><?php echo esc_html( $w['message'] ?? '' ); ?></td>
								<td><?php echo esc_html( $w['suggestion'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="rwai-muted" style="margin-top:18px;"><?php esc_html_e( '✓ No risk patterns detected.', 'rankwriter-ai' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Site-wide risk inventory', 'rankwriter-ai' ); ?>
			<form method="post" style="float:right;margin-top:-4px;">
				<input type="hidden" name="rwai_action" value="risk_bulk_rescan" />
				<?php wp_nonce_field( RankWriter_AI_Admin::RISK_NONCE ); ?>
				<button type="submit" class="button button-small"><?php esc_html_e( '↻ Rescan recent posts', 'rankwriter-ai' ); ?></button>
			</form>
		</h2>
		<?php if ( empty( $inventory ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No posts audited yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Modified', 'rankwriter-ai' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Risk', 'rankwriter-ai' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'AdSense', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $inventory as $row ) :
					$r  = (int) $row['risk'];
					$a  = (int) $row['adsense'];
					$rb = $r >= 70 ? 'rwai-tl-bar-bad' : ( $r >= 35 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-ok' );
					$ab = $a >= 80 ? 'rwai-tl-bar-ok' : ( $a >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
				?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>"><strong><?php echo esc_html( wp_trim_words( $row['title'], 10 ) ); ?></strong></a></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['modified'] ) ); ?></td>
						<td>
							<div class="rwai-tl-bar-row">
								<div class="rwai-tl-bar-label"><?php echo esc_html( $r ); ?></div>
								<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $rb ); ?>" style="width:<?php echo esc_attr( $r ); ?>%"></span></div>
								<div class="rwai-tl-bar-score">/100</div>
							</div>
						</td>
						<td>
							<div class="rwai-tl-bar-row">
								<div class="rwai-tl-bar-label"><?php echo esc_html( $a ); ?></div>
								<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $ab ); ?>" style="width:<?php echo esc_attr( $a ); ?>%"></span></div>
								<div class="rwai-tl-bar-score">/100</div>
							</div>
						</td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::RISK_SLUG, array( 'post_id' => $row['post_id'] ) ) ); ?>"><?php esc_html_e( 'View', 'rankwriter-ai' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
