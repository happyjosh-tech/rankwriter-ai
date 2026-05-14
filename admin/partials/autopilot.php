<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$cfg         = (array) $data['config'];
$profiles    = (array) $data['profiles'];
$queue       = (array) $data['queue'];
$log         = (array) $data['log'];
$next_run    = (string) $data['next_run'];
$diagnostics = isset( $data['diagnostics'] ) && is_array( $data['diagnostics'] ) ? $data['diagnostics'] : array();
$msg         = (string) $data['msg'];
$err         = (string) $data['err'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Autopilot — automated fresh-content engine', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Set seed keywords, choose a category profile, and Autopilot will continuously pull fresh keywords + competitor titles and have Claude write articles on a schedule. Drafts by default; flip to auto-publish when you trust the output.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'autopilot-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Autopilot settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'autopilot-refilled' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Queue refilled with fresh keywords.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'autopilot-cleared' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Queue cleared.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'autopilot-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ?: __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php elseif ( 'autopilot-run-now' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php esc_html_e( 'Autopilot dispatched to a background worker. Watch the Run log below — a new entry should appear within 1-3 minutes. Refresh the page to see updates.', 'rankwriter-ai' ); ?>
		</p></div>
	<?php endif; ?>

	<?php
	$tz_is_offset    = ! empty( $diagnostics['tz_is_offset'] );
	$disable_wp_cron = ! empty( $diagnostics['disable_wp_cron'] );
	if ( $tz_is_offset || $disable_wp_cron ) : ?>
		<div class="notice notice-warning" style="padding:14px 18px;">
			<p style="margin:0 0 6px 0;"><strong><?php esc_html_e( 'Autopilot diagnostics flagged issues', 'rankwriter-ai' ); ?></strong></p>
			<ul style="margin:6px 0 0 18px;list-style:disc;">
				<?php if ( $tz_is_offset ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: current timezone (e.g. +00:00) */
							esc_html__( 'Your WordPress site timezone is set to "%s" — a raw UTC offset, not a named timezone. The "Time of day" you type below is interpreted in this timezone. If you live in Nigeria (UTC+1) but the site is set to +00:00, a 16:14 setting will fire at 17:14 Nigeria time.', 'rankwriter-ai' ),
							esc_html( $diagnostics['tz_string'] ?: 'UTC' )
						);
						?>
						<br>
						<strong>
							<?php
							printf(
								/* translators: %s: link to WP General Settings */
								wp_kses_post( __( 'Fix: open %s and set Timezone to a named city like "Africa/Lagos" (Nigeria) so the time you type matches your local clock.', 'rankwriter-ai' ) ),
								'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings → General', 'rankwriter-ai' ) . '</a>'
							);
							?>
						</strong>
					</li>
				<?php endif; ?>
				<?php if ( $disable_wp_cron ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: cron URL */
							wp_kses_post( __( '<code>DISABLE_WP_CRON</code> is set to <code>true</code> in <code>wp-config.php</code>. WordPress scheduled events (including autopilot) will not fire on visitor traffic. Add a real system cron hitting <code>%s</code> every minute, or remove the constant.', 'rankwriter-ai' ) ),
							esc_html( $diagnostics['cron_url'] ?? '' )
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $diagnostics ) ) : ?>
		<div class="rwai-card" style="margin:12px 0 18px 0;padding:14px 18px;background:#f6f7f7;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Autopilot status', 'rankwriter-ai' ); ?></h2>
			<table class="widefat" style="background:transparent;">
				<tbody>
					<tr>
						<th style="width:240px;"><?php esc_html_e( 'Enabled?', 'rankwriter-ai' ); ?></th>
						<td>
							<?php if ( ! empty( $cfg['enabled'] ) ) : ?>
								<span style="color:#00a32a;">✔ <?php esc_html_e( 'Yes', 'rankwriter-ai' ); ?></span>
							<?php else : ?>
								<span style="color:#d63638;">✘ <?php esc_html_e( 'No — toggle "Enable autopilot" below and click Save to register the cron event.', 'rankwriter-ai' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cron event registered?', 'rankwriter-ai' ); ?></th>
						<td>
							<?php if ( ! empty( $diagnostics['next_run_ts'] ) ) : ?>
								<span style="color:#00a32a;">✔ <?php esc_html_e( 'Yes', 'rankwriter-ai' ); ?></span>
							<?php else : ?>
								<span style="color:#d63638;">✘ <?php esc_html_e( 'No event scheduled. If autopilot is enabled, click Save again to re-register.', 'rankwriter-ai' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Next run (site time)', 'rankwriter-ai' ); ?></th>
						<td><?php echo esc_html( $diagnostics['next_run_local'] ?: '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Next run (UTC)', 'rankwriter-ai' ); ?></th>
						<td><?php echo esc_html( $diagnostics['next_run_utc'] ?: '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Server now (site time)', 'rankwriter-ai' ); ?></th>
						<td><?php echo esc_html( $diagnostics['now_local'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Server now (UTC)', 'rankwriter-ai' ); ?></th>
						<td><?php echo esc_html( $diagnostics['now_utc'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WP timezone', 'rankwriter-ai' ); ?></th>
						<td>
							<code><?php echo esc_html( $diagnostics['tz_string'] ?: 'UTC' ); ?></code>
							<?php if ( $tz_is_offset ) : ?>
								<span style="color:#d63638;margin-left:8px;">⚠ <?php esc_html_e( 'Raw offset — use a named timezone instead', 'rankwriter-ai' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'DISABLE_WP_CRON', 'rankwriter-ai' ); ?></th>
						<td>
							<?php if ( $disable_wp_cron ) : ?>
								<span style="color:#d63638;">true — autopilot needs a real system cron</span>
							<?php else : ?>
								<span style="color:#00a32a;">false (WP-Cron active)</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Queue length', 'rankwriter-ai' ); ?></th>
						<td><?php echo (int) count( $queue ); ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" style="margin-top:14px;">
				<input type="hidden" name="rwai_action" value="run_autopilot_now" />
				<?php wp_nonce_field( RankWriter_AI_Admin::AUTOPILOT_NONCE ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( '▶ Run autopilot now (one article)', 'rankwriter-ai' ); ?></button>
				<span class="description" style="margin-left:8px;">
					<?php esc_html_e( 'Generates one article in the background, no need to wait for the scheduled time. Use this to confirm autopilot actually works before trusting the schedule.', 'rankwriter-ai' ); ?>
				</span>
			</form>
		</div>
	<?php endif; ?>

	<?php
	if ( isset( $_GET['rwai_recovery_published'] ) || isset( $_GET['rwai_recovery_kicked'] ) ) {
		$pubbed = isset( $_GET['rwai_recovery_published'] ) ? (int) $_GET['rwai_recovery_published'] : 0;
		$kicked = isset( $_GET['rwai_recovery_kicked'] ) ? (int) $_GET['rwai_recovery_kicked'] : 0;
		?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			printf(
				/* translators: 1: count of posts published, 2: count of cron hooks kicked */
				esc_html__( 'Recovery run: %1$d missed scheduled post(s) published, %2$d stalled cron hook(s) kicked.', 'rankwriter-ai' ),
				$pubbed,
				$kicked
			);
			?>
		</p></div>
	<?php } ?>

	<div class="notice notice-info" style="padding:12px 16px;">
		<p style="margin:0 0 8px 0;"><strong><?php esc_html_e( 'Missed schedule? Stuck post?', 'rankwriter-ai' ); ?></strong>
		<?php esc_html_e( 'WordPress relies on visitor traffic to fire scheduled posts. If a post is past its publish time but still says "Scheduled", click below to publish it now and kick any stalled Autopilot ticks.', 'rankwriter-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
			<input type="hidden" name="action" value="rwai_publish_missed_now" />
			<?php wp_nonce_field( 'rwai_publish_missed_now' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Publish missed scheduled posts now', 'rankwriter-ai' ); ?></button>
		</form>
	</div>

	<form method="post" class="rwai-form" data-rwai-ai-context="autopilot">
		<input type="hidden" name="rwai_action" value="save_autopilot" />
		<?php wp_nonce_field( RankWriter_AI_Admin::AUTOPILOT_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_ap_enabled"><?php esc_html_e( 'Enable autopilot', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_ap_enabled" name="enabled" value="1" <?php checked( $cfg['enabled'] ); ?> />
					<?php esc_html_e( 'Run on a schedule and publish automatically', 'rankwriter-ai' ); ?></label>
					<?php if ( $next_run ) : ?>
						<p class="description"><?php
							/* translators: %s: next run time */
							printf( esc_html__( 'Next scheduled run: %s', 'rankwriter-ai' ), esc_html( $next_run ) );
						?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_profile"><?php esc_html_e( 'Category profile', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_ap_profile" name="profile_id" data-rwai-ai-target="profile_id">
						<option value=""><?php esc_html_e( '— Select —', 'rankwriter-ai' ); ?></option>
						<?php foreach ( $profiles as $p ) : ?>
							<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $cfg['profile_id'], $p['id'] ); ?>><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rwai_ap_seeds"><?php esc_html_e( 'Seed keywords (one per line)', 'rankwriter-ai' ); ?></label>
					<br><button type="button" class="button button-small rwai-ai-fill" data-rwai-ai-field="seed_keywords" data-rwai-ai-needs="profile_id"><?php esc_html_e( '✨ AI fill', 'rankwriter-ai' ); ?></button>
				</th>
				<td>
					<textarea id="rwai_ap_seeds" name="seed_keywords" rows="6" class="large-text code" placeholder="agriculture grants&#10;visa sponsorship jobs&#10;remote work programs" data-rwai-ai-target="seed_keywords"><?php echo esc_textarea( $cfg['seed_keywords'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Each seed is expanded into a fresh keyword pool from Google Suggest + Trends + your competitors + (optionally) SerpAPI. Articles are written against the highest-scoring keywords first.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_freq"><?php esc_html_e( 'Run frequency', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_ap_freq" name="frequency" data-rwai-frequency>
						<?php
						$freqs = array(
							'hourly'     => 'Every hour',
							'twicedaily' => 'Twice daily',
							'daily'      => 'Daily',
							'weekly'     => 'Weekly',
						);
						foreach ( $freqs as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cfg['frequency'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr data-rwai-time-row>
				<th scope="row"><label for="rwai_ap_time"><?php esc_html_e( 'Time of day', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="time" id="rwai_ap_time" name="run_time" value="<?php echo esc_attr( $cfg['run_time'] ); ?>" />
					<?php
					$site_tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string', 'UTC' );
					?>
					<p class="description"><?php
						/* translators: %s: site timezone, e.g. America/Los_Angeles */
						printf( esc_html__( 'In your site timezone (%s). For "Twice daily" runs, this is the first fire; the second fire is 12 hours later.', 'rankwriter-ai' ), esc_html( $site_tz ?: 'UTC' ) );
					?></p>
					<p class="description"><?php esc_html_e( 'Note: WordPress cron is traffic-driven, so the actual fire time is "first page view after the scheduled time". On a busy site the gap is seconds; on a quiet site it can be longer. Hook a real system cron to wp-cron.php if you need second-precise timing.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr data-rwai-day-row>
				<th scope="row"><label for="rwai_ap_day"><?php esc_html_e( 'Day of week', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_ap_day" name="run_day_of_week">
						<?php
						$days = array(
							0 => __( 'Sunday', 'rankwriter-ai' ),
							1 => __( 'Monday', 'rankwriter-ai' ),
							2 => __( 'Tuesday', 'rankwriter-ai' ),
							3 => __( 'Wednesday', 'rankwriter-ai' ),
							4 => __( 'Thursday', 'rankwriter-ai' ),
							5 => __( 'Friday', 'rankwriter-ai' ),
							6 => __( 'Saturday', 'rankwriter-ai' ),
						);
						foreach ( $days as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( (int) $cfg['run_day_of_week'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_max"><?php esc_html_e( 'Max articles per run', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="1" max="5" id="rwai_ap_max" name="max_per_run" value="<?php echo esc_attr( $cfg['max_per_run'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_status"><?php esc_html_e( 'Post status', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_ap_status" name="post_status">
						<option value="draft" <?php selected( $cfg['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft (recommended)', 'rankwriter-ai' ); ?></option>
						<option value="pending" <?php selected( $cfg['post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending review', 'rankwriter-ai' ); ?></option>
						<option value="publish" <?php selected( $cfg['post_status'], 'publish' ); ?>><?php esc_html_e( 'Publish immediately', 'rankwriter-ai' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rwai_ap_country"><?php esc_html_e( 'Country code', 'rankwriter-ai' ); ?></label>
					<br><button type="button" class="button button-small rwai-ai-fill" data-rwai-ai-field="country" data-rwai-ai-needs="profile_id"><?php esc_html_e( '✨ AI fill', 'rankwriter-ai' ); ?></button>
				</th>
				<td><input type="text" class="small-text" maxlength="2" id="rwai_ap_country" name="country" value="<?php echo esc_attr( $cfg['country'] ); ?>" data-rwai-ai-target="country" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_words"><?php esc_html_e( 'Target word count (0 = match blog avg)', 'rankwriter-ai' ); ?></label></th>
				<td><input type="number" min="0" max="8000" step="100" id="rwai_ap_words" name="word_count" value="<?php echo esc_attr( $cfg['word_count'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_seo"><?php esc_html_e( 'Write SEO meta automatically', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_ap_seo" name="auto_seo" value="1" <?php checked( $cfg['auto_seo'] ); ?> />
					<?php esc_html_e( 'Push title, meta description, focus keyword, schema into Rank Math / Yoast / AIOSEO / SEOPress.', 'rankwriter-ai' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_maxtags"><?php esc_html_e( 'Max tags per post', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="0" max="10" step="1" id="rwai_ap_maxtags" name="max_tags" value="<?php echo esc_attr( isset( $cfg['max_tags'] ) ? (int) $cfg['max_tags'] : 2 ); ?>" />
					<p class="description"><?php esc_html_e( 'Cap how many tags each autopilot post receives. Default 2 keeps your tag cloud tidy. Set to 0 to apply every tag Claude suggests (no cap).', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_ap_wp_cat"><?php esc_html_e( 'Post to WordPress category', 'rankwriter-ai' ); ?></label></th>
				<td>
					<?php
					$picker_name          = 'wp_category_id';
					$picker_id            = 'rwai_ap_wp_cat';
					$picker_value         = isset( $cfg['wp_category_id'] ) ? (int) $cfg['wp_category_id'] : 0;
					$picker_new_value     = '';
					$picker_label         = __( 'Post to WordPress category', 'rankwriter-ai' );
					$picker_default_label = __( '— Use each profile\'s default category —', 'rankwriter-ai' );
					include RWAI_PLUGIN_DIR . 'admin/partials/_wp-category-picker.php';
					?>
					<p class="description"><?php esc_html_e( 'Force every autopilot post into one specific WordPress category, regardless of profile. Useful for keeping all auto-generated content under a single hub.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save autopilot', 'rankwriter-ai' ); ?></button>
		</p>
	</form>

	<div class="rwai-grid rwai-grid-2">
		<div class="rwai-card">
			<h2><?php esc_html_e( 'Queue', 'rankwriter-ai' ); ?></h2>
			<p><?php
				/* translators: %d: queue length */
				printf( esc_html( _n( '%d topic queued', '%d topics queued', count( $queue ), 'rankwriter-ai' ) ), count( $queue ) );
			?></p>
			<form method="post" style="display:inline-block;margin-right:6px;">
				<input type="hidden" name="rwai_action" value="refill_autopilot_queue" />
				<?php wp_nonce_field( RankWriter_AI_Admin::AUTOPILOT_NONCE ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Refill from seeds', 'rankwriter-ai' ); ?></button>
			</form>
			<form method="post" style="display:inline-block;">
				<input type="hidden" name="rwai_action" value="clear_autopilot_queue" />
				<?php wp_nonce_field( RankWriter_AI_Admin::AUTOPILOT_NONCE ); ?>
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Clear queue', 'rankwriter-ai' ); ?></button>
			</form>

			<?php if ( ! empty( $queue ) ) : ?>
				<ol class="rwai-titles-list">
					<?php foreach ( array_slice( $queue, 0, 15 ) as $q ) : ?>
						<li><?php echo esc_html( $q['topic'] ); ?> <small class="rwai-muted">(<?php echo esc_html( $q['seed'] . ', score ' . $q['score'] ); ?>)</small></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>

		<div class="rwai-card">
			<h2><?php esc_html_e( 'Run log', 'rankwriter-ai' ); ?></h2>
			<?php if ( empty( $log ) ) : ?>
				<p class="rwai-muted"><?php esc_html_e( 'No runs yet.', 'rankwriter-ai' ); ?></p>
			<?php else : ?>
				<ul class="rwai-titles-list">
					<?php foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
						<li>
							<span class="rwai-pill <?php echo 'ok' === $row['level'] ? 'rwai-pill-ok' : ( 'error' === $row['level'] ? 'rwai-pill-bad' : 'rwai-pill-warn' ); ?>"><?php echo esc_html( $row['level'] ); ?></span>
							<?php echo esc_html( $row['message'] ); ?>
							<small class="rwai-muted"><?php echo esc_html( $row['at'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
