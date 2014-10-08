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
	$model = incsub_sbe_get_model();
	$model->create_squema();
	require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
	incsub_sbe_upgrade_249();
}

if ( version_compare( $current_version, '2.5', '<' ) ) {
	require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
	incsub_sbe_upgrade_25();
}

if ( version_compare( $current_version, '2.7', '<' ) ) {
	require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
	incsub_sbe_upgrade_27();
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

if ( version_compare( $current_version, '2.8.4', '<' ) ) {

	global $wpdb;
	$subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
	$query = "SELECT * FROM $subscriptions_log_table WHERE mail_settings != ''";
	$results = $wpdb->get_results( $query, ARRAY_A );

	foreach ( $results as $log ) {

		$offset = absint( $log['mail_recipients'] );
		$max_email_id = $log['max_email_ID'];
		$mail_settings = maybe_unserialize( $log['mail_settings'] );

		$subscribers = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT ID, post_title FROM $wpdb->posts 
                WHERE post_status = 'publish' 
                AND post_type = 'subscriber'
                AND ID <= %d",
                $max_email_id
            )
        );

		$i = -1;
		$insert_subscribers = array();
		foreach ( $subscribers as $subscriber ) {
			$i++;

			if ( $i < $offset )
				continue;

			if ( $subscriber->ID > $max_email_id )
				continue;

			$insert_subscribers[] = $subscriber->post_title;

		}

		$model = incsub_sbe_get_model();
		$model->insert_queue_items( $insert_subscribers, $log['id'], $mail_settings );
		
		
		
	}
	
}