<?php

class Subscribe_By_Email_Shortcode {

	public $errors  = array();
	public $enqueue_scripts  = false;

	public function __construct() {
		add_shortcode( 'subscribe-by-email-form', array( $this, 'render_form' ) );
		add_action( 'init', array( &$this, 'add_tinymce_buttons' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_scripts' ) );
	}


	public function add_tinymce_buttons() {
		add_filter( 'mce_external_plugins', array( &$this, 'add_buttons' ) );
    	add_filter( 'mce_buttons', array( &$this, 'register_buttons' ) );
	}

	public function register_scripts() {
		wp_enqueue_style( 'sbe-form-css', INCSUB_SBE_ASSETS_URL . '/css/shortcode.css', array(), '20140212' );
	}


	public function add_buttons( $plugin_arr ) {
		$plugin_arr['sbeshortcode'] = INCSUB_SBE_ASSETS_URL . 'js/tiny-mce-buttons.js';
		return $plugin_arr;
	}


	public function register_buttons( $buttons ) {
		array_push( $buttons, 'sbeform' );
		return $buttons;
	}


	public function render_form( $atts ) {
		$this->enqueue_scripts = true;
		$this->process();

		extract( shortcode_atts( array(
			'bgcolor' => 'transparent',
			'textcolor' => 'inherit',
			'width' => 'auto',
			'center' => 'true',
			'success_text' => __( 'Thanks, a confirmation email has been sent to you' , INCSUB_SBE_LANG_DOMAIN )
		), $atts ) );

		$width = 'auto' == $width ? $width : $width . '%';

		$settings = incsub_sbe_get_settings();
		$extra_fields = empty( $settings['extra_fields'] ) ? array() : $settings['extra_fields'];

		if ( count( $this->errors ) == 0 && isset( $_POST['submit-subscribe-user'] ) ) {
			echo '<div class="sbe-shortcode-updated"><p>' . $success_text . '</p></div>';
		}
		else {
			?>
				<form method="post" class="sbe-shortcode-subscribe-form" id="sbe-shortcode-subscribe-form">
		        	<?php if ( count( $this->errors ) ): ?>
		        		<ul class="sbe-shortcode-error">
							<?php foreach ( $this->errors as $error ): ?>
								<li><?php echo $error; ?></li>
							<?php endforeach; ?>
		        		</ul>
		        	<?php endif; ?>
		        	
		        	<?php do_action( 'sbe_shortcode_before_fields' ); ?>
	        		
	        		<?php $email = isset( $_POST['subscription-email'] ) ? $_POST['subscription-email'] : ''; ?>
	        		<div class="sbe-shortcode-form-field-title"><?php _e( 'Email address', INCSUB_SBE_LANG_DOMAIN ); ?></div>
		        	<input type="email" class="sbe-shortcode-form-field sbe-shortcode-email-field sbe-form-field"  name="subscription-email" placeholder="<?php _e( 'ex: someone@mydomain.com', INCSUB_SBE_LANG_DOMAIN ); ?>" value="<?php echo $email; ?>"><br/>

		        	<?php if ( ! empty( $extra_fields ) ): ?>
		        		<?php foreach ( $extra_fields as $key => $value ): ?>

		        			<?php if ( 'checkbox' !== $value['type'] ): ?>
								<div class="sbe-shortcode-form-field-title"><?php echo $value['title']; ?> <?php echo $value['required'] ? '<span class="sbe-shortcode-required">(*)</span>' : ''; ?></div>
							<?php endif; ?>

		        			<?php 
		        				$current_value = isset( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) ? $_POST[ 'sbe_extra_field_' . $value['slug'] ] : '';
								$atts = array(
									'placeholder' => '',
									'name' => 'sbe_extra_field_' . $value['slug'],
									'class' => 'sbe-shortcode-form-field sbe-shortcode-' . $value['slug'] . '-field',
								);
							?>

							<?php incsub_sbe_render_extra_field( $value['type'], $value['slug'], $value['title'], $current_value, $atts ); ?>
							<?php if ( 'checkbox' === $value['type'] ): ?>
								<?php echo $value['required'] ? '<span class="sbe-shortcode-required">(*)</span>' : ''; ?>
							<?php endif; ?>
							<br/>

		        		<?php endforeach; ?>
		        	<?php endif; ?>

		        	<?php do_action( 'sbe_shortcode_form_fields' ); ?>

			        <?php wp_nonce_field( 'sbe_shortcode_subscribe', 'sbe_subscribe_nonce' ); ?>
		        	<input type="hidden" class="sbe-shortcode-form-field sbe-form-field" name="action" value="sbe_shortcode_subscribe_user">
		        	<div class="sbe-shortcode-form-submit-container">
		        		<span class="sbe-spinner"></span>
		        		<input type="submit" class="sbe-shortcode-form-submit" name="submit-subscribe-user" value="<?php echo apply_filters( 'sbe_shortcode_button_text', __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN ) ); ?>">
		        	</div>

					<?php do_action( 'sbe_shortcode_after_fields' ); ?>
		        </form>

			<?php
		}
	}

	private function process() {
		if ( isset( $_POST['submit-subscribe-user'] ) ) {
			if ( ! wp_verify_nonce( $_POST['sbe_subscribe_nonce'], 'sbe_shortcode_subscribe' ) )
				return;

			$input = $_POST;
			$errors = array();

			// Checking email
			$email = sanitize_email( $input['subscription-email'] );
			if ( ! is_email( $email ) )
				$errors[]  = __( 'Invalid e-mail address', INCSUB_SBE_LANG_DOMAIN );

			// Checking extra fields
			$settings = incsub_sbe_get_settings();
			$extra_fields = $settings['extra_fields'];

			// Here we'll save the fields and their values
			$fields_to_save = array();
			foreach ( $extra_fields as $extra_field ) {
				$required = $extra_field['required'];

				// Value of the field sent
				$field_value = isset( $input['sbe_extra_field_' . $extra_field['slug'] ] ) ?  $input['sbe_extra_field_' . $extra_field['slug'] ] : '';
				$new_value = incsub_sbe_validate_extra_field( $extra_field['type'], $field_value );

				if ( $required && ( empty( $new_value ) ) ) {
					// Field is empty and is required
					$errors[] = sprintf( __( '%s is a mandatory field.', INCSUB_SBE_LANG_DOMAIN ), $extra_field['title'] );
				}
				else {
					// Field is ok
					$fields_to_save[ $extra_field['slug'] ] = $new_value;
				}
			}

			$this->errors = apply_filters( 'sbe_shortcode_validate_form', $errors, $email, $fields_to_save );
    		
			if ( empty( $this->errors ) ) {
				
				$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', false, $fields_to_save );

				if ( $sid && $settings['get_notifications'] ) {
					require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
					$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template( $email );
					$admin_notice->send_email();
				}
				
				return true;		
    		}
		}
	}


}