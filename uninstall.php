<?php

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
	exit();

global $wpdb;

$subscribers = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type='subscriber'" );
foreach ( $subscribers as $subscriber_id ) {
	wp_delete_post( $subscriber_id, true );
}

$tables = array(
	$wpdb->base_prefix . 'subscriptions_queue',
	$wpdb->prefix . 'subscriptions_meta',
	$wpdb->prefix . 'subscriptions_log_table',
	$wpdb->prefix . 'subscriptions'
);

$options = array(
	'incsub_sbe_settings',
	'incsub_sbe_version'
);

foreach ( $options as $option )
	delete_option( $option );

$network_options = array(
	'incsub_sbe_network_version',
	'incsub_sbe_network_settings'
);
foreach ( $options as $option )
	delete_site_option( $option );



