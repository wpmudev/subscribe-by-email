<?php

/**
 * From Email field
 */
function incsub_sbe_render_from_email_field() {
	$settings_name = incsub_sbe_get_settings_slug();
	$settings = incsub_sbe_get_settings();
	?>
		<input type="text" name="<?php echo $settings_name; ?>[from_email]" class="regular-text" value="<?php echo esc_attr( $settings['from_email'] ); ?>"><br/>
		<span class="description"><?php _e( 'Recommended: no-reply@yourdomain.com as spam filters may block other addresses.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
	<?php
}

/**
 * Subject field
 */
function incsub_sbe_render_mail_batches_field() {
	$settings_name = incsub_sbe_get_settings_slug();
	$settings = incsub_sbe_get_settings();
	$minutes = Incsub_Subscribe_By_Email::$time_between_batches / 60;
	?>
		<label for="mail_batches"><?php printf( __( 'Send %s mails every %d minutes (maximum).', INCSUB_SBE_LANG_DOMAIN ), '<input id="mail_batches" type="number" name="' . $settings_name . '[mail_batches]" class="small-text" value="' . esc_attr( $settings['mails_batch_size'] ) . '">', $minutes ); ?></label><br/>
		<span class="description"><?php printf( __( 'If you are experiencing problems when sending mails, your server may be limiting the email volume. Try reducing this number. Mails will be sent every %d minutes in groups of X mails.', INCSUB_SBE_LANG_DOMAIN ), $minutes ); ?></span>
	<?php
}


function incsub_sbe_render_keep_logs_for_field() {
	$settings_name = incsub_sbe_get_settings_slug();
	$settings = incsub_sbe_get_settings();
	?>
		<input type="number" class="small-text" size="2" name="<?php echo $settings_name; ?>[keep_logs_for]" value="<?php echo absint( $settings['keep_logs_for'] ); ?>" /> <?php _e( 'Days', INCSUB_SBE_LANG_DOMAIN ); ?> <br/><span class="description"><?php _e( '31 max.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
	<?php
}


function incsub_sbe_sanitize_from_email( $email ) {
	$from_email = sanitize_email( $email );
	
	if ( is_email( $from_email ) )
		return $from_email;

	return new WP_Error( 'invalid-from-email', __( 'Notification From Email is not a valid email', INCSUB_SBE_LANG_DOMAIN ) );
}