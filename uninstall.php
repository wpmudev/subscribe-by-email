<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

return;
delete_option( 'incsub_sbe_settings' );
delete_option( 'incsub_sbe_network_settings' );
delete_option( 'incsub_sbe_version' );
delete_option( 'incsub_sbe_version' );
delete_option( 'sbe_upgrade_281' );
delete_option( 'incsub_sbe_network_version' );
delete_option( 'next_week_scheduled' );
delete_option( 'next_day_scheduled' );

delete_site_option( 'incsub_sbe_network_settings' ); 
delete_site_option( 'incsub_sbe_network_version' ); 

global $wpdb;

$tables = array(
	$wpdb->prefix . 'subscriptions_log_table',
	$wpdb->base_prefix . 'subscriptions_queue'
);

foreach ( $tables as $table )
	$wpdb->query( "DROP TABLE $table" );

