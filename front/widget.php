<?php



class Incsub_Subscribe_By_Email_Widget extends WP_Widget {

	private $errors;
	private $success;

	/**
	 * Widget setup.
	 */
	function Incsub_Subscribe_By_Email_Widget() {

		$this->errors = array();
		$this->success = false;

		/* Widget settings. */
		$widget_ops = array( 
			'classname' => 'subscribe-by-email' , 
			'description' => __('This widget allows visitors to subscribe to receive email updates when a new post is made to your blog.', INCSUB_SBE_LANG_DOMAIN ) 
		);

		/* Create the widget. */
		parent::WP_Widget( 'subscribe-by-email' , __( 'Subscribe by Email', INCSUB_SBE_LANG_DOMAIN ), $widget_ops );

		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		
		add_action( 'wp_ajax_sbe_widget_subscribe_user', array( &$this, 'validate_widget' ) );
		add_action( 'wp_ajax_nopriv_sbe_widget_subscribe_user', array( &$this, 'validate_widget' ) );

		add_action( 'init', array( &$this, 'validate_widget' ) );
	}

	public function enqueue_scripts() {

		wp_enqueue_script( 'sbe-widget-js', INCSUB_SBE_ASSETS_URL . 'js/widget.js', array( 'jquery' ) );

		$l10n = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( "sbe_widget_subscribe" )
		);
		wp_localize_script( 'sbe-widget-js', 'sbe_widget_captions', $l10n );
	}


	public function enqueue_styles() {
		$widget_stylesheet = apply_filters( 'sbe_widget_stylesheet_uri', INCSUB_SBE_ASSETS_URL . 'css/widget/widget.css', array(), '20140113' );
		$deps = apply_filters( 'sbe_widget_stylesheet_dependencies', array() );
		wp_enqueue_style( 'subscribe-by-email-widget-css', $widget_stylesheet, $deps, '20130522' );
	}



	public function validate_widget() {

		if ( ! empty( $_POST['action'] ) && 'sbe_widget_subscribe_user' == $_POST['action'] ) {

			$input = $_POST;
			$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
			$instance = $this->get_settings();

			if ( ! array_key_exists( $this->number, $instance ) )
				return false;

			if ( ! $doing_ajax ) {
				$nonce = isset( $input['sbe_subscribe_nonce'] ) ? $input['sbe_subscribe_nonce'] : '';
	    		if ( ! wp_verify_nonce( $nonce, 'sbe_widget_subscribe' ) )
	    			return false;
			}
			else {
				check_ajax_referer( 'sbe_widget_subscribe', 'nonce' );
			}


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

			$this->errors = apply_filters( 'sbe_widget_validate_form', $errors, $email, $fields_to_save );
    		
			if ( empty( $this->errors ) ) {
				
    			$instance = $instance[ $this->number ];
				$autopt = $instance['autopt'];

				$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', $autopt, $fields_to_save );

				if ( $sid && $settings['get_notifications'] ) {
					require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
					$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template( $email );
					$admin_notice->send_email();
				}

				if ( ! $doing_ajax ) {
					$redirect_to = add_query_arg( 'sbe_widget_subscribed', 'true' ) . '#subscribe-by-email-' . $this->number;
					$redirect_to = apply_filters( 'sbe_widget_redirect_on_subscribe', $redirect_to );
					wp_redirect( $redirect_to );
					exit;		
				}
				else {
					$text = $instance['subscribed_placeholder'];
					wp_send_json_success( array( 'message' => $text ) );
				}
				
    		}
    		elseif ( ! empty( $this->errors ) && $doing_ajax ) {
    			wp_send_json_error( $this->errors );
    		}
	    	
	    }
	}

	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		extract( $args );

        $title = apply_filters( 'widget_title', $instance['title'] );
        $text = $instance['text'];
		$button_text = ! empty( $instance['button_text'] ) ? $instance['button_text'] : __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN );

	    echo $before_widget;
	     
	    if ( $title )
	     	echo $before_title . $title . $after_title; 

	    $message = $instance['subscribed_placeholder'];
	    $model = incsub_sbe_get_model();

	    $settings = incsub_sbe_get_settings();
	    $extra_fields = empty( $settings['extra_fields'] ) ? array() : $settings['extra_fields'];

	    if ( ! isset( $_GET['sbe_widget_subscribed'] ) ): ?>
	        <form method="post" class="sbe-widget-subscribe-form" id="sbe-widget-subscribe-form-<?php echo $this->number; ?>">
	        	<?php if ( count( $this->errors ) > 0 ): ?>
	        		<ul class="sbe-widget-error">
						<?php foreach ( $this->errors as $error ): ?>
							<li><?php echo $error; ?></li>
						<?php endforeach; ?>
	        		</ul>
	        	<?php endif; ?>
	        	<p class="sbe-widget-top-text">
		        	<?php echo $text; ?>
		        </p>
        		
        		<?php $email = isset( $_POST['subscription-email'] ) ? $_POST['subscription-email'] : ''; ?>
        		<div class="sbe-widget-form-field-title"><?php _e( 'Email address', INCSUB_SBE_LANG_DOMAIN ); ?></div>
	        	<input type="email" class="sbe-widget-form-field sbe-widget-email-field sbe-form-field"  name="subscription-email" placeholder="<?php _e( 'ex: someone@mydomain.com', INCSUB_SBE_LANG_DOMAIN ); ?>" value="<?php echo $email; ?>"><br/>

	        	<?php if ( ! empty( $extra_fields ) ): ?>
	        		<?php foreach ( $extra_fields as $key => $value ): ?>

	        			<?php if ( 'checkbox' !== $value['type'] ): ?>
							<div class="sbe-widget-form-field-title"><?php echo $value['title']; ?> <?php echo $value['required'] ? '<span class="sbe-widget-required">(*)</span>' : ''; ?></div>
						<?php endif; ?>

	        			<?php 
	        				$current_value = isset( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) ? $_POST[ 'sbe_extra_field_' . $value['slug'] ] : '';
							$atts = array(
								'placeholder' => '',
								'name' => 'sbe_extra_field_' . $value['slug'],
								'class' => 'sbe-widget-form-field sbe-widget-' . $value['slug'] . '-field',
							);
						?>

						<?php incsub_sbe_render_extra_field( $value['type'], $value['slug'], $value['title'], $current_value, $atts ); ?>
						<?php if ( 'checkbox' === $value['type'] ): ?>
							<?php echo $value['required'] ? '<span class="sbe-widget-required">(*)</span>' : ''; ?>
						<?php endif; ?>
						<br/>

	        		<?php endforeach; ?>
	        	<?php endif; ?>

	        	<?php do_action( 'sbe_widget_form_fields' ); ?>

		        <?php wp_nonce_field( 'sbe_widget_subscribe', 'sbe_subscribe_nonce' ); ?>
	        	<input type="hidden" class="sbe-widget-form-field sbe-form-field" name="action" value="sbe_widget_subscribe_user">
	        	<div class="sbe-widget-form-submit-container">
	        		<span class="sbe-spinner"></span>
	        		<input type="submit" class="sbe-widget-form-submit" name="submit-subscribe-user" value="<?php echo $button_text; ?>">
	        	</div>

	        	<?php if ( $instance['show_count'] ): ?>
	        		<?php $count = $model->get_active_subscribers_count(); ?>
		        	<p class="sbe-widget-subscribers-count">
		        		<?php printf( _n( '%d subscriber', '%d subscribers', $count, INCSUB_SBE_LANG_DOMAIN ), $count ); ?>
		        	</p>
		        <?php endif; ?>
	        </form>
	        <?php if ( ! empty( $instance['css'] ) ): ?>
	        	<style>
					<?php echo esc_html( $instance['css'] ); ?>
	        	</style>
	    	<?php endif; ?>

	        
        <?php else: ?>
			<p class="sbe-widget-updated"><?php echo $message; ?></p>
    	<?php endif;
		echo $after_widget;
	}

	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['text'] = sanitize_text_field( $new_instance['text'] );
		$instance['button_text'] = sanitize_text_field( $new_instance['button_text'] );
		$instance['autopt'] = ! empty( $new_instance['autopt'] ) ? true : false;
		$instance['show_count'] = ! empty( $new_instance['show_count'] ) ? true : false;
		$instance['subscribed_placeholder'] = sanitize_text_field( $new_instance['subscribed_placeholder'] );
		$instance['css'] = $new_instance['css'];

		$instance = apply_filters( 'sbe_widget_validate_admin_form', $instance, $new_instance, $old_instance );

		return $instance;
	}

	private function get_default_settings() {
		return array( 
			'title' => __( 'Subscribe by Email', INCSUB_SBE_LANG_DOMAIN ), 
			'text' => __( 'Completely spam free, opt out any time.', INCSUB_SBE_LANG_DOMAIN ), 
			'button_text' => __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN  ),
			'autopt' => false,
			'show_count' => false,
			'subscribed_placeholder' => __( 'Thank you, your email has been added to the mailing list.', INCSUB_SBE_LANG_DOMAIN ),
			'css' => ''
		);
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		$defaults = $this->get_default_settings();
		/* Set up some default widget settings. */
		

		$defaults = apply_filters( 'sbe_widget_admin_form_default_values', $defaults, $instance );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', INCSUB_SBE_LANG_DOMAIN ); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'text' ); ?>"><?php _e('Text:', INCSUB_SBE_LANG_DOMAIN ); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'text' ); ?>" name="<?php echo $this->get_field_name( 'text' ); ?>" value="<?php echo esc_attr( $instance['text'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'button_text' ); ?>"><?php _e('Subscribe button text:', INCSUB_SBE_LANG_DOMAIN); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'button_text' ); ?>" name="<?php echo $this->get_field_name( 'button_text' ); ?>" value="<?php echo esc_attr( $instance['button_text'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'subscribed_placeholder' ); ?>"><?php _e( 'Text displayed when a user subscribes:', INCSUB_SBE_LANG_DOMAIN); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'subscribed_placeholder' ); ?>" name="<?php echo $this->get_field_name( 'subscribed_placeholder' ); ?>" value="<?php echo esc_attr( $instance['subscribed_placeholder'] ); ?>" />
		</p>
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'show_count' ); ?>" name="<?php echo $this->get_field_name( 'show_count' ); ?>" value="1" <?php checked( $instance['show_count'] ); ?> /> 
			<label for="<?php echo $this->get_field_id( 'show_count' ); ?>"><?php _e( 'Show number of subscribers', INCSUB_SBE_LANG_DOMAIN ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'autopt' ); ?>" name="<?php echo $this->get_field_name( 'autopt' ); ?>" value="1" <?php checked( $instance['autopt'] ); ?> /> 
			<label for="<?php echo $this->get_field_id( 'autopt' ); ?>"><?php _e('Auto-opt In (it will not send a confirmation email to the user)', INCSUB_SBE_LANG_DOMAIN ); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'css' ); ?>"><?php _e( 'Custom CSS', INCSUB_SBE_LANG_DOMAIN ); ?></label><br/>
			<textarea rows="10" cols="30" id="<?php echo $this->get_field_id( 'css' ); ?>" name="<?php echo $this->get_field_name( 'css' ); ?>" ><?php echo esc_textarea( ( $instance['css'] ) ); ?></textarea>
			
		</p>
		<?php do_action( 'sbe_widget_admin_form_fields', $this, $instance ); ?>

	<?php
	}

}