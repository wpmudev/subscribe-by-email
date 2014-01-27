<?php

class Subscribe_By_Email_Shortcode {

	public $errors  = array();

	public function __construct() {
		add_shortcode( 'subscribe-by-email-form', array( $this, 'render_form' ) );
		add_action( 'init', array( &$this, 'add_tinymce_buttons' ) );
		add_action( 'wp_ajax_display_sbe_shortcode_admin_form', array( &$this, 'display_shortcode_admin_form' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( 'post.php' == $hook || 'post-new.php' == $hook ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	public function add_tinymce_buttons() {
		add_filter( 'mce_external_plugins', array( &$this, 'add_buttons' ) );
    	add_filter( 'mce_buttons', array( &$this, 'register_buttons' ) );
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

		extract( shortcode_atts( array(
			'bgcolor' => 'transparent',
			'textcolor' => 'inherit',
			'width' => 'auto',
			'center' => 'true'
		), $atts ) );

		$width = 'auto' == $width ? $width : $width . '%';

		$settings = incsub_sbe_get_settings();
		$extra_fields = empty( $settings['extra_fields'] ) ? array() : $settings['extra_fields'];
		?>
			<form method="post" class="sbe-shortcode-subscribe-form" id="sbe-shortcode-subscribe-form">
	        	<?php if ( count( $this->errors ) > 0 ): ?>
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
			<style>
				.sbe-shortcode-subscribe-form {
					padding:15px;
					color:<?php echo $textcolor; ?>;
					background-color:<?php echo $bgcolor; ?>;
					box-sizing:border-box;
					width:<?php echo $width; ?>;
					<?php if ( $center == 'true' ): ?>
						margin:0 auto;
					<?php endif; ?>
				}
				.sbe-shortcode-subscribe-form input[type="text"],
				.sbe-shortcode-subscribe-form input[type="email"] {
					width:100%;
					box-sizing:border-box;
				}
				.sbe-shortcode-subscribe-form .sbe-shortcode-form-field-title,
				.sbe-shortcode-subscribe-form label {
					margin-top:15px;
				}

				.sbe-shortcode-subscribe-form label {
					display: inline-block;
				}

				.sbe-shortcode-form-submit {
					margin-top:15px;
				}
			</style>
			<?php
	}

	public function display_shortcode_admin_form() {
		if ( empty( $_POST['sbe_action'] ) )
			die();

		$action = $_POST['sbe_action'];
		$params = ! empty( $_POST['params'] ) ? $_POST['params'] : array();
		
		ob_start();
		switch( $action ) {
			case 'load-ui': {
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
					die();
				}

				?>
				<h3><?php _e( 'Insert Subscribe By Email Form', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
				<div id="sbe-admin-shortcode">
					<div class="sbe-admin-shortcode-field">
						<label>
							<?php _e( 'Form background color', INCSUB_SBE_LANG_DOMAIN ); ?> <br/>
							<input class="sbe-color-field" type="text" name="bgcolor" value=""> 
						</label>
						<label>
							<input type="checkbox" checked name="bgcolortheme" data-form-element="bg" value="transparent"> <?php _e( 'Theme color', INCSUB_SBE_LANG_DOMAIN ); ?>
						</label>
					</div>
					<div class="sbe-admin-shortcode-field">
						<label>
							<?php _e( 'Form text color', INCSUB_SBE_LANG_DOMAIN ); ?> <br/>
							<input class="sbe-color-field" type="text" name="textcolor" value=""> 
						</label>
						<label>
							<input type="checkbox" checked name="textcolortheme" value="inherit"> <?php _e( 'Theme color', INCSUB_SBE_LANG_DOMAIN ); ?>
						</label>
						
					</div>
					<div class="sbe-admin-shortcode-field">
						<label>
							<?php _e( 'Form width (% of its container)', INCSUB_SBE_LANG_DOMAIN ); ?> <br/>
							<input type="text" name="width" value=""> 
						</label>
						<label>
							<input type="checkbox" checked name="widthauto" value="auto"> <?php _e( 'Auto', INCSUB_SBE_LANG_DOMAIN ); ?>
						</label>
					</div>
					<div class="sbe-admin-shortcode-field">
						<label>
							<input type="checkbox" checked name="center" value="true"> <?php _e( 'Center form (need to set a width)', INCSUB_SBE_LANG_DOMAIN ); ?>
						</label>
					</div>
					
					<button class="button-primary" id="sbe-insert-shortcode"><?php _e( 'Insert shortcode', INCSUB_SBE_LANG_DOMAIN ); ?></button>
				</div>
				<style>
					#sbe-admin-shortcode {
						margin-top:25px;
					}
					.sbe-admin-shortcode-field {
						margin-bottom:25px;

					}
					.sbe-admin-shortcode-field label {
						margin-right:15px;
						line-height: 2.5;
					}

				</style>
				<?php
				break;
			}
		}

		header('Content-Type: text/html');
		echo ob_get_clean();
		die();
	}
}