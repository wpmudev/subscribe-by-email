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
		return incsub_sbe_get_subscriber( 'subscription_ID', absint( $args['include'] ) );

	$model = incsub_sbe_get_model();
	$_subscribers = $model->get_subscribers( $args );

	$subscribers = array();
	foreach ( $_subscribers as $subscriber ) {
		$subscribers[] = new Subscribe_By_Email_Subscriber( $subscriber );
	}

	return $subscribers;
}

function incsub_sbe_get_subscriber( $key, $value ) {
	return Subscribe_By_Email_Subscriber::get_instance( $key, $value );
}