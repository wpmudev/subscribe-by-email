<?php

// HTML Email Templates integration
if ( ! function_exists( 'is_plugin_active_for_network' ) )
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

if ( is_plugin_active_for_network( 'htmlemail/htmlemail.php' ) ) {
	if ( ! class_exists( 'HTML_emailer' ) )
		return false;

	add_action( 'sbe_pre_send_emails', 'sbe_remove_html_email_templates_hooks' );
	add_action( 'sbe_after_send_emails', 'sbe_remove_html_email_templates_hooks' );
	
	function sbe_remove_html_email_templates_hooks() {
		remove_filter( 'wp_mail', array( 'HTML_emailer', 'wp_mail' ) );
		remove_action( 'phpmailer_init', array( &$this,'convert_plain_text' ) );	
	}

	function sbe_restore_html_email_templates_hooks() {
		add_filter( 'wp_mail', array( 'HTML_emailer', 'wp_mail' ) );
		add_action( 'phpmailer_init', array( &$this,'convert_plain_text' ) );	
	}
	
}



// WPML

//add_filter( 'sbe_blog_default_settings', 'sbe_wpml_add_default_settings' );
/**
 * Add a new setting in SBE Settings
 *
 * @param $defaults
 *
 * @return mixed
 */
function sbe_wpml_add_default_settings( $defaults ) {
	if ( class_exists( 'SitePress' ) )
		$defaults['not_send_translated_posts'] = false;

	return $defaults;
}

//add_action( 'sbe_register_settings', 'sbe_wpml_register_settings', 10, 4 );
/**
 * Add new setting in SBE Settings page
 */
function sbe_wpml_register_settings( $current_tab, $settings_group, $settings_name, $menu_slug ) {
	if ( ! class_exists( 'SitePress' ) )
		return;

	if ( $current_tab == 'general' ) {
		add_settings_section( 'sbe-wpml-settings', __( 'WPML', INCSUB_SBE_LANG_DOMAIN ), null, $menu_slug );
		add_settings_field(
			'do-not-send-translated-posts',
			__( 'Do not resend translated posts', INCSUB_SBE_LANG_DOMAIN ),
			'sbe_wpml_render_do_not_send_translated_posts_field',
			$menu_slug,
			'sbe-wpml-settings',
			array(
				'settings_name' => $settings_name
			)
		);
	}
}

function sbe_wpml_render_do_not_send_translated_posts_field( $args ) {
	$settings = incsub_sbe_get_settings();
	?>
		<label for="">
			<input type="checkbox" name="<?php echo $args['settings_name']; ?>[not_send_translated_posts]" <?php checked( $settings['not_send_translated_posts'] ); ?>>
			<span class="description"><?php _e( 'Check this box if you do not want to send translated posts if they have been already sent. i.e. If you have already sent a post in English, the Spanish translation will not be sent.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		</label>
	<?php
}

//add_filter( 'sbe_sanitize_settings', 'sbe_wpml_sanitize_settings', 10, 3 );
function sbe_wpml_sanitize_settings( $new_settings, $input, $settings_name ) {
	if ( class_exists( 'SitePress' ) ) {
		if ( isset( $input['not_send_translated_posts'] ) ) {
			$new_settings['not_send_translated_posts'] = true;
		}
		else {
			$new_settings['not_send_translated_posts'] = false;
		}
	}

	return $new_settings;
}


//add_filter( 'sbe_enqueue_emails_campaign_args', 'sbe_wpml_do_not_send_translated_posts' );
/**
 * @param Array $args Campaign Args that will be inserted
 *
 * @return Array New args array
 */
function sbe_wpml_do_not_send_translated_posts( $args ) {
	$settings = incsub_sbe_get_settings();

	if ( class_exists( 'SitePress' ) &&  function_exists( 'icl_object_id' ) && ! empty( $args['posts_ids'] ) && $settings['not_send_translated_posts'] ) {

		// Get all languages registered in the site
		$languages = icl_get_languages();

		$args['posts_ids'] = array_map( 'absint', $args['posts_ids'] );
		$new_args = $args;

		// We need to search for every translated post for the current post
		foreach ( $args['posts_ids' ] as $key => $source_post_id ) {
			$translated_ids = array();
			$source_post_type = get_post_type( $source_post_id );
			foreach ( $languages as $lang_code => $lang ) {
				$result = absint( icl_object_id( $source_post_id, $source_post_type , false, $lang_code ) );
				if ( $result && $result != $source_post_id && get_post( $result ) )
					$translated_ids[] = absint( $result );
			}

			foreach ( $translated_ids as $post_id ) {
				$is_sent = get_post_meta( $post_id, 'sbe_sent', true );
				// If any translated post for the current one has been translated, do not send it
				if ( $is_sent ) {
					unset( $new_args['posts_ids'][ $key ] );
					// And mark it as sent
					update_post_meta( $post_id, 'sbe_sent', true );
					break;
				}
			}
		}

		return $new_args;

	}

	return $args;
}
