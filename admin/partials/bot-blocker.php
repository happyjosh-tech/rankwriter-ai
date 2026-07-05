<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings      = (array) $data['settings'];
$countries     = (array) $data['countries'];
$log           = (array) $data['log'];
$blocked_24h   = (int) $data['blocked_24h'];
$top_countries = (array) $data['top_countries'];
$msg           = (string) $data['msg'];

$selected = array_flip( array_filter( explode( ',', (string) $settings['countries'] ) ) );
?>
<div class="wrap rwai-wrap rwai-bot-blocker-wrap">
	<h1>🛡️ <?php esc_html_e( 'Bot Blocker — country & IP access control', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Block visitors from specific countries or IP addresses before they ever load a page. Built to stop suspicious traffic (click-fraud bots, invalid AdSense clicks) coming from a country or IP range you\'ve identified as abusive.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'bot-blocker-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bot Blocker settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'bot-blocker-log-cleared' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Block log cleared.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card" style="padding:14px 18px;margin-bottom:18px;background:#f6f7f7;display:flex;gap:30px;flex-wrap:wrap;">
		<div>
			<div style="font-size:24px;font-weight:600;"><?php echo esc_html( RankWriter_AI_Helpers::format_number( $blocked_24h ) ); ?></div>
			<div class="description"><?php esc_html_e( 'Blocked in the last 24 hours', 'rankwriter-ai' ); ?></div>
		</div>
		<div>
			<div style="font-size:14px;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Top blocked countries', 'rankwriter-ai' ); ?></div>
			<?php if ( empty( $top_countries ) ) : ?>
				<div class="description"><?php esc_html_e( 'No blocks logged yet.', 'rankwriter-ai' ); ?></div>
			<?php else : ?>
				<div class="description">
					<?php
					$parts = array();
					foreach ( $top_countries as $row ) {
						$parts[] = sprintf( '%s (%d)', esc_html( RankWriter_AI_Bot_Blocker_DB::country_name( $row['country'] ) ), (int) $row['hits'] );
					}
					echo esc_html( implode( ' · ', $parts ) );
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="save_bot_blocker" />
		<?php wp_nonce_field( RankWriter_AI_Admin::BOT_BLOCKER_NONCE ); ?>

		<div class="rwai-card" style="padding:14px 18px;margin-bottom:18px;background:#f6f7f7;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Master switch', 'rankwriter-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_bb_enabled"><?php esc_html_e( 'Enable Bot Blocker', 'rankwriter-ai' ); ?></label></th>
					<td><label><input type="checkbox" id="rwai_bb_enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php esc_html_e( 'Turn on country/IP blocking for the public frontend.', 'rankwriter-ai' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Mode', 'rankwriter-ai' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="mode" value="blacklist" <?php checked( 'blacklist', $settings['mode'] ); ?> />
							<?php esc_html_e( 'Block only the countries I select below (everyone else gets in)', 'rankwriter-ai' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="mode" value="whitelist" <?php checked( 'whitelist', $settings['mode'] ); ?> />
							<?php esc_html_e( 'Allow only the countries I select below (everyone else is blocked)', 'rankwriter-ai' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Safety exemptions', 'rankwriter-ai' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="exempt_logged_in" value="1" <?php checked( ! empty( $settings['exempt_logged_in'] ) ); ?> /> <?php esc_html_e( 'Never block logged-in users (recommended — keeps you from locking yourself out)', 'rankwriter-ai' ); ?></label>
						<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="exempt_search_bots" value="1" <?php checked( ! empty( $settings['exempt_search_bots'] ) ); ?> /> <?php esc_html_e( 'Never block known search engine / AdSense crawlers (Googlebot, Mediapartners-Google, Bingbot, etc.) — protects your SEO and ad verification.', 'rankwriter-ai' ); ?></label>
						<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="geo_api_lookup" value="1" <?php checked( ! empty( $settings['geo_api_lookup'] ) ); ?> /> <?php esc_html_e( 'Look up country via a free IP-geolocation API when no CDN/host header is available (results are cached per IP for 7 days).', 'rankwriter-ai' ); ?></label>
						<label style="display:block;"><input type="checkbox" name="enable_logging" value="1" <?php checked( ! empty( $settings['enable_logging'] ) ); ?> /> <?php esc_html_e( 'Log every blocked visit below', 'rankwriter-ai' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_bb_message"><?php esc_html_e( 'Message shown to blocked visitors', 'rankwriter-ai' ); ?></label></th>
					<td>
						<textarea id="rwai_bb_message" name="block_message" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Access to this site is not available from your location.', 'rankwriter-ai' ); ?>"><?php echo esc_textarea( $settings['block_message'] ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>

		<div class="rwai-card" style="padding:14px 18px;margin-bottom:18px;background:#fff;border:1px solid #c3c4c7;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Countries', 'rankwriter-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Pick from the full country list below, or type extra codes by hand (comma-separated ISO codes, e.g. RU, CN, KP) if you need something not in the list.', 'rankwriter-ai' ); ?></p>

			<p>
				<input type="text" id="rwai_bb_country_search" class="regular-text" placeholder="<?php esc_attr_e( 'Search countries…', 'rankwriter-ai' ); ?>" />
				<button type="button" class="button" id="rwai_bb_select_all"><?php esc_html_e( 'Select all', 'rankwriter-ai' ); ?></button>
				<button type="button" class="button" id="rwai_bb_select_none"><?php esc_html_e( 'Clear all', 'rankwriter-ai' ); ?></button>
			</p>

			<div id="rwai_bb_country_list" style="columns:4;column-gap:20px;max-height:340px;overflow-y:auto;border:1px solid #dcdcde;padding:12px;background:#fafafa;">
				<?php foreach ( $countries as $code => $name ) : ?>
					<label class="rwai-bb-country-row" data-search="<?php echo esc_attr( strtolower( $name . ' ' . $code ) ); ?>" style="display:block;margin-bottom:4px;break-inside:avoid;">
						<input type="checkbox" name="countries[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( isset( $selected[ $code ] ) ); ?> />
						<?php echo esc_html( $name ); ?> <span class="description">(<?php echo esc_html( $code ); ?>)</span>
					</label>
				<?php endforeach; ?>
			</div>

			<p style="margin-top:14px;">
				<label for="rwai_bb_manual_countries"><strong><?php esc_html_e( 'Manually add country codes', 'rankwriter-ai' ); ?></strong></label><br>
				<input type="text" id="rwai_bb_manual_countries" name="manual_countries" value="<?php echo esc_attr( $settings['manual_countries'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. RU, CN, KP', 'rankwriter-ai' ); ?>" />
				<span class="description"><?php esc_html_e( 'Comma-separated ISO 3166-1 alpha-2 codes. Merged with whatever is checked above.', 'rankwriter-ai' ); ?></span>
			</p>
		</div>

		<div class="rwai-card" style="padding:14px 18px;margin-bottom:18px;background:#fff;border:1px solid #c3c4c7;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'IP addresses', 'rankwriter-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_bb_blocked_ips"><?php esc_html_e( 'Block these IPs', 'rankwriter-ai' ); ?></label></th>
					<td>
						<textarea id="rwai_bb_blocked_ips" name="blocked_ips" rows="5" class="large-text code" placeholder="203.0.113.4&#10;198.51.100.0/24"><?php echo esc_textarea( $settings['blocked_ips'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP or CIDR range per line. Checked in addition to the country rule above.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_bb_whitelisted_ips"><?php esc_html_e( 'Never block these IPs', 'rankwriter-ai' ); ?></label></th>
					<td>
						<textarea id="rwai_bb_whitelisted_ips" name="whitelisted_ips" rows="3" class="large-text code" placeholder="<?php esc_attr_e( 'Your own office/VPN IP, e.g. 203.0.113.10', 'rankwriter-ai' ); ?>"><?php echo esc_textarea( $settings['whitelisted_ips'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP or CIDR range per line. Always allowed through, even if the country rule would otherwise block it.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Bot Blocker settings', 'rankwriter-ai' ); ?></button></p>
	</form>

	<div class="rwai-card" style="padding:14px 18px;background:#fff;border:1px solid #c3c4c7;">
		<h2 style="margin-top:0;display:flex;justify-content:space-between;align-items:center;">
			<span><?php esc_html_e( 'Recent blocked visits', 'rankwriter-ai' ); ?></span>
			<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the entire block log?', 'rankwriter-ai' ) ); ?>');">
				<input type="hidden" name="rwai_action" value="clear_bot_blocker_log" />
				<?php wp_nonce_field( RankWriter_AI_Admin::BOT_BLOCKER_NONCE ); ?>
				<button type="submit" class="button button-small"><?php esc_html_e( 'Clear log', 'rankwriter-ai' ); ?></button>
			</form>
		</h2>

		<?php if ( empty( $log ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nothing blocked yet.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'IP', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Country', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Page', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'User agent', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $row ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row['blocked_at'] ) ); ?></td>
							<td><?php echo esc_html( $row['ip'] ); ?></td>
							<td><?php echo esc_html( $row['country'] ? RankWriter_AI_Bot_Blocker_DB::country_name( $row['country'] ) . ' (' . $row['country'] . ')' : '—' ); ?></td>
							<td><?php echo esc_html( 'ip' === $row['reason'] ? __( 'IP list', 'rankwriter-ai' ) : __( 'Country', 'rankwriter-ai' ) ); ?></td>
							<td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $row['request_uri'] ); ?></td>
							<td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $row['user_agent'] ); ?>"><?php echo esc_html( $row['user_agent'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
(function () {
	var search = document.getElementById( 'rwai_bb_country_search' );
	var list   = document.getElementById( 'rwai_bb_country_list' );
	if ( ! search || ! list ) { return; }
	var rows = list.querySelectorAll( '.rwai-bb-country-row' );

	search.addEventListener( 'input', function () {
		var q = search.value.trim().toLowerCase();
		rows.forEach( function ( row ) {
			var haystack = row.getAttribute( 'data-search' ) || '';
			row.style.display = ( '' === q || haystack.indexOf( q ) !== -1 ) ? '' : 'none';
		} );
	} );

	var selectAll = document.getElementById( 'rwai_bb_select_all' );
	var selectNone = document.getElementById( 'rwai_bb_select_none' );
	if ( selectAll ) {
		selectAll.addEventListener( 'click', function () {
			rows.forEach( function ( row ) {
				if ( row.style.display !== 'none' ) {
					var cb = row.querySelector( 'input[type=checkbox]' );
					if ( cb ) { cb.checked = true; }
				}
			} );
		} );
	}
	if ( selectNone ) {
		selectNone.addEventListener( 'click', function () {
			rows.forEach( function ( row ) {
				if ( row.style.display !== 'none' ) {
					var cb = row.querySelector( 'input[type=checkbox]' );
					if ( cb ) { cb.checked = false; }
				}
			} );
		} );
	}
})();
</script>
