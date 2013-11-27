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

function incsub_sbe_render_extra_field( $type, $slug, $title, $value, $required, $show_label = false ) {
	switch ( $type ) {
		case 'text': {
			?>
				<?php if ( $show_label ): ?><label><?php endif; ?>
					<input type="text" name="sbe_extra_field_<?php echo $slug; ?>" value="<?php echo esc_attr( $value ); ?>" <?php if ( ! $show_label ): ?>placeholder="<?php echo $title; ?>"<?php endif; ?>>
				<?php if ( $show_label ): ?><?php echo $title; ?></label><?php endif; ?> <?php echo $required ? '(*)' : ''; ?>
			<?php
			break;
		}
		case 'checkbox': {
			?>
				<label>
					<input type="checkbox" name="sbe_extra_field_<?php echo $slug; ?>" <?php checked( ! empty( $value ) ); ?>>
					<?php echo $title; ?>
				</label> <?php echo $required ? '(*)' : ''; ?>
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