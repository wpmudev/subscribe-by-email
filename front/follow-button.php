<?php

class Incsub_Subscribe_By_Email_Follow_Button {

	private $settings;
	private $errors = array();

	public function __construct( $settings ) {
		add_action( 'wp_footer', array( &$this,'render_follow_button' ) );
		add_action( 'wp_enqueue_scripts', array( &$this,'add_styles' ) );
		add_action( 'template_redirect', array( &$this, 'validate_form' ) );
		$this->settings = $settings;
	}

	public function add_styles() {
		$settings = incsub_sbe_get_settings();
		$schema = $settings['follow_button_schema'];

		$follow_stylesheet = apply_filters( 'sbe_follow_button_stylesheet_uri', INCSUB_SBE_ASSETS_URL . '/css/follow-button/follow-button-' . $schema . '.css' );
		$deps = apply_filters( 'sbe_follow_button_stylesheet_dependants', array() );

		wp_enqueue_style( 'follow-button-styles', $follow_stylesheet, $deps, '20131129' );
		wp_enqueue_style( 'follow-button-general-styles', INCSUB_SBE_ASSETS_URL . '/css/follow-button/follow-button.css', array(), '20131129' );
		wp_enqueue_script( 'follow-button-scripts', INCSUB_SBE_ASSETS_URL . '/js/follow-button.js', array( 'jquery' ) );
	}

	public function validate_form() {
		if ( isset( $_POST['action'] ) && 'sbe_follow_subscribe_user' == $_POST['action'] ) {

			$nonce = isset( $_POST['sbe_subscribe_nonce'] ) ? $_POST['sbe_subscribe_nonce'] : '';
			if ( ! wp_verify_nonce( $nonce, 'sbe_follow_subscribe' ) ) 
				return;

			$email = sanitize_email( $_POST['subscription-email'] );
			if ( ! is_email( $email ) )
				$this->errors[]  = __( 'Invalid e-mail address', 'subscribe-by-email' );

			// Checking extra fields
			$settings = incsub_sbe_get_settings();
			$extra_fields = $settings['extra_fields'];

			// Here we'll save the fields and their values
			$fields_to_save = array();
			foreach ( $extra_fields as $extra_field ) {
				$required = $extra_field['required'];

				// Value of the field sent
				$field_value = isset( $_POST['sbe_extra_field_' . $extra_field['slug'] ] ) ?  $_POST['sbe_extra_field_' . $extra_field['slug'] ] : '';
				$new_value = incsub_sbe_validate_extra_field( $extra_field['type'], $field_value );

				if ( $required && empty( $new_value ) ) {
					// Field is empty and is required
					$this->errors[] = sprintf( __( '%s is a mandatory field.', INCSUB_SBE_LANG_DOMAIN ), $extra_field['title'] );
				}
				else {
					// Field is ok
					$fields_to_save[ $extra_field['slug'] ] = $new_value;
				}
			}

			$this->errors = apply_filters( 'sbe_follow_button_validate_form', $this->errors, $email, $fields_to_save );

			if ( empty( $this->errors ) ) {
				$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), __( 'Follow Button', INCSUB_SBE_LANG_DOMAIN ), false, $fields_to_save );
				$redirect_to = add_query_arg( 'sbe-followsubs', 'true' ) . '#sbe-follow';
				wp_redirect( $redirect_to );
				exit;		
    		}

		}
	}

	public function render_follow_button() {
		$is_opened = count( $this->errors ) > 0 || ( isset( $_GET['sbe-followsubs'] ) && isset( $_GET['sbe-followsubs'] ) == 'true' );
		
		$settings = incsub_sbe_get_settings();
		$style = 'style="' . $settings['follow_button_position'] . ':-1500px"';
	    $extra_fields = empty( $settings['extra_fields'] ) ? array() : $settings['extra_fields'];
		?>
			<div id="sbe-follow" <?php echo $style; ?> class="<?php echo $is_opened ? 'sbe-follow-opened' : ''; ?>">
				<a aria-hidden="true" class="sbe-follow-link" href="#sbe-follow-wrap"> <span><?php _e( 'Follow', INCSUB_SBE_LANG_DOMAIN ); ?></span></a>
				<div id="sbe-follow-wrap">

					<?php if ( isset( $_GET['sbe-followsubs'] ) && 'true' == $_GET['sbe-followsubs'] ): ?>

						<p class="sbe-follow-updated"><?php _e( 'Thank you! A confirmation email is on the way...', INCSUB_SBE_LANG_DOMAIN ); ?></p>

					<?php else: ?>

						<h2><?php _e( 'Follow this blog', INCSUB_SBE_LANG_DOMAIN ); ?></h2>

						<form action="" class="sbe-follow-subscribe-form" id="sbe-follow-subscribe-form" method="post">

							<?php if ( count( $this->errors ) > 0 ): ?>
				        		<ul class="sbe-follow-error">
									<?php foreach ( $this->errors as $error ): ?>
										<li><?php echo $error; ?></li>
									<?php endforeach; ?>
				        		</ul>
				        	<?php endif; ?>
							
							<?php if ( 'inmediately' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get every new post delivered right to your inbox.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php elseif ( 'weekly' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get a weekly email of all new posts.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php elseif ( 'daily' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get a daily email of all new posts.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php endif; ?>
							
							<?php $email = isset( $_POST['subscription-email'] ) ? $_POST['subscription-email'] : ''; ?>
							<div aria-hidden="true" class="sbe-follow-form-field-title"><?php _e( 'Email address', INCSUB_SBE_LANG_DOMAIN ); ?></div><label class="sbe-follow-screen-reader-text" for="sbe-follow-screen-reader-label"><?php _e( 'Email address', INCSUB_SBE_LANG_DOMAIN ); ?></label>
	        				<input type="email" id="sbe-follow-screen-reader-label" class="sbe-follow-form-field sbe-follow-email-field" name="subscription-email" placeholder="<?php _e( 'ex: someone@mydomain.com', INCSUB_SBE_LANG_DOMAIN ); ?>" value="<?php echo $email; ?>" required><br/>
							
							<?php if ( ! empty( $extra_fields ) ): ?>
				        		<?php foreach ( $extra_fields as $key => $value ): ?>

				        			<?php if ( 'checkbox' !== $value['type'] ): ?>
										<div class="sbe-follow-form-field-title"><?php echo $value['title']; ?> <?php echo $value['required'] ? '<span class="sbe-follow-required">(*)</span>' : ''; ?></div>
									<?php endif; ?>

				        			<?php 
				        				$current_value = isset( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) ? $_POST[ 'sbe_extra_field_' . $value['slug'] ] : '';
										$atts = array(
											'placeholder' => '',
											'name' => 'sbe_extra_field_' . $value['slug'],
											'class' => 'sbe-follow-form-field sbe-follow-' . $value['slug'] . '-field',
										);
									?>

									<?php incsub_sbe_render_extra_field( $value['type'], $value['slug'], $value['title'], $current_value, $atts ); ?>
									<?php if ( 'checkbox' === $value['type'] ): ?>
										<?php echo $value['required'] ? '<span class="sbe-follow-required">(*)</span>' : ''; ?>
									<?php endif; ?>
									<br/>

				        		<?php endforeach; ?>
				        	<?php endif; ?>

				        	<?php do_action( 'sbe_follow_button_form_fields' ); ?>
							
							<?php wp_nonce_field( 'sbe_follow_subscribe', 'sbe_subscribe_nonce' ); ?>
							<input type="hidden" name="action" value="sbe_follow_subscribe_user">
							
							<div class="sbe-follow-form-submit-container">
								<input type="submit" class="sbe-follow-form-submit" value="<?php _e( 'Subscribe me!', INCSUB_SBE_LANG_DOMAIN ); ?>">
							</div>
						</form>

					<?php endif; ?>

				</div>
			</div>
			<script>
				jQuery(window).load(function() {
					sbe_follow_button.init( jQuery('#sbe-follow'), jQuery('#sbe-follow-wrap'), '<?php echo $settings["follow_button_position"]; ?>' );
				});
			</script>
			<style>
				/** Main wrap **/
				#sbe-follow {					
					<?php if ( $settings['follow_button_position'] == 'bottom' ): ?>
						margin-right: 10%;
						right: 0px;
						bottom:0px;
					<?php elseif ( $settings['follow_button_position'] == 'left' ): ?>
						left:0px;
						top:10%;
					<?php elseif ( $settings['follow_button_position'] == 'right' ): ?>
						right:0px;
						top:10%;
						margin-right:0;
					<?php endif; ?>
				}
				<?php if ( $settings['follow_button_position'] == 'left' ): ?>
					#sbe-follow .sbe-follow-link {
						float: right;
						top: 65px;
						left: 52px;
					}
				<?php elseif ( $settings['follow_button_position'] == 'right' ): ?>
					#sbe-follow .sbe-follow-link {
						float: left;
						top: 65px;
						right: 52px;
					}
					#sbe-follow-wrap {
						float:right;
					}
				<?php endif; ?>
			</style>
		<?php
	}
}