<?php


if ( version_compare( $current_version, '1.0', '<=' ) ) {
	$new_settings = array();

	$new_settings['auto-subscribe'] = false;

	$defaults = incsub_sbe_get_default_settings();
	$new_settings['from_email'] = get_option( 'subscribe_by_email_instant_notification_from', $defaults['from_email'] );

	$new_settings['subject'] = get_option( 'subscribe_by_email_instant_notification_subject', $defaults['subject'] );

	$new_settings['subject'] = str_replace( 'BLOGNAME', get_bloginfo( 'name' ), $new_settings['subject'] );
	$new_settings['subject'] = str_replace( 'EXCERPT', '', $new_settings['subject'] );
	$new_settings['subject'] = str_replace( 'POST_TITLE', '', $new_settings['subject'] );

	$model = Incsub_Subscribe_By_Email_Model::get_instance();
	$model->create_squema();

	incsub_sbe_update_settings( $new_settings );

}


if ( version_compare( $current_version, '2.4.3', '<' ) ) {
	$settings = incsub_sbe_get_settings();
	if ( isset( $settings['taxonomies']['post']['categories'] ) ) {
		$categories = $settings['taxonomies']['post']['categories'];
		$settings['taxonomies']['post']['category'] = $categories;
		unset( $settings['taxonomies']['post']['categories'] );
		incsub_sbe_update_settings( $settings );
	}


}

if ( version_compare( $current_version, '2.4.4', '<' ) ) {
	$settings = incsub_sbe_get_settings();

	delete_transient( self::$freq_daily_transient_slug );
	delete_transient( self::$freq_weekly_transient_slug );
	if ( 'weekly' == $settings['frequency'] ) {
		self::set_next_week_schedule_time( $settings['day_of_week'], $settings['time'] );
	}
	
	if ( 'daily' == $settings['frequency'] ) {
		self::set_next_day_schedule_time( $settings['time'] );
	}


}

if ( version_compare( $current_version, '2.4.7b', '<' ) ) {

	$model = Incsub_Subscribe_By_Email_Model::get_instance();
	$model->create_squema();

	$model->upgrade_247b();


}

if ( version_compare( $current_version, '2.4.7RC1', '<' ) ) {

	$settings = incsub_sbe_get_settings();

	$post_types = $settings['post_types'];
	$taxonomies = $settings['taxonomies'];

	$new_taxonomies = $taxonomies;
	foreach ( $post_types as $post_type ) {
		$post_type_taxonomies = get_object_taxonomies( $post_type );
		if ( ! array_key_exists( $post_type, $taxonomies ) && ! empty( $post_type_taxonomies ) ) {
			foreach ( $post_type_taxonomies as $taxonomy_slug ) {
				$taxonomy = get_taxonomy( $taxonomy_slug );

				if ( $taxonomy->hierarchical ) {
					$new_taxonomies[ $post_type ][ $taxonomy_slug ] = array( 'all' );
				}
			}
		}
	}

	$settings['taxonomies'] = $new_taxonomies;
	incsub_sbe_update_settings( $settings );


}

if ( version_compare( $current_version, '2.4.7RC2', '<' ) ) {
	$model = incsub_sbe_get_model();
	$model->create_squema();
}

if ( version_compare( $current_version, '2.4.9', '<' ) ) {
	global $wpdb;

	$model = incsub_sbe_get_model();
	$model->create_squema();
	
    $subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';

    $errors = 0;

    $logs_counts = absint( $wpdb->get_var( "SELECT COUNT(id) FROM $subscriptions_log_table" ) );

    if ( ! empty( $logs_counts ) ) {

    	for ( $i = 0; $i < $logs_counts; $i++ ) { 
    		$log = $wpdb->get_row( "SELECT * FROM $subscriptions_log_table LIMIT $i, 1");
    		
    		if ( empty( $log ) )
    			continue;

    		$log_id = $log->id;
    		$mails_list = maybe_unserialize( $log->mails_list );
    		if ( is_array( $mails_list ) ) {
    			//$logger = new Subscribe_By_Email_Logger( $log_id );
    			$max_email_id = 0;
    			foreach ( $mails_list as $item ) {
    				$max_email_id = max( $max_email_id, absint( $item['id'] ) );
    				$email = $item['email'];
    				$status = $item['status'] === true ? __( 'Sent', INCSUB_SBE_LANG_DOMAIN ) : $item['status'];
    				//$logger->write( $email, $status );
    			}
    			$wpdb->update(
					$subscriptions_log_table,
					array( 'mails_list' => '', 'max_email_ID' => $max_email_id ),
					array( 'id' => $log->id ),
					array( '%s' ),
					array( '%d' )
				);
    		}
    		else {
    			$errors++;
                //$logger = new Subscribe_By_Email_Logger( $log_id );
                $subscriptions_table = $wpdb->prefix . 'subscriptions';
                $max_email_id = $wpdb->get_var( "SELECT MAX(subscription_ID) max_email_id FROM $subscriptions_table" );
                $wpdb->update(
                    $subscriptions_log_table,
                    array( 'mails_list' => '', 'max_email_ID' => $max_email_id ),
                    array( 'id' => $log->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                //$logger->touch();
    		}
    		
    	}
    }

    return $errors;
}

if ( version_compare( $current_version, '2.5', '<' ) ) {
    $model = incsub_sbe_get_model();
    $model->create_squema();
}

if ( version_compare( $current_version, '2.7', '<' ) ) {
	$defaults = incsub_sbe_get_default_settings();


    if ( is_multisite() ) {
        if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
            $main_blog_id = BLOG_ID_CURRENT_SITE;
        }
        else {
            $blog_details = get_blog_details( 1 );
            if ( ! empty( $blog_details ) )
                $main_blog_id = 1;
            else
                $main_blog_id = get_current_blog_id();
        }

        $tmp_settings = get_blog_option( $main_blog_id, incsub_sbe_get_settings_slug() );
        $settings = array();

        if ( isset( $tmp_settings['from_email'] ) )
            $settings['from_email'] = $tmp_settings['from_email'];

        if ( isset( $tmp_settings['keep_logs_for'] ) )
            $settings['keep_logs_for'] = $tmp_settings['keep_logs_for'];

        if ( isset( $tmp_settings['mails_batch_size'] ) )
            $settings['mails_batch_size'] = $tmp_settings['mails_batch_size'];

        $tmp_settings = get_option( incsub_sbe_get_settings_slug() );

        $settings = wp_parse_args( $tmp_settings, $settings );

    }
    else {
        $settings = get_option( incsub_sbe_get_settings_slug() );
    }

    
    $settings = wp_parse_args( $settings, $defaults );
    incsub_sbe_update_settings( $settings );
}

if ( version_compare( $current_version, '2.8.1', '<' ) ) {
	
	require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		return;

	if ( isset( $_GET['sbe_upgrade_281'] ) )
		update_option( 'sbe_upgrade_281', true );

	if ( get_option( 'sbe_upgrade_281' ) ) {
		incsub_sbe_upgrade_281();
	}
	else {
		add_action( 'admin_notices', 'incsub_sbe_display_upgrade_281_notice' );
	}

	
	// We need to make this upgrade first
	return;
}


if ( version_compare( $current_version, '2.9', '<' ) ) {
	global $wpdb;

    $subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
    $queue_table = $wpdb->base_prefix . 'subscriptions_queue';
    $query = "SELECT * FROM $subscriptions_log_table";
    $results = $wpdb->get_results( $query, ARRAY_A );

    foreach ( $results as $log ) {           
        $mail_settings = maybe_unserialize( $log['mail_settings'] );
        $max_email_id = $log['max_email_ID'];
        $log_id = $log['id'];            
        $log_file = INCSUB_SBE_LOGS_DIR . '/sbe_log_' . $log_id . '.log';

        if ( file_exists( $log_file ) ) {
            $fp = @fopen( $log_file, 'r' );
            if ( is_resource( $fp ) ) {
                $insert_subscribers = array();
                while ( $buffer = fgets( $fp ) ) {
                    $line = explode( '|', $buffer );
                    $insert_subscribers[] = $line;
                }

                if ( ! empty( $insert_subscribers ) ) {
                    $query = "INSERT IGNORE INTO $queue_table ( blog_id, subscriber_email, campaign_id, campaign_settings, sent, sent_status ) VALUES ";

                    $values = array();
                    foreach ( $insert_subscribers as $subscriber ) {
                        $values[] = $wpdb->prepare( 
                            "( %d, %s, %d, %s, %d, %d )", 
                            get_current_blog_id(),
                            $subscriber[0],
                            $log_id,
                            '',
                            $subscriber[1],
                            $subscriber[2]
                        );
                    }

                    $query .= implode( ',', $values );
                    $wpdb->query( $query );

                }
            }
        }

        if ( ! empty( $mail_settings ) ) {  
            // The campaign hasn't finished yet
            // We need to enqueue the remaining emails
            $offset = absint( $log['mail_recipients'] ); 

            // Remaining subscribers for this campaign
            $subscribers_query = $wpdb->prepare(
                "SELECT ID, post_title FROM $wpdb->posts 
                WHERE post_status = 'publish' 
                AND post_type = 'subscriber'
                AND ID <= %d",
                $max_email_id
            );

            $query_key = md5( $subscribers_query );

            $subscribers = wp_cache_get( $query_key, 'sbe' );
            if ( ! $subscribers ) {
                $subscribers = $wpdb->get_results( $subscribers_query );
                wp_cache_set( $query_key, $subscribers, 'sbe', 3600 );    
            }

            $subscribers = array_slice( $subscribers, $offset );

            if ( ! empty( $subscribers ) ) {
                $query = "INSERT IGNORE INTO $queue_table ( blog_id, subscriber_email, campaign_id, campaign_settings, sent_status ) VALUES ";
                $values = array();
                foreach ( $subscribers as $subscriber ) {
                    $values[] = $wpdb->prepare( 
                        "( %d, %s, %d, %s, %d )", 
                        get_current_blog_id(),
                        $subscriber->post_title,
                        $log_id,
                        maybe_serialize( $mail_settings ),
                        0
                    );
                }

                $query .= implode( ',', $values );
                $wpdb->query( $query );
            }
        }
    }
}

if ( version_compare( $current_version, '3.4', '<' ) ) {
    subscribe_by_email()->add_capabilities();
}

if ( version_compare( $current_version, '3.5', '<' ) ) {
    $model = incsub_sbe_get_model();
    $model->create_squema();
}

