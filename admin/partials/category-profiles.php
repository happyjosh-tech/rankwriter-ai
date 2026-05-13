<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$profiles = (array) $data['profiles'];
$schema   = (array) $data['schema'];
$editing  = $data['editing'];
$creating = (bool) $data['creating'];
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];

$show_form = $creating || $editing;
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Category Profiles', 'rankwriter-ai' ); ?></h1>
	<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG, array( 'new' => 1 ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'rankwriter-ai' ); ?></a>
	<form method="post" class="rwai-inline-form" style="display:inline-block;margin-left:6px;">
		<input type="hidden" name="rwai_action" value="restore_presets" />
		<?php wp_nonce_field( RankWriter_AI_Category_Profiles::NONCE_ACT, RankWriter_AI_Category_Profiles::NONCE_KEY ); ?>
		<button type="submit" class="page-title-action" onclick="return confirm('<?php echo esc_js( __( 'Re-seed any built-in presets that have been deleted? Your custom profiles will not be touched.', 'rankwriter-ai' ) ); ?>');"><?php esc_html_e( 'Restore default presets', 'rankwriter-ai' ); ?></button>
	</form>
	<hr class="wp-header-end" />

	<?php if ( 'profile-saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Profile saved.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'profile-deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Profile deleted.', 'rankwriter-ai' ); ?></p></div>
	<?php elseif ( 'presets-restored' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			/* translators: %d: count of presets created */
			printf( esc_html( _n( 'Restored %d default preset.', 'Restored %d default presets.', isset( $_GET['count'] ) ? (int) $_GET['count'] : 0, 'rankwriter-ai' ) ), isset( $_GET['count'] ) ? (int) $_GET['count'] : 0 );
		?></p></div>
	<?php elseif ( 'profile-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( '' !== $err ? $err : __( 'Could not save profile.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<?php
		$current = is_array( $editing ) ? $editing : array(
			'id'   => 0,
			'name' => '',
		);
		foreach ( $schema as $k => $cfg ) {
			if ( ! isset( $current[ $k ] ) ) {
				$current[ $k ] = isset( $cfg['default'] ) ? $cfg['default'] : '';
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . RankWriter_AI_Admin::PROFILES_SLUG ) ); ?>" class="rwai-form" data-rwai-ai-context="category_profile">
			<input type="hidden" name="rwai_action" value="save_profile" />
			<input type="hidden" name="profile_id" value="<?php echo esc_attr( (int) $current['id'] ); ?>" />
			<?php wp_nonce_field( RankWriter_AI_Category_Profiles::NONCE_ACT, RankWriter_AI_Category_Profiles::NONCE_KEY ); ?>

			<h2><?php echo $current['id'] ? esc_html__( 'Edit Category Profile', 'rankwriter-ai' ) : esc_html__( 'New Category Profile', 'rankwriter-ai' ); ?>
				<button type="button" class="button button-secondary rwai-ai-fill-all" data-rwai-ai-needs="profile_name"><?php esc_html_e( '✨ AI fill empty fields', 'rankwriter-ai' ); ?></button>
			</h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwai_profile_name"><?php esc_html_e( 'Custom Category Name', 'rankwriter-ai' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="rwai_profile_name" name="profile_name" value="<?php echo esc_attr( $current['name'] ); ?>" required data-rwai-ai-target="profile_name" />
						<p class="description"><?php esc_html_e( 'Example: Agriculture Grants, Visa Sponsorship Jobs, Pet Care.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwai_wp_cat_profile"><?php esc_html_e( 'Post to WordPress category', 'rankwriter-ai' ); ?></label></th>
					<td>
						<?php
						$picker_name          = 'wp_category_id';
						$picker_id            = 'rwai_wp_cat_profile';
						$picker_value         = ! empty( $current['wp_category_id'] ) ? (int) $current['wp_category_id'] : 0;
						$picker_new_value     = '';
						$picker_label         = __( 'Post to WordPress category', 'rankwriter-ai' );
						$picker_default_label = sprintf(
							/* translators: %s: profile name */
							__( '— Auto: create / reuse a WP category named "%s" —', 'rankwriter-ai' ),
							$current['name'] ? $current['name'] : __( '(this profile)', 'rankwriter-ai' )
						);
						include RWAI_PLUGIN_DIR . 'admin/partials/_wp-category-picker.php';
						?>
						<p class="description"><?php esc_html_e( 'Pick an existing category to keep your blog tidy, or let RankWriter AI manage a dedicated one. This default applies to all articles generated from this profile and can still be overridden per-article.', 'rankwriter-ai' ); ?></p>
					</td>
				</tr>

				<?php foreach ( $schema as $key => $cfg ) : ?>
					<tr>
						<th scope="row">
							<label for="rwai_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $cfg['label'] ); ?></label>
							<br><button type="button" class="button button-small rwai-ai-fill" data-rwai-ai-field="<?php echo esc_attr( $key ); ?>" data-rwai-ai-needs="profile_name"><?php esc_html_e( '✨ AI fill', 'rankwriter-ai' ); ?></button>
						</th>
						<td>
							<?php
							$val = isset( $current[ $key ] ) ? $current[ $key ] : '';
							if ( 'select' === $cfg['type'] && ! empty( $cfg['options'] ) ) :
								?>
								<select id="rwai_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" data-rwai-ai-target="<?php echo esc_attr( $key ); ?>">
									<?php foreach ( $cfg['options'] as $opt_val => $opt_label ) : ?>
										<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $val, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'textarea' === $cfg['type'] ) : ?>
								<textarea class="large-text code" rows="4" id="rwai_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" data-rwai-ai-target="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $val ); ?></textarea>
							<?php else : ?>
								<input type="text" class="regular-text" id="rwai_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" data-rwai-ai-target="<?php echo esc_attr( $key ); ?>" />
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Profile', 'rankwriter-ai' ); ?></button>
				<a href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'rankwriter-ai' ); ?></a>
			</p>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'All profiles', 'rankwriter-ai' ); ?></h2>
	<table class="widefat striped rwai-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Category', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Type', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Country', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Tone', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Monetization', 'rankwriter-ai' ); ?></th>
				<th><?php esc_html_e( 'Image style', 'rankwriter-ai' ); ?></th>
				<th class="rwai-col-actions"><?php esc_html_e( 'Actions', 'rankwriter-ai' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $profiles ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No category profiles yet — create your first one above or use "Restore default presets".', 'rankwriter-ai' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $profiles as $p ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
					<td>
						<?php if ( ! empty( $p['is_preset'] ) ) : ?>
							<span class="rwai-pill rwai-pill-ok"><?php esc_html_e( 'Preset', 'rankwriter-ai' ); ?></span>
						<?php else : ?>
							<span class="rwai-pill rwai-pill-warn"><?php esc_html_e( 'Custom', 'rankwriter-ai' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( isset( $p['target_country'] ) ? $p['target_country'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $p['article_tone'] ) ? $p['article_tone'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $p['monetization_goal'] ) ? $p['monetization_goal'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $p['image_style'] ) ? $p['image_style'] : '' ); ?></td>
					<td class="rwai-col-actions">
						<a class="button button-small" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::PROFILES_SLUG, array( 'edit' => $p['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'rankwriter-ai' ); ?></a>
						<a class="button button-small button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'profile_id' => $p['id'] ) ) ); ?>"><?php esc_html_e( 'Generate', 'rankwriter-ai' ); ?></a>
						<form method="post" class="rwai-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this category profile?', 'rankwriter-ai' ) ); ?>');">
							<input type="hidden" name="rwai_action" value="delete_profile" />
							<input type="hidden" name="profile_id" value="<?php echo esc_attr( $p['id'] ); ?>" />
							<?php wp_nonce_field( RankWriter_AI_Admin::DELETE_NONCE ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'rankwriter-ai' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
