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
	$return->total = $query->found_posts;

	return $return;
}

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

function incsub_sbe_insert_subscriber( $email, $autopt = false, $args = array(), $update = false ) {
	$defaults = array(
		'note' => '',
		'type' => '',
		'meta' => array()
	);

	$args = wp_parse_args( $args, $defaults );

	$subscribe_user = apply_filters( 'sbe_pre_subscribe_user', true, $email, $args['note'], $args['type'], $autopt, $args['meta'] );

	// Sanitize email
	$email = sanitize_email( $email );

	// Check if subscriber already exist
	$post = get_page_by_title( $email, OBJECT, 'subscriber' );

	if ( ! empty( $post ) ) {
		if ( $post->post_status != 'publish' ) {
			Incsub_Subscribe_By_Email::send_confirmation_mail( $post->ID );
		}
		return false;
	}

	
	if ( ! is_email( $email ) )
		return false;

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
			Incsub_Subscribe_By_Email::send_confirmation_mail( $subscriber_id );
		}
	}
	
	return $subscriber_id;
}

function incsub_sbe_confirm_subscription( $sid ) {
	$subscriber = get_post( $sid );

	if ( ! $subscriber )
		return;

	wp_publish_post( $sid );
}

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

function incsub_sbe_get_subscriber_by_key( $key ) {
	$query = new WP_Query(
        array(
            'post_type' => 'subscriber',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
            	array(
	                'key'     => 'key',
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

function incsub_sbe_get_subscribers_count( $max_id = false ) {
	global $wpdb;
	$query = "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'subscriber'";
	if ( $max_id )
		$query .= $wpdb->prepare( "AND ID <= %d AND post_status = 'publish'", $max_id );

	$count = $wpdb->get_var( $query );
	return absint( $count );
}

function incsub_sbe_cancel_subscription( $sid ) {
	$subscriber = get_post( $sid );
	if ( ! $subscriber )
		return;

	wp_delete_post( $sid, true );
}