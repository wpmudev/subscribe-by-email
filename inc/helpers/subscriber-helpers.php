<?php

function incsub_sbe_get_subscribers( $args = array() ) {
	$defaults = array(
		'per_page' => 10,
		's' => '',
		'current_page' => 1,
		'orderby' => 'title',
		'order' => 'ASC',
		'subscription_created_from' => 0,
		'status' => 'all',
		'include' => array()
	);

	$args = wp_parse_args( $args, $defaults );

	$r = array(
		'posts_per_page' => $args['per_page'],
		'offset' => ( $args['current_page'] - 1 ) * $args['per_page'],
		'orderby' => $args['orderby'],
		'order' => $args['order'],
		'post_type' => 'subscriber',
		'post_status' => $args['status'],
		's' => $args['s']
	);

	if ( ! empty( $args['include'] ) )
		$r['post__in'] = $args['include'];

	$query = new WP_Query( $r );
	$posts = $query->posts;

	$subscribers = array();
	foreach ( $posts as $post ) {
		$subscribers[] = new SBE_Subscriber( $post );
	}

	$return = new stdClass;
	$return->subscribers = $subscribers;
	$return->total = absint( $query->found_posts );

	return $return;
}

/**
 * Update a subscriber
 * @param  Integer $id   Subscriber ID
 * @param  array  $args 
	array(
		'email' => 'New email',
		'meta_key1' => 'meta_value1',
		'meta_key2' => 'meta_value2',
		...
	)
 * @return Boolean      True if everything went ok
 */
function incsub_sbe_update_subscriber( $id, $args = array() ) {
	$subscriber = incsub_sbe_get_subscriber( $id );

	if ( ! $subscriber )
		return false;

	if ( empty( $args ) )
		return false;

	$r = array();
	if ( isset( $args['email'] ) ) {
		$email = sanitize_email( $args['email'] );
		unset( $args['email'] );
		if ( is_email( $email ) )
			$r['post_title'] = $email;
	}

	if ( ! empty( $r ) ) {
		$r['ID'] = $subscriber->ID;
		wp_update_post( $r );
	}

	foreach ( $args as $key => $value ) {
		update_post_meta( $subscriber->ID, $key, $value );
	}

	return true;

}

/**
 * Insert a new subscriber
 * 
 * @param  string  $email  Subscriber Email
 * @param  boolean $autopt If a confirmation email should be avoided
 * @param  array   $args   
 	array(
		note => String
		type => String
		meta => Array of key/value metadata
 	)
 * @return Integer          New subscriber ID/False in case of error
 */
function incsub_sbe_insert_subscriber( $email, $autopt = false, $args = array() ) {
	$defaults = array(
		'note' => '',
		'type' => '',
		'meta' => array()
	);

	$args = wp_parse_args( $args, $defaults );

	$subscribe_user = apply_filters( 'sbe_pre_subscribe_user', true, $email, $args['note'], $args['type'], $autopt, $args['meta'] );
	if ( ! $subscribe_user )
		return false;

	// Sanitize email
	$email = sanitize_email( $email );

	if ( ! is_email( $email ) )
		return false;

	// Check if subscriber already exist
	$post = get_page_by_title( $email, OBJECT, 'subscriber' );

	if ( ! empty( $post ) ) {
		incsub_sbe_send_confirmation_email( $post->ID );

		return false;
	}

	$autopt = apply_filters( 'sbe_autopt_subscription', $autopt, $email );

	$postarr = array(
		'post_title' => $email,
		'post_status' => $autopt ? 'publish' : 'pending',
		'post_type' => 'subscriber',
		'post_author' => 0
	);

	$subscriber_id = wp_insert_post( $postarr, true );
	if ( $subscriber_id ) {

		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $meta_key => $meta_value ) {
				add_post_meta( $subscriber_id, $meta_key, $meta_value );
			}
		}

		if ( ! empty( $args['type'] ) )
			add_post_meta( $subscriber_id, 'type', $args['type'] );

		if ( ! empty( $args['note'] ) )
			add_post_meta( $subscriber_id, 'note', $args['note'] );

		$user_key = substr( md5( time() . rand() . $email ), 0, 16 );
		add_post_meta( $subscriber_id, 'key', $user_key );

		if ( ! $autopt ) {
			incsub_sbe_send_confirmation_email( $subscriber_id );
		}
	}
	
	return $subscriber_id;
}

/**
 * Confirm a subscriber Subscription
 * 
 * @param  Integer $sid Subscriber ID
 */
function incsub_sbe_confirm_subscription( $sid ) {
	$subscriber = get_post( $sid );

	if ( ! $subscriber )
		return;

	wp_publish_post( $sid );
}

/**
 * Get a subscriber instance
 * 
 * @param  Instance $sid Subscriber ID
 * @return Object      SBE_Subscriber instance/ False in case of error
 */
function incsub_sbe_get_subscriber( $sid ) {
	if ( is_email( $sid ) ) {
		$post = get_page_by_title( $sid, OBJECT, 'subscriber' );
		if ( ! empty( $post ) )
			$sid = $post->ID;
		else
			return false;
	}
	return SBE_Subscriber::get_instance( $sid );
}

/**
 * Get a subscriber by its unique key (not the same than the ID)
 * @param  String $key Subscriber Key
 * @return Object      SBE_Subscriber instance/ False in case of error
 */
function incsub_sbe_get_subscriber_by_key( $key ) {
	$query = new WP_Query(
        array(
            'post_type' => 'subscriber',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
            	array(
	                'key'     => 'subscription_key',
	                'value'   => $key,
	                'compare' => '='
	               )
            )
        )
    );

    if ( ! empty( $query->posts ) ) {
    	$subscriber = new SBE_Subscriber( $query->posts[0] );
    	return $subscriber;
    }

    return false;
}

/**
 * Get a total count of the current number of subscribers
 * 
 * If $max_id is especified, only confirmed subscribers count will be returned up to that ID
 * 
 * @param  boolean $max_id Max ID to count up to
 * @return Integer          Number of subscribers in Database
 */
function incsub_sbe_get_subscribers_count( $max_id = false ) {
	global $wpdb;
	$query = "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'subscriber'";
	if ( $max_id )
		$query .= $wpdb->prepare( "AND ID <= %d AND post_status = 'publish'", $max_id );

	$count = $wpdb->get_var( $query );
	return absint( $count );
}

/**
 * Cancel and delete a subscriber
 * 
 * @param  Integer $sid Subscriber ID
 * @return Boolean      True if everything went ok
 */
function incsub_sbe_cancel_subscription( $sid ) {
	if ( is_email( $sid ) ) {
		$subscriber = incsub_sbe_get_subscriber( $sid );

		if ( ! $subscriber )
			return false;
		$sid = $subscriber->ID;
	}

	$subscriber = get_post( $sid );
	if ( ! $subscriber )
		return false;

	return wp_delete_post( $sid, true );
}

/**
 * Delete a meta key for all subscribers in Database
 *
 * @param  String $meta_key Meta Key
 */
function sbe_delete_all_subscribers_meta( $meta_key ) {
	$subscribers = incsub_sbe_get_subscribers( array( 'per_page' => -1 ) );
	foreach ( $subscribers->subscribers as $subscriber ) {
		delete_post_meta( $subscriber->ID, $meta_key );
	}
}