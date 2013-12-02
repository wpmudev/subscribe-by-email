<?php

function incsub_sbe_get_extra_field_types() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	$types = $settings_handler->get_extra_field_types();
	return $types;
}

function incsub_sbe_extra_field_types_dropdown( $selected = '' ) {
	$types = incsub_sbe_get_extra_field_types();
	foreach ( $types as $slug => $value ) {
		?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>><?php echo $value['name']; ?></option><?php
	}
}

function incsub_sbe_render_extra_field( $type, $slug, $title, $value, $atts = array() ) {
	$default_atts = array(
		'class' => 'sbe-extra-field',
		'name' => 'sbe_extra_field_' . $slug,
		'placeholder' => esc_attr( $title ),
		'id' => '',
		'show_label' => true
	);

	$atts = wp_parse_args( $atts, $default_atts );

	switch ( $type ) {
		case 'text': {
			?>
				<input type="text" id="<?php echo esc_attr( $atts['id'] ); ?>" class="<?php echo $atts['class']; ?>" name="<?php echo $atts['name']; ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo $atts['placeholder']; ?>">
			<?php
			break;
		}
		case 'checkbox': {
			?>
				<label>
					<input type="checkbox" name="<?php echo $atts['name']; ?>" <?php checked( ! empty( $value ) ); ?>>
					<?php echo $atts ['show_label'] ? $title : ''; ?>
				</label>
			<?php
			break;
		}
	}
}


function incsub_sbe_validate_extra_field( $type, $value ) {
	$new_value = $value;
	switch ( $type ) {
		case 'text': {
			$new_value = stripslashes_deep( sanitize_text_field( $new_value ) );
			break;
		}
		case 'checkbox': {
			$new_value = ! empty( $value );
			break;
		}
	}
	return $new_value;
}

function incsub_sbe_get_extra_fields_slugs() {
	$settings = incsub_sbe_get_settings();
    $extra_fields = $settings['extra_fields'];
    $extra_fields_slugs = array();
    foreach ( $extra_fields as $extra_field ) {
    	$extra_fields_slugs[] = $extra_field['slug'];
    }
    return $extra_fields_slugs;
}

function incsub_sbe_get_reserved_extra_fields_slugs() {
	return array( 'email', 'type', 'note', 'created' );
}