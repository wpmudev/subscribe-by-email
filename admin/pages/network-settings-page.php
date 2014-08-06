<?php

class Incsub_Subscribe_By_Email_Network_Settings_Page extends Incsub_Subscribe_By_Email_Admin_Page {
	private $settings_name;
	private $settings_group;
	private $settings;

	public function __construct() {
		$args = array(
			'slug' => 'sbe-network-settings',
			'page_title' => __( 'Subscribe By Email Network Settings', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Subscribe By Email', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_network',
			'parent' => 'settings.php',
			'network' => true
		);
		parent::__construct( $args );

 		add_action( 'admin_init', array( &$this, 'sanitize_settings' ) );

 		$this->settings_name = incsub_sbe_get_settings_slug();
		$this->settings_group = incsub_sbe_get_settings_slug();
		$this->settings = incsub_sbe_get_network_settings();

 	}

 	public function render_content() {

 		settings_errors( $this->settings_name );

 		if ( isset( $_GET['updated'] ) && ! get_settings_errors( $this->settings_name ) ) {
 			?>
 				<div class="updated"><p><?php _e( 'Settings updated', INCSUB_SBE_LANG_DOMAIN ); ?></p></div>
 			<?php
 		}

 		?>
			<form action="" method="post">
				<table class="form-table">
					<?php $this->render_row( __( 'Notification From Email', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_from_email_field' ); ?>
					<?php $this->render_row( __( 'Mail batches', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_mail_batches_field' ); ?>
					<?php $this->render_row( __( 'Keep logs files during', INCSUB_SBE_LANG_DOMAIN ), 'incsub_sbe_render_keep_logs_for_field' ); ?>
				</table>

				<?php wp_nonce_field( 'submit_sbe_network_settings' ); ?>

				<?php submit_button( __( 'Save changes', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit_sbe_network_settings' ); ?>
			</form>
 		<?php
 		
 	}

 	

 	public function sanitize_settings() {

 		if ( isset( $_GET['page'] ) && $_GET['page'] == $this->get_menu_slug() && isset( $_POST['submit_sbe_network_settings'] ) ) {

 			if ( ! check_admin_referer( 'submit_sbe_network_settings' ) )
 				return;

 			if ( ! current_user_can( $this->capability ) )
 				return;

 			$input = isset( $_POST[ $this->settings_name ] ) ? $_POST[ $this->settings_name ] : array();
 			$current_settings = incsub_sbe_get_settings();
 			$new_settings = $current_settings;

 			// From Email
			$result = incsub_sbe_sanitize_from_email( $input['from_email'] );

			if ( is_wp_error( $result ) ) {
				add_settings_error( $this->settings_name, $result->get_error_code(), $result->get_error_message() );
			}
			else {
				$new_settings['from_email'] = $result;
			}
					

 			// Batches
		    if ( ! empty( $input['mail_batches'] ) )
				$new_settings['mails_batch_size'] = absint( $input['mail_batches'] );

			// Logs
			if ( ! empty( $input['keep_logs_for'] ) ) {
				$option = absint( $input['keep_logs_for'] );
				if ( $option > 31 ) {
					$new_settings['keep_logs_for'] = 31;
				}
				elseif ( $option < 1 ) {
					$new_settings['keep_logs_for'] = 1;	
				}
				else {
					$new_settings['keep_logs_for'] = $option;
				}
			}
			incsub_sbe_update_settings( $new_settings );

			$errors = get_settings_errors( $this->settings_name, $sanitize );

			if ( empty( $errors ) ) {
				$redirect_to = add_query_arg( 'updated', 'true', $this->get_permalink() );
				wp_redirect( $redirect_to );	
				exit;
			} 			
 			
 		}

 	}
}