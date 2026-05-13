<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$pins   = (array) $data['pins'];
$stats  = (array) $data['stats'];
$msg    = (string) $data['msg'];
$err    = (string) $data['err'];
$niches = RankWriter_AI_Pinterest_Engine::supported_niches();
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Pinterest Auto Content', 'rankwriter-ai' ); ?></h1>
	<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PINTEREST_SLUG, array( 'new' => 1 ) ) ); ?>" class="page-title-action"><?php esc_html_e( '✨ Generate pins for a topic', 'rankwriter-ai' ); ?></a>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Generate Pinterest-ready pin packages for any blog post or topic — title, description, hashtags, big bold overlay text, image prompt, board suggestions, and a server-rendered 1000×1500 pin image. Each post can have multiple pin variations to A/B test.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'pin-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pin saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pin-deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pin deleted.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pin-generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			printf( esc_html__( 'Generated %d pin variations.', 'rankwriter-ai' ), isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'pin-image' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pin image rendered.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'pin-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Action failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Total pins', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['total'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Draft', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['draft'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Scheduled', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['scheduled'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Ready', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['ready'] ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Posted', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $stats['posted'] ); ?></div>
		</div>
	</div>

	<!-- ============== Generate from a post ============== -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Generate pins for an existing post', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-form">
			<input type="hidden" name="rwai_action" value="pin_generate_post" />
			<?php wp_nonce_field( RankWriter_AI_Admin::PINTEREST_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwai_pin_post"><?php esc_html_e( 'Post', 'rankwriter-ai' ); ?></label></th>
					<td>
						<select id="rwai_pin_post" name="post_id" required>
							<option value=""><?php esc_html_e( '— Select a post —', 'rankwriter-ai' ); ?></option>
							<?php
							$recent = get_posts( array(
								'post_type'      => 'post',
								'post_status'    => array( 'publish', 'draft', 'pending' ),
								'posts_per_page' => 100,
								'orderby'        => 'date',
								'order'          => 'DESC',
							) );
							foreach ( $recent as $p ) : ?>
								<option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_pin_niche"><?php esc_html_e( 'Niche', 'rankwriter-ai' ); ?></label></th>
					<td>
						<select id="rwai_pin_niche" name="niche">
							<option value=""><?php esc_html_e( 'Auto-detect', 'rankwriter-ai' ); ?></option>
							<?php foreach ( $niches as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_pin_count_form"><?php esc_html_e( 'How many variations', 'rankwriter-ai' ); ?></label></th>
					<td><input type="number" id="rwai_pin_count_form" name="count" min="1" max="5" value="3" /></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( '✨ Generate pins', 'rankwriter-ai' ); ?></button>
			</p>
		</form>
	</div>

	<!-- ============== Pin library ============== -->
	<?php if ( empty( $pins ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<p><?php esc_html_e( 'No pins yet. Generate some from the form above or enable "Auto-generate pins on publish" in Settings.', 'rankwriter-ai' ); ?></p>
		</div>
	<?php else : ?>
		<div class="rwai-pin-grid">
		<?php foreach ( $pins as $p ) :
			$status_class = 'rwai-pill-warn';
			if ( 'posted' === $p['status'] ) { $status_class = 'rwai-pill-ok'; }
			elseif ( 'ready' === $p['status'] ) { $status_class = 'rwai-pill-ok'; }
			$image_url = $p['image_attachment_id'] ? wp_get_attachment_image_url( $p['image_attachment_id'], 'medium' ) : '';
			$edit_url  = RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PINTEREST_SLUG, array( 'pin' => $p['id'] ) );
			?>
			<div class="rwai-pin-card">
				<a href="<?php echo esc_url( $edit_url ); ?>" class="rwai-pin-card-image">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php else : ?>
						<div class="rwai-pin-card-placeholder">📌</div>
					<?php endif; ?>
				</a>
				<div class="rwai-pin-card-body">
					<div class="rwai-pin-card-niche">
						<span class="rwai-pill rwai-niche-<?php echo esc_attr( $p['niche'] ); ?>"><?php echo esc_html( isset( $niches[ $p['niche'] ] ) ? $niches[ $p['niche'] ] : $p['niche'] ); ?></span>
						<span class="rwai-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $p['status'] ); ?></span>
					</div>
					<h3><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( wp_trim_words( $p['title'], 14 ) ); ?></a></h3>
					<p class="rwai-pin-card-overlay"><?php echo esc_html( $p['overlay_text'] ); ?></p>
					<?php if ( ! empty( $p['post_id'] ) && get_post( $p['post_id'] ) ) : ?>
						<p class="rwai-muted"><?php echo esc_html__( 'From:', 'rankwriter-ai' ) . ' ' . esc_html( get_the_title( $p['post_id'] ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
