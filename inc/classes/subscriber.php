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

	public static function get_instance( $sid ) {
		$model = incsub_sbe_get_model();

		$sid = absint( $sid );
		if ( ! $sid )
			return false;

		$_subscriber = wp_cache_get( $sid, 'subscribers' );

		if ( ! $_subscriber ) {
			$_subscriber = $model->get_subscriber( $sid );

			if ( ! $_subscriber )
				return false;

			wp_cache_add( $sid, $_subscriber, 'subscribers' );

		}

		return new self( $_subscriber );
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

	public function get_meta( $meta_key, $default = false ) {
		$model = incsub_sbe_get_model();

		$meta = wp_cache_get( $this->subscription_ID . $meta_key, 'subscribers_meta' );

		if ( ! $meta ) {
			$meta = stripslashes_deep( $model->get_subscriber_meta( $this->subscription_ID, $meta_key ) );

			if ( ! $meta )
				return $default;

			wp_cache_add( $this->subscription_ID . $meta_key, $meta, 'subscribers_meta' );
		}

		return $meta;


	}

	public function get_metas( $slugs ) {

		$model = incsub_sbe_get_model();

		$meta = $model->get_subscriber_meta( $this->subscription_ID, $slugs );

		$results = array();
		foreach ( $meta as $value ) {
			$results[ $value->meta_key ] = stripslashes_deep( $value->meta_value );
		}

		foreach ( $slugs as $slug ) {
			if ( ! array_key_exists( $slug, $results ) ) {
				$results[ $slug ] = '';
			}
		}


		return $results;


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