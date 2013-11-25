<?php

class Subscribe_By_Email_Subscriber {

	private $subscription_ID;
	private $subscription_email;
	private $confirmation_flag;
	private $subscription_type;
	private $subscription_created;
	private $subscription_note;
	private $user_key;
	private $post_types = '';

	public static function get_instance( $key, $value ) {
		$model = incsub_sbe_get_model();

		$subscriber = false;
		if ( $key == 'subscription_email' ) {
			$subscriber_id = $model->get_subscriber_id( $value );
			if ( $subscriber_id )
				$subscriber = $model->get_subscriber( $subscriber_id );
		}
		elseif ( $key == 'user_key' ) {
			$subscriber = $model->get_subscriber_by_key( $value );
		}
		elseif ( $key == 'subscription_ID' ) {
			$subscriber = $model->get_subscriber( $value );	
		}
		else {
			return false;
		}

		if ( empty( $subscriber ) )
			return false;

		return new self( $subscriber );
	}

	public function __construct( $subscriber ) {

		foreach ( get_object_vars( $subscriber ) as $key => $value ) {
			if ( 'subscription_settings' == $key ) {
				$settings = maybe_unserialize( $value );
				$this->post_types = isset( $settings['post_types'] ) ? maybe_unserialize( $settings['post_types'] ) : false;
			}
			else {
				$this->$key = $value;
			}
		}
	}

	public function get_meta( $meta_key ) {
		$model = incsub_sbe_get_model();
		return stripslashes_deep( $model->get_subscriber_meta( $this->subscription_ID, $meta_key ) );
	}

	public function get_subscription_ID() {
		return $this->subscription_ID;
	}
	public function get_subscription_email() {
		return $this->subscription_email;
	}
	public function get_confirmation_flag() {
		return $this->confirmation_flag;
	}
	public function get_subscription_type() {
		return stripslashes_deep( $this->subscription_type );
	}
	public function get_subscription_created( $type = 'timestamp' ) {
		if ( 'date' == $type )
			return date_i18n( get_option( 'date_format' ), $this->subscription_created );

		return $this->subscription_created;
	}
	public function get_subscription_note() {
		return stripslashes_deep( $this->subscription_note );
	}
	public function get_user_key() {
		return $this->user_key;
	}
	public function get_post_types() {
		return $this->post_types;
	}

}