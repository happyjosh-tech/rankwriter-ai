<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings  = (array) ( $data['settings'] ?? array() );
$inventory = (array) ( $data['inventory'] ?? array() );
$log       = (array) ( $data['log'] ?? array() );
$msg       = (string) ( $data['msg'] ?? '' );
$err       = (string) ( $data['err'] ?? '' );
$quota_used = (int) ( $data['quota_used_24h'] ?? 0 );
$next_run  = (int) ( $data['next_run_ts'] ?? 0 );
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Auto Update Old Articles', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'A lightweight background worker that detects stale posts, refreshes statistics + dates + readability via Claude, and re-runs internal linking — all without changing your URL slugs or page titles. Every refresh saves a WP revision so you can roll back.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'refresher-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'refresher-ran' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Refresh complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'refresher-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ? $err : __( 'Refresh failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Auto-refresh', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo ! empty( $settings['enabled'] ) ? '<span class="rwai-pill rwai-pill-ok">ON</span>' : '<span class="rwai-pill rwai-pill-warn">OFF</span>'; ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Refreshed in last 24h', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $quota_used . ' / ' . (int) $settings['daily_quota'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Next tick', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;"><?php echo esc_html( $next_run ? human_time_diff( time(), $next_run ) : '—' ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Stale posts found', 'rankwriter-ai' ); ?></div>
			<?php
			$stale_under = 0;
			$threshold   = (int) $settings['min_freshness_score'];
			foreach ( $inventory as $i ) {
				if ( (int) $i['freshness_score'] < $threshold ) { $stale_under++; }
			}
			?>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( $stale_under ); ?></div>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Scheduler & detection rules', 'rankwriter-ai' ); ?></h2>
		<form method="post">
			<input type="hidden" name="rwai_action" value="save_refresher_settings" />
			<?php wp_nonce_field( RankWriter_AI_Admin::REFRESH_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_ref_enabled"><?php esc_html_e( 'Enable auto-refresh', 'rankwriter-ai' ); ?></label></th>
					<td><label><input type="checkbox" id="rwai_ref_enabled" name="rwai_refresher[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> /> <?php esc_html_e( 'Run hourly tick and refresh the stalest qualifying post', 'rankwriter-ai' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="rwai_ref_threshold"><?php esc_html_e( 'Stale post age (days)', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" id="rwai_ref_threshold" name="rwai_refresher[stale_threshold_days]" value="<?php echo esc_attr( $settings['stale_threshold_days'] ); ?>" min="30" max="3650" />
						<p class="description"><?php esc_html_e( 'Only posts whose modified date is older than this are eligible.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_ref_minfresh"><?php esc_html_e( 'Refresh when freshness <', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" id="rwai_ref_minfresh" name="rwai_refresher[min_freshness_score]" value="<?php echo esc_attr( $settings['min_freshness_score'] ); ?>" min="0" max="100" />
						<p class="description"><?php esc_html_e( 'A post must score under this freshness number (0-100) to be picked. Try 60 to start.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_ref_gap"><?php esc_html_e( 'Min interval between refreshes (days)', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" id="rwai_ref_gap" name="rwai_refresher[min_interval_days]" value="<?php echo esc_attr( $settings['min_interval_days'] ); ?>" min="7" max="365" />
						<p class="description"><?php esc_html_e( 'Never re-refresh the same post inside this window.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_ref_quota"><?php esc_html_e( 'Daily quota', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" id="rwai_ref_quota" name="rwai_refresher[daily_quota]" value="<?php echo esc_attr( $settings['daily_quota'] ); ?>" min="1" max="50" />
						<p class="description"><?php esc_html_e( 'Max refreshes per 24h. Each refresh = 1 Claude call.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Preservation rules', 'rankwriter-ai' ); ?></th>
					<td>
						<label style="display:block;"><input type="checkbox" name="rwai_refresher[preserve_url]" value="1" <?php checked( $settings['preserve_url'] ); ?> /> <?php esc_html_e( 'Preserve URL slug (never change post_name) — protects your rankings.', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="rwai_refresher[preserve_title]" value="1" <?php checked( $settings['preserve_title'] ); ?> /> <?php esc_html_e( 'Preserve title (H1) exactly.', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="rwai_refresher[use_claude]" value="1" <?php checked( $settings['use_claude'] ); ?> /> <?php esc_html_e( 'Use Claude for the rewrite pass (off = freshness scoring only).', 'rankwriter-ai' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'rankwriter-ai' ) ); ?>
		</form>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Stale inventory', 'rankwriter-ai' ); ?> <span class="rwai-muted">(<?php echo (int) count( $inventory ); ?> scanned)</span></h2>
		<?php if ( empty( $inventory ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No published posts found.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Last modified', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Age', 'rankwriter-ai' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Freshness', 'rankwriter-ai' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $inventory, 0, 50 ) as $row ) :
					$score = (int) $row['freshness_score'];
					$band  = $score >= 80 ? 'rwai-tl-bar-ok' : ( $score >= 50 ? 'rwai-tl-bar-warn' : 'rwai-tl-bar-bad' );
				?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>"><strong><?php echo esc_html( wp_trim_words( $row['title'], 10 ) ); ?></strong></a>
							<br><a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::FACT_SLUG, array( 'post_id' => $row['post_id'] ) ) ); ?>" class="rwai-muted">🔍 <?php esc_html_e( 'Fact check', 'rankwriter-ai' ); ?></a>
						</td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['modified'] ) ); ?></td>
						<td><?php echo esc_html( $row['age_days'] . 'd' ); ?></td>
						<td>
							<div class="rwai-tl-bar-row">
								<div class="rwai-tl-bar-label"><?php echo esc_html( $score ); ?></div>
								<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $band ); ?>" style="width:<?php echo esc_attr( $score ); ?>%"></span></div>
								<div class="rwai-tl-bar-score">/100</div>
							</div>
						</td>
						<td>
							<form method="post" style="display:inline-block;">
								<input type="hidden" name="rwai_action" value="refresh_post_now" />
								<input type="hidden" name="post_id" value="<?php echo esc_attr( $row['post_id'] ); ?>" />
								<?php wp_nonce_field( RankWriter_AI_Admin::REFRESH_NONCE ); ?>
								<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Refresh now', 'rankwriter-ai' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Update log', 'rankwriter-ai' ); ?> <span class="rwai-muted">(<?php echo (int) count( $log ); ?>)</span></h2>
		<?php if ( empty( $log ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No refreshes recorded yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Freshness Δ', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Summary', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) :
					$before = (int) $row['freshness_before'];
					$after  = (int) $row['freshness_after'];
					$delta  = $after - $before;
					$pill   = $delta > 0 ? 'rwai-pill-ok' : ( $delta < 0 ? 'rwai-pill-bad' : 'rwai-pill-warn' );
					$post_t = get_the_title( (int) $row['post_id'] );
				?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['refreshed_at'] ) ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( $post_t ?: ( '#' . (int) $row['post_id'] ) ); ?></a></td>
						<td><span class="rwai-pill <?php echo 'success' === $row['status'] ? 'rwai-pill-ok' : 'rwai-pill-bad'; ?>"><?php echo esc_html( strtoupper( $row['status'] ) ); ?></span></td>
						<td><span class="rwai-pill <?php echo esc_attr( $pill ); ?>"><?php echo esc_html( $before . ' → ' . $after . ' (' . sprintf( '%+d', $delta ) . ')' ); ?></span></td>
						<td><?php echo esc_html( (string) $row['summary'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
