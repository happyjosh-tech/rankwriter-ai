<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings = (array) ( $data['settings'] ?? array() );
$status   = (array) ( $data['status'] ?? array() );
$preflight= (array) ( $data['db_preflight'] ?? array() );
$logs     = (array) ( $data['logs'] ?? array() );
$psi_last = (array) ( $data['psi_last'] ?? array() );
$msg      = (string) ( $data['msg'] ?? '' );
$err      = (string) ( $data['err'] ?? '' );

$mode      = $settings['mode'] ?? 'balanced';
$enabled   = ! empty( $settings['enabled'] );
$mode_pill = function ( $m ) use ( $mode ) {
	return $mode === $m ? 'rwai-pill-ok' : 'rwai-pill-muted';
};
$bytes_fmt = function ( $b ) {
	$b = (int) $b;
	if ( $b < 1024 ) { return $b . ' B'; }
	if ( $b < 1048576 ) { return number_format_i18n( $b / 1024, 1 ) . ' KB'; }
	return number_format_i18n( $b / 1048576, 1 ) . ' MB';
};
?>
<div class="wrap rwai-wrap rwai-speed">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'RankWriter Site Speed Optimizer', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />
	<p class="rwai-lede"><?php esc_html_e( "One-click safe speed optimization for WordPress. Page caching, CSS/JS minify, image lazy-load, Core Web Vitals nudges — all reversible, none of which alter your design, content, or ads. The mode selector is the safety dial.", 'rankwriter-ai' ); ?></p>

	<?php if ( 'speed-optimized' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Speed optimization applied. Browse the site in an incognito window to confirm everything renders.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-cache-cleared' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache cleared.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-db-cleaned' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Database cleanup complete.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-images-done' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Image batch optimized.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-restored' === $msg ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Previous settings restored. Cache cleared.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-disabled' === $msg ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Speed Optimizer disabled. Your original site behavior is back.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'speed-error' === $msg && $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<!-- ====================== Top status row ====================== -->
	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Module status', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo $enabled ? '<span style="color:#1f7d36;">' . esc_html__( 'ENABLED', 'rankwriter-ai' ) . '</span>' : '<span style="color:#888;">' . esc_html__( 'OFF', 'rankwriter-ai' ) . '</span>'; ?></div>
			<div class="rwai-muted"><?php echo esc_html( strtoupper( $mode ) ); ?> <?php esc_html_e( 'mode', 'rankwriter-ai' ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Cached pages', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( number_format_i18n( (int) ( $status['cache_files'] ?? 0 ) ) ); ?></div>
			<div class="rwai-muted"><?php echo esc_html( $bytes_fmt( (int) ( $status['cache_size'] ?? 0 ) ) ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Images WebP-converted', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( number_format_i18n( (int) ( $status['image_stats']['webp_generated'] ?? 0 ) ) ); ?></div>
			<div class="rwai-muted">
				<?php if ( ! empty( $status['webp_supported'] ) ) : ?>
					<?php esc_html_e( 'WebP supported by server', 'rankwriter-ai' ); ?>
				<?php else : ?>
					<span style="color:#b32d2e;"><?php esc_html_e( 'WebP not supported on this server', 'rankwriter-ai' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Last optimized', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;">
				<?php $ts = $status['last_status']['last_optimized_at'] ?? ''; ?>
				<?php echo $ts ? esc_html( $ts ) : '—'; ?>
			</div>
		</div>
	</div>

	<!-- ====================== One-click optimize ====================== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'One-Click Optimize', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted" style="margin-top:0;"><?php esc_html_e( 'Pick a mode and click optimize. Aggressive is the most performant but is the most likely to interact with quirky themes — start with Balanced.', 'rankwriter-ai' ); ?></p>

		<form method="post" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
			<input type="hidden" name="rwai_action" value="speed_optimize_now" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>
			<label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
				<input type="radio" name="mode" value="safe" <?php checked( $mode, 'safe' ); ?> />
				<strong><?php esc_html_e( 'Safe', 'rankwriter-ai' ); ?></strong>
				<span class="rwai-muted">(<?php esc_html_e( 'cache + minify only', 'rankwriter-ai' ); ?>)</span>
			</label>
			<label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
				<input type="radio" name="mode" value="balanced" <?php checked( $mode, 'balanced' ); ?> />
				<strong><?php esc_html_e( 'Balanced', 'rankwriter-ai' ); ?></strong>
				<span class="rwai-muted">(<?php esc_html_e( 'recommended', 'rankwriter-ai' ); ?>)</span>
			</label>
			<label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
				<input type="radio" name="mode" value="aggressive" <?php checked( $mode, 'aggressive' ); ?> />
				<strong><?php esc_html_e( 'Aggressive', 'rankwriter-ai' ); ?></strong>
				<span class="rwai-muted">(<?php esc_html_e( 'CSS/JS defer + delay', 'rankwriter-ai' ); ?>)</span>
			</label>
			<button type="submit" class="button button-primary button-large" style="margin-left:auto;">
				<?php esc_html_e( '⚡ Optimize Site Speed Now', 'rankwriter-ai' ); ?>
			</button>
		</form>
	</div>

	<!-- ====================== Settings form ====================== -->
	<form method="post" class="rwai-speed-settings">
		<input type="hidden" name="rwai_action" value="speed_save_settings" />
		<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'Page caching', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[cache_enabled]" value="1" <?php checked( ! empty( $settings['cache_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable static HTML page cache', 'rankwriter-ai' ); ?></label>
			<p>
				<label><?php esc_html_e( 'Cache TTL (seconds):', 'rankwriter-ai' ); ?>
					<input type="number" name="rwai_speed[cache_ttl]" min="60" max="604800" step="60" value="<?php echo esc_attr( (int) $settings['cache_ttl'] ); ?>" style="width:120px;" />
				</label>
			</p>
			<label><?php esc_html_e( 'Bypass cache for these URL fragments (one per line):', 'rankwriter-ai' ); ?></label>
			<textarea name="rwai_speed[cache_exclusions]" rows="4" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( (string) $settings['cache_exclusions'] ); ?></textarea>
			<p>
				<a href="<?php echo esc_url( add_query_arg( array( 'rwai_action' => 'speed_clear_cache', '_wpnonce' => wp_create_nonce( RankWriter_AI_Admin::SPEED_NONCE ) ), admin_url( 'admin-post.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Clear cache now', 'rankwriter-ai' ); ?></a>
			</p>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'Browser caching', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[browser_cache_enabled]" value="1" <?php checked( ! empty( $settings['browser_cache_enabled'] ) ); ?> /> <?php esc_html_e( 'Send Cache-Control headers from PHP', 'rankwriter-ai' ); ?></label>
			<p class="rwai-muted"><?php esc_html_e( 'For longest-lived caching of static assets (1-year on CSS/JS/images), paste the snippet below into your .htaccess file. We intentionally do not modify .htaccess automatically.', 'rankwriter-ai' ); ?></p>
			<textarea readonly rows="14" style="width:100%;font-family:monospace;font-size:11px;background:#f6f7f9;"><?php echo esc_textarea( RankWriter_AI_Browser_Cache::htaccess_snippet() ); ?></textarea>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'CSS optimization', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[css_minify]" value="1" <?php checked( ! empty( $settings['css_minify'] ) ); ?> /> <?php esc_html_e( 'Minify local stylesheets', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[css_defer]" value="1" <?php checked( ! empty( $settings['css_defer'] ) ); ?> /> <?php esc_html_e( 'Defer non-critical CSS (requires a critical CSS block below)', 'rankwriter-ai' ); ?></label>
			<p>
				<label><?php esc_html_e( 'Critical CSS (inlined in <head>; leave empty to skip the defer trick):', 'rankwriter-ai' ); ?></label>
				<textarea name="rwai_speed[critical_css]" rows="6" placeholder="/* Paste above-the-fold CSS here */" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( (string) $settings['critical_css'] ); ?></textarea>
			</p>
			<p>
				<label><?php esc_html_e( 'CSS exclusions (one URL fragment or handle per line):', 'rankwriter-ai' ); ?></label>
				<textarea name="rwai_speed[css_exclusions]" rows="3" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( (string) $settings['css_exclusions'] ); ?></textarea>
			</p>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'JavaScript optimization', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[js_minify]" value="1" <?php checked( ! empty( $settings['js_minify'] ) ); ?> /> <?php esc_html_e( 'Minify local scripts', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[js_defer]" value="1" <?php checked( ! empty( $settings['js_defer'] ) ); ?> /> <?php esc_html_e( 'Defer non-essential scripts', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[js_delay]" value="1" <?php checked( ! empty( $settings['js_delay'] ) ); ?> /> <?php esc_html_e( 'Delay analytics / social / chat scripts until first user interaction', 'rankwriter-ai' ); ?></label>
			<p class="rwai-muted"><?php esc_html_e( "AdSense, Stripe, PayPal, reCAPTCHA, jQuery, WooCommerce checkout, and login scripts are protected — never deferred or delayed.", 'rankwriter-ai' ); ?></p>
			<p>
				<label><?php esc_html_e( 'JS exclusions (one URL fragment or handle per line):', 'rankwriter-ai' ); ?></label>
				<textarea name="rwai_speed[js_exclusions]" rows="3" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( (string) $settings['js_exclusions'] ); ?></textarea>
			</p>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'Image optimization', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[image_lazyload]" value="1" <?php checked( ! empty( $settings['image_lazyload'] ) ); ?> /> <?php esc_html_e( 'Lazy-load images below the fold (skips first image so LCP stays fast)', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[image_dims]" value="1" <?php checked( ! empty( $settings['image_dims'] ) ); ?> /> <?php esc_html_e( 'Inject missing width / height (reduces Cumulative Layout Shift)', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[image_webp]" value="1" <?php checked( ! empty( $settings['image_webp'] ) ); ?> /> <?php esc_html_e( 'Serve WebP versions where available', 'rankwriter-ai' ); ?></label>
			<p>
				<a href="<?php echo esc_url( add_query_arg( array( 'rwai_action' => 'speed_bulk_webp', '_wpnonce' => wp_create_nonce( RankWriter_AI_Admin::SPEED_NONCE ) ), admin_url( 'admin-post.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Optimize images (batch of 50)', 'rankwriter-ai' ); ?></a>
				<span class="rwai-muted"><?php
					$st = (array) ( $status['image_stats'] ?? array() );
					/* translators: %1$d generated, %2$d skipped */
					echo esc_html( sprintf( __( 'WebP generated: %1$d · skipped: %2$d', 'rankwriter-ai' ), (int) ( $st['webp_generated'] ?? 0 ), (int) ( $st['webp_skipped'] ?? 0 ) ) );
				?></span>
			</p>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'Core Web Vitals', 'rankwriter-ai' ); ?></h2>
			<label><input type="checkbox" name="rwai_speed[cwv_fetchpriority_lcp]" value="1" <?php checked( ! empty( $settings['cwv_fetchpriority_lcp'] ) ); ?> /> <?php esc_html_e( 'Mark the first content image fetchpriority="high" (LCP boost)', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[cwv_preload_featured]" value="1" <?php checked( ! empty( $settings['cwv_preload_featured'] ) ); ?> /> <?php esc_html_e( 'Preload featured image on single posts', 'rankwriter-ai' ); ?></label><br>
			<label><input type="checkbox" name="rwai_speed[cwv_preload_fonts]" value="1" <?php checked( ! empty( $settings['cwv_preload_fonts'] ) ); ?> /> <?php esc_html_e( 'Preload important fonts', 'rankwriter-ai' ); ?></label>
			<p>
				<label><?php esc_html_e( 'Font URLs to preload (one per line):', 'rankwriter-ai' ); ?></label>
				<textarea name="rwai_speed[preload_font_urls]" rows="3" style="width:100%;font-family:monospace;font-size:12px;" placeholder="https://example.com/wp-content/themes/your-theme/fonts/inter.woff2"><?php echo esc_textarea( (string) $settings['preload_font_urls'] ); ?></textarea>
			</p>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'PageSpeed Insights (optional)', 'rankwriter-ai' ); ?></h2>
			<p class="rwai-muted"><?php esc_html_e( 'Drop in a free Google PageSpeed API key to fetch real mobile + desktop scores. Without a key, we still show internal status above but not lighthouse numbers.', 'rankwriter-ai' ); ?></p>
			<label><?php esc_html_e( 'API key:', 'rankwriter-ai' ); ?>
				<input type="text" name="rwai_speed[pagespeed_api_key]" value="<?php echo esc_attr( $settings['pagespeed_api_key'] ); ?>" autocomplete="off" style="width:380px;font-family:monospace;" placeholder="AIzaSy..." />
			</label>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'rankwriter-ai' ); ?></button>
		</p>
	</form>

	<!-- ====================== Speed test (PSI) ====================== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Speed test', 'rankwriter-ai' ); ?></h2>
		<form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<input type="hidden" name="rwai_action" value="speed_run_test" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>
			<input type="url" name="test_url" value="<?php echo esc_attr( home_url( '/' ) ); ?>" required style="min-width:340px;" />
			<select name="strategy">
				<option value="mobile"><?php esc_html_e( 'Mobile', 'rankwriter-ai' ); ?></option>
				<option value="desktop"><?php esc_html_e( 'Desktop', 'rankwriter-ai' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Run speed test', 'rankwriter-ai' ); ?></button>
		</form>
		<?php if ( ! empty( $psi_last ) && empty( $psi_last['error'] ) ) : ?>
			<div style="margin-top:14px;padding:12px;background:#f6f7f9;border-radius:6px;">
				<strong><?php esc_html_e( 'Latest result', 'rankwriter-ai' ); ?>
					(<?php echo esc_html( $psi_last['strategy'] ?? '' ); ?>):</strong>
				<?php esc_html_e( 'score', 'rankwriter-ai' ); ?>
				<strong><?php echo esc_html( $psi_last['score'] ?? '—' ); ?>/100</strong>
				<?php $m = (array) ( $psi_last['metrics'] ?? array() ); ?>
				<span class="rwai-muted">
					· LCP <?php echo esc_html( isset( $m['lcp_ms'] ) ? round( $m['lcp_ms'] / 1000, 2 ) . 's' : '—' ); ?>
					· CLS <?php echo esc_html( isset( $m['cls'] ) ? $m['cls'] : '—' ); ?>
					· TBT <?php echo esc_html( isset( $m['tbt_ms'] ) ? $m['tbt_ms'] . 'ms' : '—' ); ?>
					· FCP <?php echo esc_html( isset( $m['fcp_ms'] ) ? round( $m['fcp_ms'] / 1000, 2 ) . 's' : '—' ); ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<!-- ====================== Database cleanup ====================== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Database cleanup', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( "Removes only safe rows: post revisions, auto-drafts, trashed posts, spam comments, expired transients, and orphan post-meta. Active content, settings, users, and orders are never touched. We recommend taking a database backup before your first run.", 'rankwriter-ai' ); ?></p>
		<table class="widefat striped" style="max-width:560px;">
			<tr><td><?php esc_html_e( 'Post revisions', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['revisions'] ?? 0 ) ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Auto-drafts', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['auto_drafts'] ?? 0 ) ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Trashed posts', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['trashed'] ?? 0 ) ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Spam / trashed comments', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['spam'] ?? 0 ) ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Expired transients', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['transients'] ?? 0 ) ) ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Orphan post-meta', 'rankwriter-ai' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) ( $preflight['orphan_meta'] ?? 0 ) ) ); ?></strong></td></tr>
		</table>
		<form method="post" style="margin-top:12px;" onsubmit="return confirm('<?php echo esc_attr( __( 'Run database cleanup now? Take a database backup first if you haven\'t already.', 'rankwriter-ai' ) ); ?>');">
			<input type="hidden" name="rwai_action" value="speed_db_clean" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>
			<button type="submit" class="button"><?php esc_html_e( '🧹 Clean database now', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<!-- ====================== Restore / Disable ====================== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Rollback & safety', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( "If the site looks broken after optimization, restore the snapshot we took the first time the optimizer was turned on. It re-saves your pre-optimization settings and wipes the page cache.", 'rankwriter-ai' ); ?></p>
		<form method="post" style="display:inline-block;margin-right:10px;" onsubmit="return confirm('<?php echo esc_attr( __( 'Restore the pre-optimization settings snapshot? The cache will be cleared.', 'rankwriter-ai' ) ); ?>');">
			<input type="hidden" name="rwai_action" value="speed_restore" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Restore previous settings', 'rankwriter-ai' ); ?></button>
		</form>
		<form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_attr( __( 'Disable Speed Optimizer entirely?', 'rankwriter-ai' ) ); ?>');">
			<input type="hidden" name="rwai_action" value="speed_disable" />
			<?php wp_nonce_field( RankWriter_AI_Admin::SPEED_NONCE ); ?>
			<button type="submit" class="button" style="color:#b32d2e;"><?php esc_html_e( 'Disable Speed Optimizer', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<!-- ====================== Activity log ====================== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Activity log', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $logs ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No activity yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th style="width:160px;"><?php esc_html_e( 'When', 'rankwriter-ai' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Action', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $logs as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['time'] ); ?></td>
							<td><code><?php echo esc_html( (string) $row['action'] ); ?></code></td>
							<td><span class="rwai-muted"><?php echo esc_html( (string) $row['detail'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
