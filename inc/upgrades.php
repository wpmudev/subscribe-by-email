<?php


function incsub_sbe_upgrade_281() {
    global $wpdb;
    
    // Due to an upgrade bug in 2.8 we need to compare if all subscribers have been already moved to posts table
    $subscribers_table = $wpdb->prefix . 'subscriptions';
    $post_subscribers = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'subscriber'" );
    $old_subscribers = $wpdb->get_var( "SELECT COUNT(subscription_id) FROM $subscribers_table" );

    if ( $old_subscribers > $post_subscribers ) {
        // We need to copy subscribers again
        if ( current_user_can( 'manage_options') ) {
            set_transient( 'sbe_sending', 'next', apply_filters( 'sbe_time_between_batches', 1200 ) );

            if ( isset( $_REQUEST['sbe-upgrade'] ) ) {
                check_admin_referer( 'sbe-upgrade-281' );
                incsub_sbe_upgrade_281_upgrade_subscribers_batch();
            }
            else {
                incsub_sbe_display_upgrade_db_281();
            }
            
            die;
        }
        else {
            add_action( 'sbe_upgrade', 'incsub_sbe_restore_previous_version' );
        }
    }
    else {
        flush_rewrite_rules();
        incsub_sbe_upgrade_281_update_log_table();
        update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
        delete_option( 'sbe_upgrade_281' );

        global $wpdb;
        $wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = 'subscription_post_types' WHERE meta_key = 'post_types' AND post_id IN
        (SELECT ID FROM $wpdb->posts WHERE post_type = 'subscriber' )" );

        $redirect = add_query_arg(
            array(
                'page' => 'sbe-subscribers',
                'upgraded' => 'true'
            ),
            admin_url( 'admin.php' ) 
        );

        delete_transient( 'incsub_sbe_updating' );

        ?>
        <script type="text/javascript">
            location.href='<?php echo $redirect; ?>';
        </script>
        <?php
        exit;
    }
}

function incsub_sbe_upgrade_281_upgrade_subscribers_batch() {
    $batch_size = 20;

    $sid = 1;
    if ( isset( $_GET['sid'] ) )
        $sid = absint( $_GET['sid'] );

    global $wpdb;
    $subscribers_table = $wpdb->prefix . 'subscriptions';
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $subscribers_table WHERE subscription_ID >= %d LIMIT $batch_size", $sid ) );

    if ( empty( $results ) ){
        // We have finished
        $redirect = add_query_arg( 'sbe-upgrade-end', 'true', admin_url() );
        incsub_sbe_upgrade_281_update_log_table();
        
    }
    else{
        incsub_sbe_upgrade_281_insert_subscribers( $results );
        $last_id = $results[ count( $results ) - 1 ]->subscription_ID + 1;
        $redirect = add_query_arg(
            array(
                'sbe-upgrade' => 'true',
                '_wpnonce' => wp_create_nonce( 'sbe-upgrade-281' ),
                'sid' => $last_id
            ),
            site_url()
        );
        incsub_sbe_upgrade_281_display_continue_screen( $redirect, $last_id );
    }    
    exit;
}

function incsub_sbe_upgrade_281_insert_subscribers( $users ) {
    global $wpdb;
    $table = $wpdb->prefix . 'subscriptions';
    $table_meta = $wpdb->prefix . 'subscriptions_meta';

    foreach ( $users as $user ) {
        $autopt = $user->confirmation_flag == 1 ? true : false;

        // We need to copy the user metadata
        $meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_meta WHERE subscription_id = %d", $user->subscription_ID ) );
        $new_meta = array();
        foreach ( $meta as $row ) {
            $new_meta[ $row->meta_key ] = $row->meta_value;
        }

        $settings = maybe_unserialize( $user->subscription_settings );
        $settings = is_array( $settings ) ? $settings : array();
        foreach ( $settings as $key => $value ) {
            if ( $key == 'post_types' )
                $_key = 'subscription_post_types';
            else
                $_key = $key;
            $new_meta[ $_key ] = $value;
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

    }

    flush_rewrite_rules();
}

function incsub_sbe_display_upgrade_db_281() {
    global $wpdb;

    $subscribers_table = $wpdb->prefix . 'subscriptions';
    $subscribers_no = $wpdb->get_var( "SELECT COUNT(subscription_ID) FROM $subscribers_table" );

    ob_start();
    ?>
        <form method="get" action="" id="sbe-upgrade-form">
            <h2><?php _e( 'Subscribe By Email needs to be upgraded.', INCSUB_SBE_LANG_DOMAIN ); ?></h2>
            <?php if ( $subscribers_no > 100 ): ?>
                <p><?php printf( __( 'There are %s subscribers. This may take a while.', INCSUB_SBE_LANG_DOMAIN ), $subscribers_no ); ?></p>
            <?php endif; ?>

            <?php if ( error_reporting() != 0 || ini_get( 'display_errors' ) ): ?>
                <p style="color:red"><?php _e( 'It\'s recommended that WP_DEBUG is set to false in order to avoid errors during the upgrade', INCSUB_SBE_LANG_DOMAIN ); ?></p>
            <?php endif; ?>
            <?php wp_nonce_field( 'sbe-upgrade-281' ); ?>
            <input type="hidden" name="action" value="sbe_upgrade_281" />
            <p class="submit">
                <input type="submit" name="sbe-upgrade" class="button button-large" value="<?php _e( 'Start upgrade', INCSUB_SBE_LANG_DOMAIN ); ?>" />
            </p>
        </form>
        
    <?php
    $content = ob_get_clean();
    incsub_sbe_render_upgrade_screen( $content );
    
}


function incsub_sbe_upgrade_281_display_continue_screen( $redirect, $sid = false ) {
    global $wpdb;

    $subscribers_processed = _x( 'All', 'All subscribers processed', INCSUB_SBE_LANG_DOMAIN );
    if ( $sid ) {
        $subscribers_table = $wpdb->prefix . 'subscriptions';
        $subscribers_processed = $wpdb->get_var( "SELECT COUNT(subscription_ID) FROM $subscribers_table WHERE subscription_ID < $sid" );
    }

    ob_start();
    ?>
        <p><?php printf( __( '%s subscribers processed.', INCSUB_SBE_LANG_DOMAIN ), $subscribers_processed ); ?></p>
        <p><?php printf( __( 'This screen should be reloaded in a few seconds (you must have Javascript activated). If it doesn\'t, please <a href="%s">click here</a>', INCSUB_SBE_LANG_DOMAIN ), $redirect ); ?></p>
        <script>
            jQuery(document).ready(function($) {
                location.href = '<?php echo $redirect; ?>';
            });
        </script>
    <?php
    $content = ob_get_clean();

    incsub_sbe_render_upgrade_screen( $content );
}

function incsub_sbe_upgrade_281_update_log_table() {
    global $wpdb;

    $subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
    $max_ID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_type = 'subscriber'" );
    if ( ! $max_ID )
        $max_ID = 0;

    $wpdb->query( "UPDATE $subscriptions_log_table SET max_email_ID = $max_ID" );
}

function incsub_sbe_render_upgrade_screen( $content ) {
    nocache_headers();
    @header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
    ?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
        <head>
            <meta name="viewport" content="width=device-width" />
            <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
            <script type="text/javascript" src="<?php echo includes_url(  'wp-includes/js/jquery/jquery.js' ); ?>"></script>
            <title><?php _e( 'Subscribe By Email &rsaquo; Update' ); ?></title>
            <?php
            wp_admin_css( 'install', true );
            wp_admin_css( 'ie', true );
            ?>
        </head>
        <body class="wp-core-ui">
            <h1><a href="https://premium.wpmudev.org/project/subscribe-by-email/">Subscribe By Email</a></h1>
            <?php echo $content; ?>
        </body>
    <?php
}

function incsub_sbe_restore_previous_version() {
    update_option( 'incsub_sbe_version', '2.7.5' );
}

function incsub_sbe_display_upgrade_281_notice() {
    if ( ! current_user_can( 'manage_options' ) )
        return;
    
    $link = add_query_arg( 'sbe_upgrade_281', 'true', admin_url() );
    ?>
        <div class="error"><p><?php printf( __( 'Subscribe By Email needs to be upgraded manually. Please <a href="%s">click here</a> to start with the upgrade.', INCSUB_SBE_LANG_DOMAIN ), $link ); ?></p></div>
    <?php
}
