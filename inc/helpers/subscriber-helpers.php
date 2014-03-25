<?php

function incsub_sbe_get_subscribers( $args = array() ) {
	$defaults = array(
		'per_page' => 10,
		's' => '',
		'current_page' => 1,
		'sort' => 'subscription_ID',
		'sort_type' => 'ASC',
		'subscription_created_from' => 0,
		'confirmed' => false,
		'include' => array()
	);

	$args = wp_parse_args( $args, $defaults );

	if ( is_array( $args['include'] ) && count( $args['include'] ) === 1 )
		return incsub_sbe_get_subscriber( $args['include'] );

	$model = incsub_sbe_get_model();
	$_subscribers = $model->get_subscribers( $args );

	$subscribers = array();
	foreach ( $_subscribers as $subscriber ) {
		$subscribers[] = new Subscribe_By_Email_Subscriber( $subscriber );
	}

	return $subscribers;
}

function incsub_sbe_get_subscriber( $sid ) {
	if ( is_email( $sid ) ) {
		$model = incsub_sbe_get_model();
		$sid = $model->get_subscriber_id( $sid );
	}
	return Subscribe_By_Email_Subscriber::get_instance( $sid );
}

function incsub_sbe_get_subscribes_count() {
	$model = incsub_sbe_get_model();
	return $model->get_all_subscribers( true );
}