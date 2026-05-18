<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$blocks   = (array) $data['blocks'];
$settings = (array) $data['settings'];
$msg      = (string) $data['msg'];
$active   = (int) $data['active'];

$num_blocks = RankWriter_AI_Ads_DB::NUM_BLOCKS;
?>
<div class="wrap rwai-wrap rwai-ads-wrap">
	<h1><?php esc_html_e( 'Ads — ad insertion + AdSense management', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Paste your ad code (AdSense, Ezoic, Mediavine, custom HTML/JS) into one of the 16 blocks below. Each block has its own insertion rule, display conditions, device targeting, and schedule. No external plugin needed.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'ads-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ad settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="save_ads" />
		<?php wp_nonce_field( RankWriter_AI_Admin::ADS_NONCE ); ?>

		<!-- Global settings card -->
		<div class="rwai-card" style="padding:14px 18px;margin-bottom:18px;background:#f6f7f7;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Global ad settings', 'rankwriter-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rwai_ads_master"><?php esc_html_e( 'Master enable', 'rankwriter-ai' ); ?></label></th>
					<td><label><input type="checkbox" id="rwai_ads_master" name="master_enabled" value="1" <?php checked( ! empty( $settings['master_enabled'] ) ); ?> /> <?php esc_html_e( 'Turn ads on across the site (kill switch).', 'rankwriter-ai' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="rwai_auto_ads"><?php esc_html_e( 'AdSense Auto Ads', 'rankwriter-ai' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="rwai_auto_ads" name="auto_ads_enabled" value="1" <?php checked( ! empty( $settings['auto_ads_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable Google Auto Ads in <head>.', 'rankwriter-ai' ); ?></label>
						<br>
						<input type="text" name="auto_ads_pub_id" value="<?php echo esc_attr( $settings['auto_ads_pub_id'] ); ?>" placeholder="ca-pub-1234567890123456" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Your AdSense publisher ID. Starts with ca-pub-.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_ads_txt"><?php esc_html_e( 'ads.txt content', 'rankwriter-ai' ); ?></label></th>
					<td>
						<textarea id="rwai_ads_txt" name="ads_txt_content" rows="4" class="large-text code" placeholder="google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0"><?php echo esc_textarea( $settings['ads_txt_content'] ); ?></textarea>
						<p class="description"><?php
						printf(
							/* translators: %s: ads.txt URL */
							wp_kses_post( __( 'Served at %s — required for AdSense / programmatic verification. Leave blank to let WordPress 404 normally.', 'rankwriter-ai' ) ),
							'<a href="' . esc_url( home_url( '/ads.txt' ) ) . '" target="_blank">' . esc_html( home_url( '/ads.txt' ) ) . '</a>'
						);
						?></p>
					</td>
				</tr>
				<tr>
					<th><label for="rwai_inject_head"><?php esc_html_e( 'Extra HTML in &lt;head&gt;', 'rankwriter-ai' ); ?></label></th>
					<td>
						<textarea id="rwai_inject_head" name="inject_in_head" rows="3" class="large-text code" placeholder="<!-- additional verification meta / pixels -->"><?php echo esc_textarea( $settings['inject_in_head'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Anything you paste here is emitted verbatim in the site\'s <head>. Useful for Google Search Console / Bing Webmaster / AdSense site verification meta tags.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Block tabs -->
		<div class="rwai-ads-tabs" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:0;border-bottom:1px solid #c3c4c7;">
			<?php for ( $i = 1; $i <= $num_blocks; $i++ ) :
				$is_active   = ( $i === $active );
				$is_enabled  = ! empty( $blocks[ $i ]['enabled'] );
				$tab_color   = $is_active ? '#fff' : '#f0f0f1';
				$num_color   = $is_enabled ? '#00a32a' : '#646970';
				?>
				<a href="<?php echo esc_url( add_query_arg( 'block', $i ) ); ?>" class="rwai-ads-tab" style="padding:8px 14px;border:1px solid #c3c4c7;border-bottom:none;background:<?php echo esc_attr( $tab_color ); ?>;text-decoration:none;color:<?php echo esc_attr( $num_color ); ?>;font-weight:<?php echo $is_active ? 'bold' : 'normal'; ?>;">
					<?php echo (int) $i; ?>
					<?php if ( $is_enabled ) : ?><span style="margin-left:4px;">●</span><?php endif; ?>
				</a>
			<?php endfor; ?>
		</div>

		<?php
		// Render all 16 block panels — only the active one is visible.
		// We submit all 16 in one POST so the user doesn't lose changes
		// when clicking between tabs.
		for ( $i = 1; $i <= $num_blocks; $i++ ) :
			$b           = $blocks[ $i ];
			$show        = ( $i === $active ) ? '' : 'display:none;';
			$days_arr    = array_filter( array_map( 'intval', explode( ',', (string) $b['schedule_days'] ) ), function ( $d ) { return $d >= 0 && $d <= 6; } );
			$day_labels  = array( 0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat' );
			?>
			<div class="rwai-ads-panel" data-block="<?php echo (int) $i; ?>" style="<?php echo esc_attr( $show ); ?>border:1px solid #c3c4c7;padding:18px;background:#fff;">

				<h2 style="margin-top:0;">
					<?php
					/* translators: %d: block number */
					printf( esc_html__( 'Block %d', 'rankwriter-ai' ), $i );
					?>
				</h2>

				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Enabled', 'rankwriter-ai' ); ?></th>
						<td><label><input type="checkbox" name="blocks[<?php echo $i; ?>][enabled]" value="1" <?php checked( ! empty( $b['enabled'] ) ); ?> /> <?php esc_html_e( 'Render this ad on the frontend', 'rankwriter-ai' ); ?></label></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Block name', 'rankwriter-ai' ); ?></label></th>
						<td><input type="text" name="blocks[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $b['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Top of post — leaderboard 728x90', 'rankwriter-ai' ); ?>" /></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Ad code', 'rankwriter-ai' ); ?></label></th>
						<td>
							<textarea name="blocks[<?php echo $i; ?>][code]" rows="10" class="large-text code" placeholder='<?php esc_attr_e( 'Paste your AdSense / Ezoic / Mediavine / custom HTML+JS snippet here.', 'rankwriter-ai' ); ?>'><?php echo esc_textarea( $b['code'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'HTML + JavaScript + PHP-free. Paste the AdSense ins-tag with its <script> push, or any custom ad-network snippet. Rendered verbatim — admin-only field, no escaping applied.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Insertion', 'rankwriter-ai' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Where to insert', 'rankwriter-ai' ); ?></th>
						<td>
							<select name="blocks[<?php echo $i; ?>][insertion]">
								<option value="after_paragraph"  <?php selected( $b['insertion'], 'after_paragraph' ); ?>><?php esc_html_e( 'After paragraph N', 'rankwriter-ai' ); ?></option>
								<option value="before_content"   <?php selected( $b['insertion'], 'before_content' ); ?>><?php esc_html_e( 'Before content (top of post)', 'rankwriter-ai' ); ?></option>
								<option value="after_content"    <?php selected( $b['insertion'], 'after_content' ); ?>><?php esc_html_e( 'After content (bottom of post)', 'rankwriter-ai' ); ?></option>
								<option value="before_excerpt"   <?php selected( $b['insertion'], 'before_excerpt' ); ?>><?php esc_html_e( 'Before excerpt', 'rankwriter-ai' ); ?></option>
								<option value="after_excerpt"    <?php selected( $b['insertion'], 'after_excerpt' ); ?>><?php esc_html_e( 'After excerpt', 'rankwriter-ai' ); ?></option>
								<option value="between_posts"    <?php selected( $b['insertion'], 'between_posts' ); ?>><?php esc_html_e( 'Between posts on archive pages', 'rankwriter-ai' ); ?></option>
								<option value="none"             <?php selected( $b['insertion'], 'none' ); ?>><?php esc_html_e( 'Manual only (use shortcode)', 'rankwriter-ai' ); ?></option>
							</select>
							<p class="description">
								<?php
								printf(
									/* translators: %s: shortcode example */
									esc_html__( 'Manual placement via shortcode: %s', 'rankwriter-ai' ),
									'<code>[rwai_ad block=' . (int) $i . ']</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Paragraph numbers', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="text" name="blocks[<?php echo $i; ?>][insertion_paragraphs]" value="<?php echo esc_attr( $b['insertion_paragraphs'] ); ?>" class="regular-text" placeholder="3,6,9,12" />
							<p class="description"><?php esc_html_e( 'Comma-separated paragraph numbers, used only with "After paragraph N". Example: 3,6,9,12 puts an ad after the 3rd, 6th, 9th, and 12th paragraphs.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Between every', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="number" min="1" max="50" name="blocks[<?php echo $i; ?>][between_posts_every]" value="<?php echo esc_attr( (int) $b['between_posts_every'] ); ?>" class="small-text" /> <?php esc_html_e( 'posts (only with "Between posts on archives")', 'rankwriter-ai' ); ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Alignment', 'rankwriter-ai' ); ?></th>
						<td>
							<select name="blocks[<?php echo $i; ?>][alignment]">
								<option value="default" <?php selected( $b['alignment'], 'default' ); ?>><?php esc_html_e( 'Default', 'rankwriter-ai' ); ?></option>
								<option value="left"    <?php selected( $b['alignment'], 'left' ); ?>><?php esc_html_e( 'Left (float)', 'rankwriter-ai' ); ?></option>
								<option value="right"   <?php selected( $b['alignment'], 'right' ); ?>><?php esc_html_e( 'Right (float)', 'rankwriter-ai' ); ?></option>
								<option value="center"  <?php selected( $b['alignment'], 'center' ); ?>><?php esc_html_e( 'Center', 'rankwriter-ai' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Display conditions', 'rankwriter-ai' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Show on', 'rankwriter-ai' ); ?></th>
						<td>
							<?php foreach ( array(
								'show_on_posts'    => __( 'Posts',          'rankwriter-ai' ),
								'show_on_pages'    => __( 'Pages',          'rankwriter-ai' ),
								'show_on_homepage' => __( 'Homepage',       'rankwriter-ai' ),
								'show_on_category' => __( 'Category pages', 'rankwriter-ai' ),
								'show_on_tag'      => __( 'Tag pages',      'rankwriter-ai' ),
								'show_on_search'   => __( 'Search results', 'rankwriter-ai' ),
								'show_on_archive'  => __( 'Archive pages',  'rankwriter-ai' ),
							) as $field => $label ) : ?>
								<label style="margin-right:14px;display:inline-block;">
									<input type="checkbox" name="blocks[<?php echo $i; ?>][<?php echo esc_attr( $field ); ?>]" value="1" <?php checked( ! empty( $b[ $field ] ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Categories', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="text" name="blocks[<?php echo $i; ?>][include_categories]" value="<?php echo esc_attr( $b['include_categories'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Include category IDs (CSV) — empty = all', 'rankwriter-ai' ); ?>" />
							<br><br>
							<input type="text" name="blocks[<?php echo $i; ?>][exclude_categories]" value="<?php echo esc_attr( $b['exclude_categories'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Exclude category IDs (CSV)', 'rankwriter-ai' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Tags', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="text" name="blocks[<?php echo $i; ?>][include_tags]" value="<?php echo esc_attr( $b['include_tags'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Include tag IDs (CSV) — empty = all', 'rankwriter-ai' ); ?>" />
							<br><br>
							<input type="text" name="blocks[<?php echo $i; ?>][exclude_tags]" value="<?php echo esc_attr( $b['exclude_tags'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Exclude tag IDs (CSV)', 'rankwriter-ai' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Exclude posts', 'rankwriter-ai' ); ?></label></th>
						<td><input type="text" name="blocks[<?php echo $i; ?>][exclude_post_ids]" value="<?php echo esc_attr( $b['exclude_post_ids'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Post IDs to hide this ad on (CSV)', 'rankwriter-ai' ); ?>" /></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Device targeting', 'rankwriter-ai' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Show on devices', 'rankwriter-ai' ); ?></th>
						<td>
							<label style="margin-right:14px;"><input type="checkbox" name="blocks[<?php echo $i; ?>][show_desktop]" value="1" <?php checked( ! empty( $b['show_desktop'] ) ); ?> /> <?php esc_html_e( 'Desktop', 'rankwriter-ai' ); ?></label>
							<label style="margin-right:14px;"><input type="checkbox" name="blocks[<?php echo $i; ?>][show_tablet]"  value="1" <?php checked( ! empty( $b['show_tablet'] ) ); ?> /> <?php esc_html_e( 'Tablet', 'rankwriter-ai' ); ?></label>
							<label style="margin-right:14px;"><input type="checkbox" name="blocks[<?php echo $i; ?>][show_mobile]"  value="1" <?php checked( ! empty( $b['show_mobile'] ) ); ?> /> <?php esc_html_e( 'Mobile', 'rankwriter-ai' ); ?></label>
							<p class="description"><?php esc_html_e( 'Detection is user-agent based: iPad / Android tablets → tablet; iPhone / Android phones → mobile; everything else → desktop.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Schedule', 'rankwriter-ai' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><label><?php esc_html_e( 'Date range', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="datetime-local" name="blocks[<?php echo $i; ?>][schedule_start]" value="<?php echo esc_attr( $b['schedule_start'] ? str_replace( ' ', 'T', $b['schedule_start'] ) : '' ); ?>" />
							<?php esc_html_e( 'to', 'rankwriter-ai' ); ?>
							<input type="datetime-local" name="blocks[<?php echo $i; ?>][schedule_end]" value="<?php echo esc_attr( $b['schedule_end'] ? str_replace( ' ', 'T', $b['schedule_end'] ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Empty = no boundary. Site timezone applies.', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Time-of-day window', 'rankwriter-ai' ); ?></label></th>
						<td>
							<input type="time" name="blocks[<?php echo $i; ?>][schedule_hour_from]" value="<?php echo esc_attr( $b['schedule_hour_from'] ); ?>" />
							<?php esc_html_e( 'to', 'rankwriter-ai' ); ?>
							<input type="time" name="blocks[<?php echo $i; ?>][schedule_hour_to]" value="<?php echo esc_attr( $b['schedule_hour_to'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Empty = all day. Wraps midnight if "to" is earlier than "from" (e.g. 22:00 → 06:00 for overnight ads).', 'rankwriter-ai' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Day of week', 'rankwriter-ai' ); ?></th>
						<td>
							<?php foreach ( $day_labels as $idx => $lbl ) : ?>
								<label style="margin-right:10px;">
									<input type="checkbox" name="blocks[<?php echo $i; ?>][schedule_days][]" value="<?php echo (int) $idx; ?>" <?php checked( in_array( (int) $idx, $days_arr, true ) ); ?> />
									<?php echo esc_html( $lbl ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<?php
				// schedule_days arrives as array; we need a hidden CSV
				// so the DB layer's sanitize_csv_ints() can consume it.
				// JS in the form coerces this on submit; for no-JS users
				// we add an empty hidden field as a fallback (server-side
				// detection of [] checkboxes works either way thanks to
				// PHP's $_POST array handling).
				?>
			</div>
		<?php endfor; ?>

		<p class="submit" style="margin-top:18px;">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save all blocks', 'rankwriter-ai' ); ?></button>
			<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Saves the global settings + all 16 blocks in one click.', 'rankwriter-ai' ); ?></span>
		</p>
	</form>

	<script>
	// Coerce schedule_days[] checkbox arrays into a single CSV field
	// that the server-side sanitize_csv_ints() expects.
	(function () {
		var form = document.querySelector('.rwai-ads-wrap form.rwai-form');
		if (!form) return;
		form.addEventListener('submit', function () {
			for (var b = 1; b <= <?php echo (int) $num_blocks; ?>; b++) {
				var boxes = form.querySelectorAll('input[name="blocks[' + b + '][schedule_days][]"]:checked');
				var vals = [];
				boxes.forEach(function (cb) { vals.push(cb.value); });
				var hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'blocks[' + b + '][schedule_days]';
				hidden.value = vals.join(',');
				form.appendChild(hidden);
			}
		});
	})();
	</script>
</div>
