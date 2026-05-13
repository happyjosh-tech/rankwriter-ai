<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$profiles   = (array) $data['profiles'];
$style      = (array) $data['style'];
$api_ready  = (bool) $data['api_ready'];
$msg        = (string) $data['msg'];
$err        = (string) $data['err'];

$selected_profile = isset( $_GET['profile_id'] ) ? absint( $_GET['profile_id'] ) : 0;
$default_words    = ! empty( $style['average_word_count'] ) ? (int) $style['average_word_count'] : (int) RankWriter_AI_Helpers::get_setting( 'default_word_count', 1500 );
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Generate Article', 'rankwriter-ai' ); ?></h1>

	<?php if ( ! $api_ready ) : ?>
		<div class="notice notice-error"><p>
			<?php
			printf(
				/* translators: %s: link to settings */
				wp_kses_post( __( 'Add your Claude API key in %s before generating articles.', 'rankwriter-ai' ) ),
				'<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'rankwriter-ai' ) . '</a>'
			);
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( empty( $style ) ) : ?>
		<div class="notice notice-warning"><p>
			<?php
			printf(
				/* translators: %s: link to analyzer */
				wp_kses_post( __( 'No Blog Style Profile yet. %s so generated articles match your existing site.', 'rankwriter-ai' ) ),
				'<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::ANALYZER_SLUG ) ) . '">' . esc_html__( 'Run the Blog Analyzer first', 'rankwriter-ai' ) . '</a>'
			);
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( 'generate-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Generation failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $profiles ) ) : ?>
		<div class="notice notice-info"><p>
			<?php
			printf(
				/* translators: %s: link to profiles */
				wp_kses_post( __( 'You need at least one category profile. %s', 'rankwriter-ai' ) ),
				'<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG, array( 'new' => 1 ) ) ) . '">' . esc_html__( 'Create one now', 'rankwriter-ai' ) . '</a>'
			);
			?>
		</p></div>
	<?php else : ?>
		<form method="post" class="rwai-form" data-rwai-ai-context="generate_article">
			<input type="hidden" name="rwai_action" value="generate_article" />
			<?php wp_nonce_field( RankWriter_AI_Admin::GENERATE_NONCE ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwai_profile_id"><?php esc_html_e( 'Category profile', 'rankwriter-ai' ); ?></label></th>
					<td>
						<select id="rwai_profile_id" name="profile_id" required data-rwai-ai-target="profile_id">
							<option value=""><?php esc_html_e( '— Select a category —', 'rankwriter-ai' ); ?></option>
							<?php foreach ( $profiles as $p ) : ?>
								<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $selected_profile, $p['id'] ); ?>><?php echo esc_html( $p['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rwai_topic"><?php esc_html_e( 'Topic / working title', 'rankwriter-ai' ); ?></label>
						<br><button type="button" class="button button-small rwai-ai-fill" data-rwai-ai-field="topic" data-rwai-ai-needs="profile_id"><?php esc_html_e( '✨ AI fill', 'rankwriter-ai' ); ?></button>
					</th>
					<td>
						<?php $rwai_prefill = isset( $_GET['prefill_topic'] ) ? sanitize_text_field( wp_unslash( $_GET['prefill_topic'] ) ) : ''; ?>
						<input type="text" class="regular-text" id="rwai_topic" name="topic" required placeholder="<?php esc_attr_e( 'e.g. Agriculture grants for first-time farmers in the US', 'rankwriter-ai' ); ?>" data-rwai-ai-target="topic" value="<?php echo esc_attr( $rwai_prefill ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_word_count"><?php esc_html_e( 'Target word count', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="number" min="300" max="8000" step="100" id="rwai_word_count" name="word_count" value="<?php echo esc_attr( $default_words ); ?>" />
						<p class="description"><?php esc_html_e( 'Defaults to your blog\'s average. Leave as-is to match existing posts.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_wp_cat_gen"><?php esc_html_e( 'Post to WordPress category', 'rankwriter-ai' ); ?></label></th>
					<td>
						<?php
						$picker_name          = 'wp_category_id';
						$picker_id            = 'rwai_wp_cat_gen';
						$picker_value         = 0;
						$picker_new_value     = '';
						$picker_label         = __( 'Post to WordPress category', 'rankwriter-ai' );
						$picker_default_label = __( '— Use the profile\'s default category —', 'rankwriter-ai' );
						include RWAI_PLUGIN_DIR . 'admin/partials/_wp-category-picker.php';
						?>
						<p class="description"><?php esc_html_e( 'Override the profile\'s default category for this single article.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rwai_extra"><?php esc_html_e( 'Extra brief (optional)', 'rankwriter-ai' ); ?></label>
						<br><button type="button" class="button button-small rwai-ai-fill" data-rwai-ai-field="extra_context" data-rwai-ai-needs="topic"><?php esc_html_e( '✨ AI fill', 'rankwriter-ai' ); ?></button>
					</th>
					<td>
						<textarea class="large-text code" rows="4" id="rwai_extra" name="extra_context" placeholder="<?php esc_attr_e( 'Audience angle, must-include points, specific keywords, sources to cite, etc.', 'rankwriter-ai' ); ?>" data-rwai-ai-target="extra_context"></textarea>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" <?php disabled( ! $api_ready ); ?>><?php esc_html_e( 'Generate draft', 'rankwriter-ai' ); ?></button>
			</p>
		</form>
		<p class="description"><?php esc_html_e( 'The article is saved as a draft post you can review and publish from the Posts screen.', 'rankwriter-ai' ); ?></p>
	<?php endif; ?>
</div>
