<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$msg = (string) $data['msg'];
$err = (string) $data['err'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Google Discover Optimization Engine', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Score any saved post — or any draft you paste in — for the four levers that matter on Google Discover: mobile engagement, freshness, emotional pull, and image readiness. Generate scroll-stopping intro hooks with Claude when you need a stronger opener.', 'rankwriter-ai' ); ?></p>

	<?php if ( '' !== $err ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
	<?php endif; ?>

	<!-- ============== Score a saved post ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '📊 Score a saved post', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Pick any published or draft post. Instant heuristic scoring, no API call.', 'rankwriter-ai' ); ?></p>
		<p>
			<select id="rwai-do-post-id" class="regular-text">
				<option value=""><?php esc_html_e( '— Select a post —', 'rankwriter-ai' ); ?></option>
				<?php
				$recent = get_posts( array(
					'post_type'      => 'post',
					'post_status'    => array( 'publish', 'draft', 'pending' ),
					'posts_per_page' => 100,
					'orderby'        => 'date',
					'order'          => 'DESC',
				) );
				foreach ( $recent as $p ) :
					$age = floor( ( time() - strtotime( $p->post_date_gmt . ' UTC' ) ) / DAY_IN_SECONDS );
					?>
					<option value="<?php echo esc_attr( $p->ID ); ?>">
						<?php echo esc_html( $p->post_title . '  ·  ' . $age . 'd old  ·  ' . $p->post_status ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-primary" id="rwai-do-score-post"><?php esc_html_e( 'Score post', 'rankwriter-ai' ); ?></button>
		</p>
		<div id="rwai-do-post-result"></div>
	</div>

	<!-- ============== Score a draft ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '✏️ Score a draft (paste content)', 'rankwriter-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai-do-title"><?php esc_html_e( 'Title', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" id="rwai-do-title" class="large-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai-do-content"><?php esc_html_e( 'Content (HTML or plain text)', 'rankwriter-ai' ); ?></label></th>
				<td><textarea id="rwai-do-content" class="large-text code" rows="10"></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai-do-image"><?php esc_html_e( 'Featured image URL (optional)', 'rankwriter-ai' ); ?></label></th>
				<td><input type="url" id="rwai-do-image" class="large-text" placeholder="https://yoursite.com/wp-content/uploads/..." /></td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="rwai-do-score-content"><?php esc_html_e( 'Score draft', 'rankwriter-ai' ); ?></button>
		</p>
		<div id="rwai-do-draft-result"></div>
	</div>

	<!-- ============== Generate hooks ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '🎣 Generate Discover hooks', 'rankwriter-ai' ); ?></h2>
		<p class="rwai-muted"><?php esc_html_e( 'Claude returns 4 scroll-stopping opening paragraphs for any topic. Each one is concrete, mobile-friendly, and AdSense-safe.', 'rankwriter-ai' ); ?></p>
		<p>
			<input type="text" id="rwai-do-hook-topic" class="large-text" placeholder="<?php esc_attr_e( 'Article topic / working title…', 'rankwriter-ai' ); ?>" />
		</p>
		<p>
			<button type="button" class="button button-primary" id="rwai-do-hook-generate"><?php esc_html_e( '✨ Generate hooks', 'rankwriter-ai' ); ?></button>
			<span id="rwai-do-hook-status" class="rwai-status"></span>
		</p>
		<div id="rwai-do-hook-result"></div>
	</div>
</div>
