<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$profiles    = (array) $data['profiles'];
$style       = (array) $data['style'];
$api_ready   = (bool) $data['api_ready'];
$msg         = (string) $data['msg'];
$err         = (string) $data['err'];
$current_job = isset( $data['current_job'] ) ? $data['current_job'] : null;
$recent_jobs = isset( $data['recent_jobs'] ) && is_array( $data['recent_jobs'] ) ? $data['recent_jobs'] : array();

$selected_profile = isset( $_GET['profile_id'] ) ? absint( $_GET['profile_id'] ) : 0;
$default_words    = ! empty( $style['average_word_count'] ) ? (int) $style['average_word_count'] : (int) RankWriter_AI_Helpers::get_setting( 'default_word_count', 1500 );

// If the queued job just finished, send the user straight to the post
// editor — this mirrors the old synchronous redirect behaviour.
if ( is_array( $current_job ) && 'done' === ( $current_job['status'] ?? '' ) && ! empty( $current_job['post_id'] ) ) {
	$edit_url = add_query_arg( array( 'post' => (int) $current_job['post_id'], 'action' => 'edit' ), admin_url( 'post.php' ) );
	?>
	<script>window.location.replace(<?php echo wp_json_encode( $edit_url ); ?>);</script>
	<noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $edit_url ); ?>"></noscript>
	<?php
}

// While the job is in-flight (queued or running), auto-refresh every
// 5 seconds so the user gets the result without having to click reload.
$is_pending = is_array( $current_job ) && in_array( (string) ( $current_job['status'] ?? '' ), array( 'queued', 'running' ), true );
if ( $is_pending ) {
	?>
	<meta http-equiv="refresh" content="5">
	<?php
}
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
	<?php elseif ( 'generate-reset' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Job reset and re-queued. It will run again on the next background worker tick.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<?php if ( is_array( $current_job ) ) :
		$job_status = (string) ( $current_job['status'] ?? '' );
		$job_topic  = (string) ( $current_job['topic'] ?? '' );
		?>
		<?php if ( 'queued' === $job_status ) : ?>
			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Article queued for background generation.', 'rankwriter-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %s: article topic */
					esc_html__( 'Topic: %s. This page will refresh every 5 seconds and redirect you to the editor as soon as it\'s ready (typically 1-3 minutes).', 'rankwriter-ai' ),
					'<em>' . esc_html( $job_topic ) . '</em>' // phpcs:ignore
				);
				?></p>
				<p><?php esc_html_e( 'Why background? Article generation can take 90-180 seconds; running it inline causes nginx 504 Gateway Time-out on most hosts.', 'rankwriter-ai' ); ?></p>
			</div>
		<?php elseif ( 'running' === $job_status ) : ?>
			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Generating now…', 'rankwriter-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %s: article topic */
					esc_html__( 'Claude is writing your article on "%s". This page will redirect to the editor when it\'s done.', 'rankwriter-ai' ),
					esc_html( $job_topic )
				);
				?></p>
			</div>
		<?php elseif ( 'failed' === $job_status ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Generation failed.', 'rankwriter-ai' ); ?></strong>
				<?php echo esc_html( (string) ( $current_job['error'] ?? __( 'Unknown error.', 'rankwriter-ai' ) ) ); ?></p>
			</div>
		<?php endif; ?>
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

	<?php if ( ! empty( $recent_jobs ) ) : ?>
		<hr style="margin:32px 0 16px 0;">
		<h2><?php esc_html_e( 'Recent generations', 'rankwriter-ai' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Topic', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Status', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Last step', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Queued', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Finished', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Action / Result', 'rankwriter-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_jobs as $job ) :
					$status     = (string) ( $job['status']      ?? '' );
					$topic      = (string) ( $job['topic']       ?? '' );
					$queued     = (string) ( $job['queued_at']   ?? '' );
					$ended      = (string) ( $job['ended_at']    ?? '' );
					$post_id    = (int)    ( $job['post_id']     ?? 0 );
					$error      = (string) ( $job['error']       ?? '' );
					$progress   = (string) ( $job['progress']    ?? '' );
					$attempts   = (int)    ( $job['attempts']    ?? 0 );
					$started_ts = (int)    ( $job['started_at_ts'] ?? 0 );
					$job_id     = (string) ( $job['id']          ?? '' );

					$is_stale_running = ( 'running' === $status && $started_ts > 0 && ( time() - $started_ts ) > 600 );

					$badge_colors = array(
						'queued'  => '#dba617',
						'running' => '#2271b1',
						'done'    => '#00a32a',
						'failed'  => '#d63638',
					);
					$badge_color = $badge_colors[ $status ] ?? '#646970';
					?>
					<tr>
						<td><?php echo esc_html( $topic ); ?>
							<?php if ( $attempts > 1 ) : ?>
								<br><small class="rwai-muted"><?php
								/* translators: %d: attempt count */
								printf( esc_html__( 'attempt %d', 'rankwriter-ai' ), (int) $attempts );
								?></small>
							<?php endif; ?>
						</td>
						<td>
							<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:<?php echo esc_attr( $badge_color ); ?>;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">
								<?php echo esc_html( $status ); ?>
							</span>
							<?php if ( $is_stale_running ) : ?>
								<br><small style="color:#d63638;">⚠ <?php esc_html_e( 'worker likely died', 'rankwriter-ai' ); ?></small>
							<?php endif; ?>
						</td>
						<td><small><?php echo esc_html( $progress ?: '—' ); ?></small></td>
						<td><?php echo esc_html( $queued ); ?></td>
						<td><?php echo esc_html( $ended ); ?></td>
						<td>
							<?php if ( 'done' === $status && $post_id ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) ); ?>"><?php esc_html_e( 'Open post', 'rankwriter-ai' ); ?></a>
							<?php elseif ( 'failed' === $status || $is_stale_running ) : ?>
								<?php if ( $error ) : ?>
									<div style="color:#d63638;margin-bottom:6px;"><small><?php echo esc_html( $error ); ?></small></div>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
									<input type="hidden" name="action" value="rwai_reset_generation_job" />
									<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
									<?php wp_nonce_field( 'rwai_reset_generation_job' ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Reset & retry', 'rankwriter-ai' ); ?></button>
								</form>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
