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

		add_action( 'wp_ajax_nopriv_sbe_subscribe_user', array( &$this, 'subscribe_user' ) );
		add_action( 'wp_ajax_sbe_subscribe_user', array( &$this, 'subscribe_user' ) );

		add_action( 'init', array( &$this, 'validate_widget' ) );
	}

	public function enqueue_scripts() {
		//wp_enqueue_script( 'subscribe-by-email-widget-js', INCSUB_SBE_ASSETS_URL . 'js/widget.js', array( 'jquery' ), '20130522' );
		//$sbe_localized = array(
		//	'ajaxurl' => site_url() . '/wp-admin/admin-ajax.php',
		//	'subscription_created' => __( 'Your subscription has been successfully created!', 'subscribe-by-email'),
		//	'already_subscribed' => __( 'You are already subscribed!', 'subscribe-by-email'),
		//	'subscription_cancelled' => __('Your subscription has been successfully canceled!', 'subscribe-by-email'),
		//	'failed_to_cancel_subscription' => __('Failed to cancel your subscription!', 'subscribe-by-email'),
		//	'invalid_email' => __('Invalid e-mail address!', 'subscribe-by-email'),
		//	'default_email' => __('ex: john@hotmail.com', 'subscribe-by-email'),
		//);
	//
		//wp_localize_script( 'subscribe-by-email-widget-js', 'sbe_localized', $sbe_localized );
	}


	public function enqueue_styles() {
		wp_enqueue_style( 'subscribe-by-email-widget-css', INCSUB_SBE_ASSETS_URL . 'css/widget.css', array(), '20130522' );
	}



	public function subscribe_user() {
		
		if ( isset( $_POST['subscription-email'] ) ) {
			$instance = $this->get_settings();

			if ( array_key_exists( $this->number, $instance ) ) {
				$instance = $instance[ $this->number ];

				if ( false !== $instance ) {
					$autopt = $instance['autopt'];

					$email = sanitize_email( $_POST['subscription-email'] );
					if ( ! is_email( $email ) ) {
						echo "MAIL ERROR";
					}
					else {
						$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', $autopt );

						if ( ! $sid ) {
							echo "MAIL ERROR";
							die();
						}

						$model = incsub_sbe_get_model();
						if ( isset( $_POST['widget_meta']['first_name'] ) )
							$model->add_subscriber_meta( $sid, 'first_name', stripslashes_deep( $_POST['widget_meta']['first_name'] ) );

						if ( isset( $_POST['widget_meta']['last_name'] ) )
							$model->add_subscriber_meta( $sid, 'last_name', stripslashes_deep( $_POST['widget_meta']['last_name'] ) );

						if ( isset( $_POST['widget_meta']['address'] ) )
							$model->add_subscriber_meta( $sid, 'address', stripslashes_deep( $_POST['widget_meta']['address'] ) );

						$settings = incsub_sbe_get_settings();
						if ( $settings['get_notifications'] ) {
							$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template( $email );
							$admin_notice->send_email();
						}

						echo "TRUE";
					}
				}
			}
		}
		die();
	}

	public function validate_widget() {
		if ( ! empty( $_POST['action'] ) && 'sbe_subscribe_user' == $_POST['action'] ) {
	    	$subscribed = false;
	    	$this->errors = array();

	    	$nonce = isset( $_POST['sbe_subscribe_nonce'] ) ? $_POST['sbe_subscribe_nonce'] : '';
	    	$instance = $this->get_settings();
	    	if ( wp_verify_nonce( $nonce, 'sbe_widget_subscribe' ) && array_key_exists( $this->number, $instance ) ) {

	    		$instance = $instance[ $this->number ];

	    		// Checking email
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

				if ( empty( $this->errors ) ) {
					$autopt = $instance['autopt'];
					$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', $autopt );

					if ( $sid ) {
						$model = incsub_sbe_get_model();
						foreach ( $fields_to_save as $meta_key => $meta_value ) {
							$model->add_subscriber_meta( $sid, 'first_name', $meta_value );	
						}

						if ( $settings['get_notifications'] ) {
							$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template( $email );
							$admin_notice->send_email();
						}

					}

					$redirect_to = add_query_arg( 'sbe_widget_subscribed', 'true' ) . '#subscribe-by-email-' . $this->number;
					wp_redirect( $redirect_to );
					exit;		
	    		}
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
	        <form method="post" id="subscribe-by-email-subscribe-form">
	        	<?php if ( count( $this->errors ) > 0 ): ?>
	        		<ul class="subscribe-by-email-error">
						<?php foreach ( $this->errors as $error ): ?>
							<li><?php echo $error; ?></li>
						<?php endforeach; ?>
	        		</ul>
	        	<?php endif; ?>
	        	<p>
		        	<?php echo $text; ?>
		        </p>
        		
        		<?php $email = isset( $_POST['subscription-email'] ) ? $_POST['subscription-email'] : ''; ?>
	        	<input type="email" class="subscribe-by-email-field" name="subscription-email" placeholder="<?php _e( 'ex: someone@mydomain.com', INCSUB_SBE_LANG_DOMAIN ); ?>" value="<?php echo $email; ?>"><br/><br/>
	        	<?php if ( ! empty( $extra_fields ) ): ?>
	        		<?php foreach ($extra_fields as $key => $value ): ?>
	        			<?php $current_value = isset( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) ? $_POST[ 'sbe_extra_field_' . $value['slug'] ] : '';  ?>
	        			<?php incsub_sbe_render_extra_field( $value['type'], $value['slug'], $value['title'], $current_value, $value['required'] ); ?><br/><br/>
	        		<?php endforeach; ?>
	        	<?php endif; ?>
	        	<?php if ( $instance['show_count'] ): ?>
	        		<?php $count = $model->get_active_subscribers_count(); ?>
		        	<p>
		        		<?php printf( _n( '%d subscriber', '%d subscribers', $count, INCSUB_SBE_LANG_DOMAIN ), $count ); ?>
		        	</p>
		        <?php endif; ?>

		        <?php wp_nonce_field( 'sbe_widget_subscribe', 'sbe_subscribe_nonce' ); ?>
	        	<input type="hidden" name="action" value="sbe_subscribe_user">
	        	<input type="submit" class="subscribe-by-email-submit" name="submit-subscribe-user" value="<?php echo $button_text; ?>">
	        	<img src="<?php echo INCSUB_SBE_ASSETS_URL . 'images/ajax-loader.gif'; ?>" class="subscribe-by-email-loader"/>
	        </form>
	        
        <?php else: ?>
			<p class="subscribe-by-email-updated"><?php echo $message; ?></p>
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

		$instance['widget_meta'] = $show_meta;

		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 
			'title' => __( 'Subscribe by Email', INCSUB_SBE_LANG_DOMAIN ), 
			'text' => __( 'Completely spam free, opt out any time.', INCSUB_SBE_LANG_DOMAIN ), 
			'button_text' => __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN  ),
			'autopt' => false,
			'show_count' => false,
			'subscribed_placeholder' => __( 'Thank you, your email has been added to the mailing list.', INCSUB_SBE_LANG_DOMAIN ),
			'widget_meta' => array()
		);
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
	<?php
	}

}