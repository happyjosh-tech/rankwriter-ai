<?php
/**
 * Reusable WordPress-category picker.
 *
 * Required vars in scope:
 *   $picker_name        string  Base form name (e.g. "wp_category_id").
 *   $picker_id          string  HTML id for the select.
 *   $picker_value       int|string  Current selected term ID, or "__new__".
 *   $picker_new_value   string  Current "create new" text value.
 *   $picker_label       string  Field label shown above the control.
 *   $picker_default_label string Label for the "use default" zero option.
 *
 * The picker submits as either an integer (existing term ID) or the string
 * "__new__" plus a second field "{name}_new_name" containing the name to
 * create. The backend's resolve_wp_category_from_request() handles both.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$picker_name          = isset( $picker_name ) ? $picker_name : 'wp_category_id';
$picker_id            = isset( $picker_id ) ? $picker_id : 'rwai_wp_category_picker';
$picker_value         = isset( $picker_value ) ? $picker_value : 0;
$picker_new_value     = isset( $picker_new_value ) ? $picker_new_value : '';
$picker_label         = isset( $picker_label ) ? $picker_label : __( 'WordPress category', 'rankwriter-ai' );
$picker_default_label = isset( $picker_default_label ) ? $picker_default_label : __( '— Use this profile\'s default category —', 'rankwriter-ai' );

$rwai_picker_terms = get_terms( array(
	'taxonomy'   => 'category',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
	'number'     => 0,
) );
if ( is_wp_error( $rwai_picker_terms ) ) {
	$rwai_picker_terms = array();
}

$is_new_mode = ( '__new__' === (string) $picker_value );
?>
<div class="rwai-wp-cat-picker" data-rwai-picker>
	<select id="<?php echo esc_attr( $picker_id ); ?>" name="<?php echo esc_attr( $picker_name ); ?>" data-rwai-picker-select>
		<option value="0" <?php selected( ! $is_new_mode && (int) $picker_value === 0 ); ?>><?php echo esc_html( $picker_default_label ); ?></option>
		<?php if ( ! empty( $rwai_picker_terms ) ) : ?>
			<optgroup label="<?php esc_attr_e( 'Existing categories', 'rankwriter-ai' ); ?>">
				<?php foreach ( $rwai_picker_terms as $rwai_term ) : ?>
					<option value="<?php echo esc_attr( $rwai_term->term_id ); ?>" <?php selected( ! $is_new_mode && (int) $picker_value === (int) $rwai_term->term_id ); ?>>
						<?php echo esc_html( $rwai_term->name . ' (' . $rwai_term->count . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</optgroup>
		<?php endif; ?>
		<option value="__new__" <?php selected( $is_new_mode ); ?>><?php esc_html_e( '+ Create a new category…', 'rankwriter-ai' ); ?></option>
	</select>
	<div class="rwai-wp-cat-new" data-rwai-picker-new style="<?php echo $is_new_mode ? '' : 'display:none;'; ?>margin-top:6px;">
		<input type="text" class="regular-text" name="<?php echo esc_attr( $picker_name ); ?>_new_name" value="<?php echo esc_attr( $picker_new_value ); ?>" placeholder="<?php esc_attr_e( 'New category name', 'rankwriter-ai' ); ?>" />
		<p class="description" style="margin-top:4px;"><?php esc_html_e( 'A new WordPress category will be created with this name. If a category with this name already exists, posts will be added to it instead.', 'rankwriter-ai' ); ?></p>
	</div>
</div>
