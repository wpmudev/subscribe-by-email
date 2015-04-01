<?php

class SBE_Subscriber {

	public $ID = 0;
	public $subscription_email = '';
	public $subscription_created = '';
	public $post = null;

	public static function get_instance( $sid ) {
		$sid = absint( $sid );
		if ( ! $sid )
			return false;


		$_subscriber = get_post( $sid );

		if ( ! $_subscriber )
			return false;


		return new self( $_subscriber );
	}

	public function __construct( $subscriber ) {

		if ( ! isset( $subscriber->ID ) )
			return;

		$this->ID = $subscriber->ID;
		$this->subscription_email = $subscriber->post_title;
		$this->subscription_created = date_i18n( get_option( 'date_format' ), strtotime( $subscriber->post_date ) );

		// WP_Post object related
		$this->post = get_post( $this->ID );
		
	}

	public function __get( $name ) {

		if ( $name === 'subscription_type' ) {
			$type = $this->get_meta( 'type', '' );
			return $type ? $type : '';
		}

		if ( $name === 'subscription_note' ) {
			$note = $this->get_meta( 'note', '' );
			return $note ? $note : '';
		}

		if ( $name === 'subscription_post_types' ) {
			$post_types = get_post_meta( $this->ID, 'subscription_post_types', true );

			if ( is_array( $post_types ) )
				return $post_types;
			else
				return false;
		}

		if ( $name === 'subscription_key' ) {
			$key = $this->get_meta( 'subscription_key', '' );
			if ( empty( $key ) ) {
				$key = substr( md5( time() . rand() . $this->subscription_email ), 0, 16 );
				update_post_meta( $this->ID, 'subscription_key', $key );
			}
			return $key;
		}

		return $this->get_meta( $name );

	}

	public function is_confirmed() {
		return get_post_status( $this->ID ) == 'publish' ? true : false;
	}

	public function get_meta( $meta_key, $default = false ) {
		$meta = get_post_meta( $this->ID, $meta_key, true );

		$meta = apply_filters( 'sbe_get_subscriber_meta', $meta, $this->ID, $meta_key );

		if ( ! $meta )
			return $default;

		return $meta;


	}

	public function set_post_types( $post_types ) {
		if ( false === $post_types ) {
			$post_types = false;
		}
		else {
			$current_post_types = $this->subscription_post_types;
			$site_post_types = incsub_sbe_get_subscriptions_post_types();
			$post_types = array_intersect( $site_post_types, $post_types );
		}

		update_post_meta( $this->ID, 'subscription_post_types', $post_types );
	}


}