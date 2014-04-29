<?php

function incsub_sbe_upgrade_249() {
    global $wpdb;
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
    			$logger = new Subscribe_By_Email_Logger( $log_id );
    			$max_email_id = 0;
    			foreach ( $mails_list as $item ) {
    				$max_email_id = max( $max_email_id, absint( $item['id'] ) );
    				$email = $item['email'];
    				$status = $item['status'] === true ? __( 'Sent', INCSUB_SBE_LANG_DOMAIN ) : $item['status'];
    				$logger->write( $email, $status );
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
                $logger = new Subscribe_By_Email_Logger( $log_id );
                $subscriptions_table = $wpdb->prefix . 'subscriptions';
                $max_email_id = $wpdb->get_var( "SELECT MAX(subscription_ID) max_email_id FROM $subscriptions_table" );
                $wpdb->update(
                    $subscriptions_log_table,
                    array( 'mails_list' => '', 'max_email_ID' => $max_email_id ),
                    array( 'id' => $log->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                $logger->touch();
    		}
    		
    	}
    }

    return $errors;
}

function incsub_sbe_upgrade_25() {
    $model = incsub_sbe_get_model();
    $model->create_squema();
}

function incsub_sbe_upgrade_27() {
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


function incsub_sbe_render_upgrade_database_screen_28RC1() {
    global $wpdb;

    $table = $wpdb->prefix . 'subscriptions';
    $total_users = $wpdb->get_var( "SELECT COUNT(subscription_ID) FROM $table" );
    
    ?>
        <div class="wrap">
            <h3><?php printf( __( 'Total subscribers: %s', INCSUB_SBE_LANG_DOMAIN ), $total_users ); ?></h3>
            <?php if ( $total_users > 500 ): ?>
                <p><?php _e( 'This could take a while, please be patient and do not close this window' ); ?></p>
            <?php endif; ?>

            <p><?php _e( 'Updating users:', INCSUB_SBE_LANG_DOMAIN ); ?> <span id="subscriber-count">0</span> / <?php echo $total_users; ?> <span class="spinner" style="float:none;display:inline-block;"></span></p>

            <h3 id="success-message" style="display:none"><?php _e( 'Subscribe By Email was successfully updated.', INCSUB_SBE_LANG_DOMAIN ); ?></h3>


            <script>
                jQuery(document).ready(function($) {
                    var subscribers_count = 0;

                    import_subscribers();

                    function import_subscribers() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'post',
                            
                            data: {
                                'counter': subscribers_count,
                                'nonce': "<?php echo wp_create_nonce( 'sbe_upgrade_database' ); ?>",
                                'action': 'sbe_upgrade_database_28rc1'
                            },
                        })
                        .done(function( response ) {
                            if ( response.data.done ) {
                                $('#success-message').show();
                                $('.spinner').hide();
                                return true;
                            }
                            subscribers_count++;
                            $( '#subscriber-count' ).text( subscribers_count );
                            import_subscribers();
                        });
                    }
                    
                    
                });
            </script>
        </div>
    <?php
}


