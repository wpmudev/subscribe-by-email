<?php


class Incsub_Subscribe_By_Email_Widget extends WP_Widget {

	private $error;
	private $success;

	/**
	 * Widget setup.
	 */
	function Incsub_Subscribe_By_Email_Widget() {

		$this->error = false;
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
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'subscribe-by-email-widget-js', INCSUB_SBE_ASSETS_URL . 'js/widget.js', array( 'jquery' ), '20130522' );
		$sbe_localized = array(
			'ajaxurl' => site_url() . '/wp-admin/admin-ajax.php',
			'subscription_created' => __( 'Your subscription has been successfully created!', 'subscribe-by-email'),
			'already_subscribed' => __( 'You are already subscribed!', 'subscribe-by-email'),
			'subscription_cancelled' => __('Your subscription has been successfully canceled!', 'subscribe-by-email'),
			'failed_to_cancel_subscription' => __('Failed to cancel your subscription!', 'subscribe-by-email'),
			'invalid_email' => __('Invalid e-mail address!', 'subscribe-by-email'),
			'default_email' => __('ex: john@hotmail.com', 'subscribe-by-email'),
		);
	
		wp_localize_script( 'subscribe-by-email-widget-js', 'sbe_localized', $sbe_localized );
	}


	public function enqueue_styles() {
		wp_enqueue_style( 'subscribe-by-email-widget-css', INCSUB_SBE_ASSETS_URL . 'css/widget.css', array(), '20130522' );
	}



	public function subscribe_user() {
		
		if ( ! empty( $_POST['subscription-email'] ) ) {
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
						Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), 'Instant', $autopt );
						echo "TRUE";
					}
				}
			}
		}
		die();
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
	    
	    $message = $instance['autopt'] ? __( 'Thank you, your email has been added to the mailing.', INCSUB_SBE_LANG_DOMAIN ) : __( 'Thank you, your email will be added to the mailing list once you click on the link in the confirmation email.', INCSUB_SBE_LANG_DOMAIN );

	    ?>
	        <form method="post" id="subscribe-by-email-subscribe-form">
	        	<p>
		        	<?php echo $text; ?>
		        </p>
	        	<p class="subscribe-by-email-error"><?php _e( 'Please, insert a valid email.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
        		<p class="subscribe-by-email-updated"><?php echo $message; ?></p>
	        	<input type="text" class="subscribe-by-email-field" name="subscription-email" placeholder="<?php _e( 'ex: someone@mydomain.com', INCSUB_SBE_LANG_DOMAIN ); ?>">
	        	<input type="hidden" name="action" value="sbe_subscribe_user">
	        	<input type="submit" class="subscribe-by-email-submit" name="submit-subscribe-user" value="<?php echo $button_text; ?>">
	        	<img src="<?php echo INCSUB_SBE_ASSETS_URL . 'images/ajax-loader.gif'; ?>" class="subscribe-by-email-loader"/>
	        </form>
	        
        <?php
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
			'autopt' => false
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
			<input type="checkbox" id="<?php echo $this->get_field_id( 'autopt' ); ?>" name="<?php echo $this->get_field_name( 'autopt' ); ?>" value="1" <?php checked( $instance['autopt'] ); ?> /> 
			<label for="<?php echo $this->get_field_id( 'autopt' ); ?>"><?php _e('Auto-opt In (it will not send a confirmation email to the user)', INCSUB_SBE_LANG_DOMAIN ); ?></label>
		</p>
	<?php
	}

}