<?php

class Subscribe_By_Email_Shortcode {

	public $errors  = array();
	public $enqueue_scripts  = false;

	public function __construct() {

		$add_sbe_shortcode = apply_filters( 'sbe_register_shortcode', true );
		if ( ! $add_sbe_shortcode )
			return false;

		add_shortcode( 'subscribe-by-email-form', array( $this, 'render_form' ) );
		
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_scripts' ), 999 );
		add_action( 'wp_footer', array( &$this, 'enqueue_scripts' ), 999 );

		$this->init_tiny_mce_button();

		if ( ( is_admin() || apply_filters( 'sbe_display_tinymce_buttons_in_front', true ) ) && current_user_can( apply_filters( 'sbe_display_tinymce_buttons_cap', 'publish_posts' ) ) ) {
			add_action( 'wp_head', array( &$this, 'register_footer_scripts' ) );
			add_action( 'wp_head', array( $this,'add_icon_styles' ) );
		}
	}

	// TINY MCE FUNCTIONS
	function init_tiny_mce_button() {
		add_action( 'admin_head', array( $this, 'add_shortcode_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_admin_scripts' ) );
	}

	function add_shortcode_button() {
		if ( 'true' == get_user_option( 'rich_editing' ) ) {
			add_filter( 'mce_external_plugins', array( $this, 'add_shortcode_tinymce_plugin' ) );
			add_filter( 'mce_buttons', array( $this, 'register_shortcode_button' ) );
			add_filter( 'mce_external_languages', array( $this, 'add_tinymce_i18n' ) );
		}
	}

	public function enqueue_editor_admin_scripts() {
		wp_enqueue_style( 'sbe-admin-shortcodes', INCSUB_SBE_PLUGIN_URL . '/admin/assets/css/editor-shortcode.css' );
	}

	public function add_shortcode_tinymce_plugin( $plugins ) {
		$plugins['sbe_shortcodes'] = INCSUB_SBE_PLUGIN_URL . '/admin/assets/js/editor-shortcode.js';
		return $plugins;
	}

	public function register_shortcode_button( $buttons ) {
		array_push( $buttons, '|', 'sbe_shortcodes' );
		return $buttons;
	}

	public function add_tinymce_i18n( $i18n ) {
		$i18n['sbe_shortcodes'] = INCSUB_SBE_PLUGIN_DIR . '/admin/tinymce-shortcodes-i18n.php';
		return $i18n;
	}


	// END //

	function add_icon_styles() {
		?>
		<style>
			.mce-i-sbeform:before {
				font: normal 20px/1 'dashicons';
				content: "\f466";
			}
		</style>
		<?php
	}




	public function register_scripts() {
		wp_enqueue_style( 'sbe-form-css', INCSUB_SBE_ASSETS_URL . '/css/shortcode.css', array(), '20140212' );
		wp_enqueue_script( 'sbe-shortcode', INCSUB_SBE_ASSETS_URL . 'js/shortcode.js', array( 'jquery' ), '', true );
	}

	public function enqueue_scripts() {
		if ( $this->enqueue_scripts ) {
			wp_enqueue_script( 'sbe-shortcode' );
		}
	}

	public function register_footer_scripts() {
		global $wp_version;
		$l10n = array(
			'title' => __( 'Insert Subscribe By Email Form', INCSUB_SBE_LANG_DOMAIN ),
			'png_icon' => version_compare( $wp_version, '3.8', '>=' ) ? '' : INCSUB_SBE_ASSETS_URL . '/images/tinymceicon.png'
		);
		$l10n = json_encode($l10n);
		?>
			<script>
				var sbe_l10n = <?php echo $l10n; ?>;
			</script>
		<?php
	}


	public function render_form( $atts ) {
		$this->enqueue_scripts = true;

		extract( shortcode_atts( array(
			'bgcolor' => 'transparent',
			'textcolor' => 'inherit',
			'width' => 'auto',
			'center' => 'true',
			'success_text' => __( 'Thanks, a confirmation email has been sent to you.' , INCSUB_SBE_LANG_DOMAIN ),
			'success_autopt_text' => __( 'Thanks, you have been subscribed to our list.' , INCSUB_SBE_LANG_DOMAIN ),
			'autopt' => 'false'
		), $atts ) );

		if ( $autopt === 'true' )
			$autopt = true;
		else
			$autopt = false;

		$this->process( $autopt );

		$width = 'auto' == $width ? $width : $width . '%';

		$settings = incsub_sbe_get_settings();
		$extra_fields = empty( $settings['extra_fields'] ) ? array() : $settings['extra_fields'];

		ob_start();

		if ( count( $this->errors ) == 0 && isset( $_POST['submit-subscribe-user'] ) && ! $autopt ) {
			echo '<div id="sbe-shortcode-updated" class="sbe-shortcode-updated"><p>' . $success_text . '</p></div>';
		}
		elseif ( count( $this->errors ) == 0 && isset( $_POST['submit-subscribe-user'] ) && $autopt ) {
			echo '<div id="sbe-shortcode-updated" class="sbe-shortcode-updated"><p>' . $success_autopt_text . '</p></div>';
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

		return ob_get_clean();
	}

	private function process( $autopt = false ) {
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
				
				$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', $autopt, $fields_to_save );
				
				return true;		
    		}
		}
	}


}