<?php

add_action( 'wp_ajax_sbe_upgrade_database_28rc1_step1', 'incsub_sbe_upgrade_28RC1_step1' );
function incsub_sbe_upgrade_28RC1_step1() {

    check_ajax_referer( 'sbe_upgrade_database', 'nonce' );
    $counter = absint( $_POST['counter'] );

    global $wpdb;
    $table = $wpdb->prefix . 'subscriptions';
    $table_meta = $wpdb->prefix . 'subscriptions_meta';

    $total_users = $wpdb->get_var( "SELECT COUNT(subscription_ID) FROM $table" );
    

    $users = $wpdb->get_results( "SELECT * FROM $table LIMIT $counter, 50" );

    if ( empty( $users ) ) {
        delete_option( 'sbe_upgrade_database_28RC1' );
        $data = array(
            'done' => true
        );
    }

    foreach ( $users as $user ) {
    	$autopt = $user->confirmation_flag == 1 ? true : false;
    	$meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_meta WHERE subscription_id = %d", $user->subscription_ID ) );
    	$new_meta = array();
    	foreach ( $meta as $row ) {
    		$new_meta[ $row->meta_key ] = $row->meta_value;
    	}

    	$settings = maybe_unserialize( $user->subscription_settings );
    	$settings = is_array( $settings ) ? $settings : array();
    	foreach ( $settings as $key => $value ) {
    		$new_meta[ $key ] = $value;
    	}

        add_filter( 'sbe_send_confirmation_email', '__return_false' );
    	$sid = Incsub_Subscribe_By_Email::subscribe_user( $user->subscription_email, $user->subscription_note, $user->subscription_type, $autopt );

    	if ( $sid && is_array( $new_meta ) && ! empty( $new_meta ) ) {
    		foreach ( $new_meta as $key => $value ) {
    			update_post_meta( $sid, $key, $value );
    		}
    	}

    	if ( $sid ) {
	    	// The date
	    	$date = date( 'Y-m-d H:i:s', $user->subscription_created );
	    	$date_gmt = get_gmt_from_date( $date );

	    	$wpdb->update(
    			$wpdb->posts,
    			array(
    				'post_date' => $date,
    				'post_date_gmt' => $date_gmt
    			),
    			array( 'ID' => $sid ),
    			array( '%s', '%s' ),
    			array( '%d' )
    		);

	    	$r = array(
    			'ID' => $sid,
    			'post_date' => $date,
    			'post_date_gmt' => $date_gmt
    		);
	    	wp_insert_post( $r );
	    }

	    $data = array(
	        'done' => false,
	        'email' => $user->subscription_email
	    );
    }
    
	if ( ! isset( $_POST['unittest'] ) )
    	wp_send_json_success( $data );
    else
    	return $data;
}

add_action( 'wp_ajax_sbe_upgrade_database_28rc1_step2', 'incsub_sbe_upgrade_28RC1_step2' );
function incsub_sbe_upgrade_28RC1_step2() {
    global $wpdb;

    check_ajax_referer( 'sbe_upgrade_database', 'nonce' );

    $subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
    $max_ID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_type = 'subscriber'" );
    if ( ! $max_ID )
        $max_ID = 0;

    $wpdb->query( "UPDATE $subscriptions_log_table SET max_email_ID = $max_ID" );

    if ( ! isset( $_POST['unittest'] ) )
        wp_send_json_success( array( 'done' => true ) );
    else
        return true;
}