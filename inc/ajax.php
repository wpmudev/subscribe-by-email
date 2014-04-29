<?php

add_action( 'wp_ajax_sbe_upgrade_database_28rc1', 'incsub_sbe_upgrade_28RC1' );
function incsub_sbe_upgrade_28RC1() {
    $counter = absint( $_POST['counter'] );

    global $wpdb;
    $table = $wpdb->prefix . 'subscriptions';
    $table_meta = $wpdb->prefix . 'subscriptions_meta';

    $total_users = $wpdb->get_var( "SELECT COUNT(subscription_ID) FROM $table" );
    

    $user = $wpdb->get_row( "SELECT * FROM $table LIMIT $counter, 1" );


    if ( ! $user ) {
    	delete_option( 'sbe_upgrade_database' );
    	$data = array(
    		'done' => true
    	);
    }
    else {
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

    // UPDATE MAIL LOGS IDS !!!!
    
	if ( ! isset( $_POST['unittest'] ) )
    	wp_send_json_success( $data );
    else
    	return $data;
}