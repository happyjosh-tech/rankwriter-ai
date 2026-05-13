<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$groups  = (array) $data['groups'];
$enabled = (array) $data['enabled'];
$msg     = (string) $data['msg'];
$err     = (string) $data['err'];
$all     = RankWriter_AI_Language::languages();
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Translations', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Manage every translation group on your site. Each row is one piece of content with its language variants. Missing languages can be generated on demand — translations are Claude-rewritten, not literal, with cultural references and units localized for the target audience.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'tr-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Translation generated.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'tr-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Translation failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<!-- Single-post translate form -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( '✨ Translate a post on demand', 'rankwriter-ai' ); ?></h2>
		<form method="post" class="rwai-form">
			<input type="hidden" name="rwai_action" value="translate_post" />
			<?php wp_nonce_field( RankWriter_AI_Admin::TRANSLATION_NONCE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwai_tr_post"><?php esc_html_e( 'Source post', 'rankwriter-ai' ); ?></label></th>
					<td>
						<select id="rwai_tr_post" name="post_id" required>
							<option value=""><?php esc_html_e( '— Select a post —', 'rankwriter-ai' ); ?></option>
							<?php
							$recent = get_posts( array(
								'post_type'      => 'post',
								'post_status'    => array( 'publish', 'draft', 'pending' ),
								'posts_per_page' => 100,
								'orderby'        => 'date',
								'order'          => 'DESC',
							) );
							foreach ( $recent as $rp ) :
								$lang = RankWriter_AI_Language::get_post_language( $rp->ID );
								?>
								<option value="<?php echo esc_attr( $rp->ID ); ?>"><?php echo esc_html( $rp->post_title . ' (' . strtoupper( $lang ) . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Target languages', 'rankwriter-ai' ); ?></th>
					<td>
						<?php foreach ( $enabled as $code ) :
							if ( 'en' === $code ) { continue; }
							$cfg = $all[ $code ] ?? null;
							if ( ! $cfg ) { continue; }
							?>
							<label style="display:inline-block;margin-right:14px;">
								<input type="checkbox" name="targets[]" value="<?php echo esc_attr( $code ); ?>" />
								<?php echo esc_html( $cfg['name'] . ' (' . $cfg['native'] . ')' ); ?>
							</label>
						<?php endforeach; ?>
						<?php if ( count( $enabled ) <= 1 ) : ?>
							<p class="description"><?php
								printf(
									/* translators: %s: settings link */
									wp_kses_post( __( 'No additional languages enabled. %s', 'rankwriter-ai' ) ),
									'<a href="' . esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Enable more in Settings', 'rankwriter-ai' ) . '</a>'
								);
							?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Translate', 'rankwriter-ai' ); ?></button>
			</p>
		</form>
	</div>

	<!-- Existing translation groups -->
	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Translation groups', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $groups ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No translation groups yet. Translate a post above to start one.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source title', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Languages', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Missing', 'rankwriter-ai' ); ?></th>
						<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $groups as $g ) :
					$translations = $g['translations'];
					$existing     = array();
					foreach ( $translations as $t ) {
						$existing[ $t['lang'] ] = $t;
					}
					$missing = array();
					foreach ( $enabled as $code ) {
						if ( ! isset( $existing[ $code ] ) ) { $missing[] = $code; }
					}
					$primary = $g['primary'];
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $primary['title'] ); ?></strong>
							<br><small class="rwai-muted">ID: <?php echo (int) $primary['post_id']; ?></small>
						</td>
						<td>
							<?php foreach ( $translations as $t ) :
								$post_status = get_post_status( $t['post_id'] );
								$cls = 'publish' === $post_status ? 'rwai-pill-ok' : 'rwai-pill-warn';
							?>
								<a href="<?php echo esc_url( get_edit_post_link( $t['post_id'] ) ); ?>" class="rwai-pill <?php echo esc_attr( $cls ); ?>" title="<?php echo esc_attr( $t['title'] ); ?>"><?php echo esc_html( strtoupper( $t['lang'] ) ); ?></a>
							<?php endforeach; ?>
						</td>
						<td>
							<?php if ( empty( $missing ) ) : ?>
								<span class="rwai-muted"><?php esc_html_e( 'Complete', 'rankwriter-ai' ); ?></span>
							<?php else : ?>
								<?php foreach ( $missing as $code ) : ?>
									<form method="post" class="rwai-inline-form">
										<input type="hidden" name="rwai_action" value="translate_post" />
										<input type="hidden" name="post_id" value="<?php echo esc_attr( $primary['post_id'] ); ?>" />
										<input type="hidden" name="targets[]" value="<?php echo esc_attr( $code ); ?>" />
										<?php wp_nonce_field( RankWriter_AI_Admin::TRANSLATION_NONCE ); ?>
										<button type="submit" class="button button-small" title="<?php echo esc_attr( $all[ $code ]['name'] ?? $code ); ?>">+ <?php echo esc_html( strtoupper( $code ) ); ?></button>
									</form>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
						<td class="rwai-col-actions">
							<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $primary['post_id'] ) ); ?>"><?php esc_html_e( 'Open source', 'rankwriter-ai' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
