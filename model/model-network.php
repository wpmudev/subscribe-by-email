<?php

class Incsub_Subscribe_By_Email_Model_Network {

	private $subscriptions_queue_table;
	private static $instance;

	public static function get_instance() {
		if ( empty( self::$instance ) )
			self::$instance = new self();
		return self::$instance;
	}

	public function __construct() {
		global $wpdb;
		
		$this->subscriptions_queue_table = $wpdb->base_prefix . 'subscriptions_queue';
	}

	public function create_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_queue_table();
    }

    /**
     * Creates/upgrade Queue table
     * 
     * @since 1.8
     */
    private function create_subscriptions_queue_table() {

        global $wpdb;

         // Get the correct character collate
        $db_charset_collate = '';
        if ( ! empty($wpdb->charset) )
          $db_charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
          $db_charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE $this->subscriptions_queue_table (
              id bigint(20) NOT NULL AUTO_INCREMENT,
              blog_id bigint(20) NOT NULL,
              subscriber_email varchar(100) NOT NULL,
              campaign_id varchar(20) NOT NULL,
              campaign_settings text,
              log_id bigint(20),
              sent int(12) DEFAULT 0,
              PRIMARY KEY  (id),
              UNIQUE KEY campaign (campaign_id,subscriber_email,blog_id),
              KEY log_id (log_id) 
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

    }

    public function insert_queue_items( $emails, $log_id, $campaign_id, $settings ) {
        global $wpdb;

        $blog_id = get_current_blog_id();

        if ( empty( $emails ) )
            return;

        $settings = maybe_serialize( $settings );

        $query = "INSERT IGNORE INTO $this->subscriptions_queue_table ( blog_id, subscriber_email, campaign_id, campaign_settings, log_id ) VALUES ";
        $values = array();
        foreach ( $emails as $email ) {
            $values[] = $wpdb->prepare( "( %d, %s, %s, %s, %d )", $blog_id, $email, $campaign_id, $settings, $log_id );
        }

        $query .= implode( ' , ', $values );
        $wpdb->query( $query );
        
    }

    public function get_queue_items( $limit ) {
        global $wpdb;

        $blog_id = get_current_blog_id();

        // We need to return all items from the same log.
        // We first get the first element
        $log_id = $wpdb->get_var( "SELECT log_id FROM $this->subscriptions_queue_table WHERE blog_id = $blog_id AND sent = 0 ORDER BY id LIMIT 1" );

        // Now we get results based on that log ID
        $results = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT * FROM $this->subscriptions_queue_table 
                WHERE log_id = %d
                AND sent = 0
                ORDER BY id LIMIT %d",
                $log_id, 
                $limit 
            ) 
        );

        if ( ! empty( $results ) ) {
            $return = array();
            foreach ( $results as $result ) {
                $result->campaign_settings = maybe_unserialize( $result->campaign_settings );
                $return[] = $result;
            }
            return $return;
            
        }

        return false;
    }

    public function count_queue_items() {
        global $wpdb;

        $blog_id = get_current_blog_id();

        $results = $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT COUNT(id) FROM $this->subscriptions_queue_table 
                WHERE sent = 0
                AND blog_id = %d",
                $blog_id
            ) 
        );

        return $results;
    }

    public function set_item_sent( $id ) {
        global $wpdb;

        $result = $wpdb->update(
            $this->subscriptions_queue_table,
            array( 'sent' => current_time( 'timestamp' ) ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }


    public function delete_queue_items_before( $timestamp ) {
        global $wpdb;

        $timestamp = absint( $timestamp );
        if ( ! $timestamp )
            return;

        $wpdb->query( $wpdb->prepare( "DELETE FROM $this->subscriptions_queue_table WHERE sent <= %d AND sent != 0", $timestamp ) );
    }

    public function is_log_finished( $log_id ) {
        global $wpdb;

        $blog_id = get_current_blog_id();
        
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $this->subscriptions_queue_table WHERE log_id = %d AND blog_id = %d", $log_id, $blog_id ) );

        if ( $result > 0 )
            return false;

        return true;
    }

}