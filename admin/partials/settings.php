<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$settings = (array) $data['settings'];
$msg      = (string) $data['msg'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'RankWriter AI — Settings', 'rankwriter-ai' ); ?></h1>

	<?php if ( 'settings-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="save_settings" />
		<?php wp_nonce_field( RankWriter_AI_Admin::SETTINGS_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_api_key"><?php esc_html_e( 'Claude API key', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="rwai_api_key" name="rwai_settings[claude_api_key]" value="<?php echo esc_attr( $settings['claude_api_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php
						printf(
							/* translators: %s: console URL */
							wp_kses_post( __( 'Create one at %s. Stored only in your WordPress database.', 'rankwriter-ai' ) ),
							'<a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>'
						);
					?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_model"><?php esc_html_e( 'Claude model', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_model" name="rwai_settings[claude_model]">
						<?php
						$models = array(
							'claude-opus-4-7'         => 'Claude Opus 4.7 (highest quality)',
							'claude-sonnet-4-6'       => 'Claude Sonnet 4.6 (balanced)',
							'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (fastest, cheapest)',
						);
						foreach ( $models as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['claude_model'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_max_tokens"><?php esc_html_e( 'Max output tokens', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="2000" max="64000" step="1000" id="rwai_max_tokens" name="rwai_settings[max_tokens]" value="<?php echo esc_attr( $settings['max_tokens'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Upper bound for a single article generation (~1 token ≈ 0.75 words). Cap is 64,000 — the maximum the Claude Opus 4.7 model itself returns in one response. This is a ceiling, not a quota; you only pay for tokens the model actually generates. Bumping it has no downside other than a higher per-article cost ceiling. If you ever see truncated / broken articles, raise this value.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_analyze_limit"><?php esc_html_e( 'Analyzer post limit', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="10" max="2000" step="10" id="rwai_analyze_limit" name="rwai_settings[analyze_post_limit]" value="<?php echo esc_attr( $settings['analyze_post_limit'] ); ?>" />
					<p class="description"><?php esc_html_e( 'How many recent posts the Blog Analyzer scans on each run.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_default_words"><?php esc_html_e( 'Default article length', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="300" max="8000" step="100" id="rwai_default_words" name="rwai_settings[default_word_count]" value="<?php echo esc_attr( $settings['default_word_count'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Used when no Blog Style Profile exists yet.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_humanize"><?php esc_html_e( 'AI Humanization pass', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_humanize" name="rwai_settings[humanize_pass]" value="1" <?php checked( ! empty( $settings['humanize_pass'] ) ); ?> />
					<?php esc_html_e( 'Run a second Claude pass on every generated article to scrub AI tells, sharpen voice, and rewrite generic openings.', 'rankwriter-ai' ); ?></label>
					<p class="description"><?php esc_html_e( 'Roughly doubles the per-article API cost but dramatically reduces the "AI-written" feel. Preserves every fact, number, HTML tag, and internal link.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<?php if ( class_exists( 'RankWriter_AI_Humanizer' ) ) :
				$strengths     = RankWriter_AI_Humanizer::strengths();
				$tones         = RankWriter_AI_Humanizer::tones();
				$personalities = RankWriter_AI_Humanizer::personalities();
				$readability   = RankWriter_AI_Humanizer::readability_modes();
			?>
			<tr>
				<th scope="row"><label for="rwai_hum_strength"><?php esc_html_e( 'Humanization strength', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_hum_strength" name="rwai_settings[humanize_strength]">
						<?php foreach ( $strengths as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['humanize_strength'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Light = surface scrub. Medium = ~50% sentences rewritten. Aggressive = full rebuild.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_hum_tone"><?php esc_html_e( 'Tone', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_hum_tone" name="rwai_settings[humanize_tone]">
						<?php foreach ( $tones as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['humanize_tone'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_hum_personality"><?php esc_html_e( 'Writer persona', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_hum_personality" name="rwai_settings[humanize_personality]">
						<?php foreach ( $personalities as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['humanize_personality'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_hum_readability"><?php esc_html_e( 'Readability mode', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_hum_readability" name="rwai_settings[humanize_readability]">
						<?php foreach ( $readability as $k => $cfg ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['humanize_readability'], $k ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Optional: force shorter sentences and simpler vocabulary. Off keeps the persona\'s natural cadence.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="rwai_default_image_style"><?php esc_html_e( 'Default image style', 'rankwriter-ai' ); ?></label></th>
				<td>
					<select id="rwai_default_image_style" name="rwai_settings[default_image_style]">
						<?php
						$styles = array(
							'realistic'   => 'Realistic Photography',
							'illustration'=> 'Illustration / Vector',
							'infographic' => 'Infographic',
							'screenshot'  => 'Screenshot / Product Shot',
							'cinematic'   => 'Cinematic',
							'minimalist'  => 'Minimalist / Flat',
						);
						foreach ( $styles as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['default_image_style'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_default_country"><?php esc_html_e( 'Default country (ISO 2-letter)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="small-text" maxlength="2" id="rwai_default_country" name="rwai_settings[default_country]" value="<?php echo esc_attr( strtoupper( $settings['default_country'] ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Used by Keyword Research + Autopilot when no per-profile country is set. e.g. US, GB, NG, IN.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Live keyword discovery', 'rankwriter-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_competitors"><?php esc_html_e( 'Competitor domains', 'rankwriter-ai' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="4" id="rwai_competitors" name="rwai_settings[competitor_domains]" placeholder="example.com&#10;competitor.blog&#10;industry-site.org"><?php echo esc_textarea( $settings['competitor_domains'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One domain per line (or comma-separated). The plugin reads each site\'s RSS/Atom feed to harvest recent post titles for inspiration and ranking inputs. Free, no API key needed.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_serpapi"><?php esc_html_e( 'SerpAPI key (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="rwai_serpapi" name="rwai_settings[serpapi_key]" value="<?php echo esc_attr( $settings['serpapi_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php
						printf(
							/* translators: %s: SerpAPI URL */
							wp_kses_post( __( 'Adds "People Also Ask", related searches, and live SERP organic titles to keyword research. Get a key at %s.', 'rankwriter-ai' ) ),
							'<a href="https://serpapi.com/" target="_blank" rel="noopener">serpapi.com</a>'
						);
					?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_dfs_login"><?php esc_html_e( 'DataForSEO login (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="rwai_dfs_login" name="rwai_settings[dataforseo_login]" value="<?php echo esc_attr( $settings['dataforseo_login'] ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_dfs_pass"><?php esc_html_e( 'DataForSEO password (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="rwai_dfs_pass" name="rwai_settings[dataforseo_password]" value="<?php echo esc_attr( $settings['dataforseo_password'] ); ?>" autocomplete="off" />
					<p class="description"><?php
						printf(
							/* translators: %s: DataForSEO URL */
							wp_kses_post( __( 'Adds search-volume + competition data to keyword research. Sign up at %s.', 'rankwriter-ai' ) ),
							'<a href="https://dataforseo.com/" target="_blank" rel="noopener">dataforseo.com</a>'
						);
					?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Featured image sourcing', 'rankwriter-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_pexels"><?php esc_html_e( 'Pexels API key (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="rwai_pexels" name="rwai_settings[pexels_api_key]" value="<?php echo esc_attr( $settings['pexels_api_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php
						printf(
							/* translators: %s: Pexels URL */
							wp_kses_post( __( 'Free key at %s. If unset the plugin falls back to Openverse (free, no key needed).', 'rankwriter-ai' ) ),
							'<a href="https://www.pexels.com/api/" target="_blank" rel="noopener">pexels.com/api</a>'
						);
					?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_unsplash"><?php esc_html_e( 'Unsplash access key (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="rwai_unsplash" name="rwai_settings[unsplash_access_key]" value="<?php echo esc_attr( $settings['unsplash_access_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php
						printf(
							/* translators: %s: Unsplash URL */
							wp_kses_post( __( 'Free key at %s. Used after Pexels if available.', 'rankwriter-ai' ) ),
							'<a href="https://unsplash.com/developers" target="_blank" rel="noopener">unsplash.com/developers</a>'
						);
					?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Multi-Language Mode', 'rankwriter-ai' ); ?></h2>
		<?php
		$enabled_codes = class_exists( 'RankWriter_AI_Language' ) ? RankWriter_AI_Language::enabled_codes() : array( 'en' );
		$all_langs     = class_exists( 'RankWriter_AI_Language' ) ? RankWriter_AI_Language::languages() : array();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled languages', 'rankwriter-ai' ); ?></th>
				<td>
					<?php foreach ( $all_langs as $code => $cfg ) :
						$is_en = 'en' === $code;
						?>
						<label style="display:inline-block;margin:3px 14px 3px 0;">
							<input type="checkbox" name="rwai_settings[enabled_languages][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $enabled_codes, true ) ); ?> <?php disabled( $is_en ); ?> />
							<?php echo esc_html( $cfg['name'] . ' (' . $cfg['native'] . ')' ); ?>
							<?php if ( ! empty( $cfg['rtl'] ) ) : ?> <small class="rwai-muted">RTL</small><?php endif; ?>
							<?php if ( $is_en ) : ?> <small class="rwai-muted"><?php esc_html_e( '— always enabled', 'rankwriter-ai' ); ?></small><?php endif; ?>
						</label>
						<?php if ( $is_en ) : ?>
							<input type="hidden" name="rwai_settings[enabled_languages][]" value="en" />
						<?php endif; ?>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Languages users can translate posts into. English is always on. More languages can be added programmatically via the rwai_languages filter.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_auto_translate"><?php esc_html_e( 'Auto-translate on publish', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_auto_translate" name="rwai_settings[auto_translate_on_publish]" value="1" <?php checked( ! empty( $settings['auto_translate_on_publish'] ) ); ?> />
					<?php esc_html_e( 'When an English post is published, automatically queue translations for every other enabled language.', 'rankwriter-ai' ); ?></label>
					<p class="description"><?php esc_html_e( 'Translations are queued as a deferred cron event (5 min after publish) and saved as drafts in each language. Cost scales linearly with the number of enabled languages, so test on one or two before enabling all of them.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Pinterest Auto Content Mode', 'rankwriter-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_pin_autogen"><?php esc_html_e( 'Auto-generate pins on publish', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_pin_autogen" name="rwai_settings[pinterest_auto_generate]" value="1" <?php checked( ! empty( $settings['pinterest_auto_generate'] ) ); ?> />
					<?php esc_html_e( 'When a post is published, schedule a deferred Pinterest pin generation 3 minutes later (keeps the publish click instant).', 'rankwriter-ai' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pin_count"><?php esc_html_e( 'Pins per post', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="number" min="1" max="5" id="rwai_pin_count" name="rwai_settings[pinterest_pins_per_post]" value="<?php echo esc_attr( $settings['pinterest_pins_per_post'] ); ?>" />
					<p class="description"><?php esc_html_e( 'How many pin variations to generate per article (1-5). Each variation tests a different overlay angle.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pin_render"><?php esc_html_e( 'Auto-render pin images', 'rankwriter-ai' ); ?></label></th>
				<td>
					<label><input type="checkbox" id="rwai_pin_render" name="rwai_settings[pinterest_auto_render_images]" value="1" <?php checked( ! empty( $settings['pinterest_auto_render_images'] ) ); ?> />
					<?php esc_html_e( 'Automatically render a 1000×1500 overlay image for every generated pin. Requires PHP GD.', 'rankwriter-ai' ); ?></label>
					<?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'PHP GD is NOT available on this server. Image rendering will be skipped.', 'rankwriter-ai' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_pin_font"><?php esc_html_e( 'Custom font path (optional)', 'rankwriter-ai' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="rwai_pin_font" name="rwai_settings[pinterest_font_path]" value="<?php echo esc_attr( $settings['pinterest_font_path'] ); ?>" placeholder="/full/path/to/font.ttf" />
					<p class="description"><?php esc_html_e( 'Absolute path to a .ttf file used for pin overlays. Leave empty to auto-detect a system font (DejaVu Sans, Liberation Sans, Helvetica). Falls back to bitmap text if nothing is found.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'SEO plugin integration', 'rankwriter-ai' ); ?></h2>
		<?php $seo = new RankWriter_AI_SEO_Integration(); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Detected SEO plugin', 'rankwriter-ai' ); ?></th>
				<td>
					<strong><?php echo esc_html( $seo->detected_label() ); ?></strong>
					<p class="description"><?php esc_html_e( 'RankWriter AI will automatically write meta title, description, focus keyword, OG fields, and schema type into whichever SEO plugin you have active. No extra config needed.', 'rankwriter-ai' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'rankwriter-ai' ); ?></button>
		</p>
	</form>
</div>
