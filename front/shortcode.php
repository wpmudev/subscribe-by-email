<?php

class Subscribe_By_Email_Shortcode {

	public $errors  = array();

	public function __construct() {
		add_shortcode( 'subscribe-by-email-form', array( $this, 'render_form' ) );
	}

	public function render_form() {
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
		<?php
	}
}