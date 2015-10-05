<?php


class Incsub_Subscribe_By_Email_Model {

	public static $instance;
    public $subscriptions_log_table;
    public $subscriptions_queue_table;

	/**
	 * Singleton Pattern
	 * 
	 * Gets the instance of the class
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) )
			self::$instance = new Incsub_Subscribe_By_Email_Model();
		return self::$instance;
	}

	public function __construct() {
        global $wpdb;
        
        $this->subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
        $this->subscriptions_queue_table = $wpdb->base_prefix . 'subscriptions_queue';

    }

    public function create_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_log_table();
    }

    public function create_network_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_queue_table();
    }

    public function get_tables_list() {
        return array(
            $this->subscriptions_log_table,
            $this->subscriptions_queue_table
        );
    }


    /**
     * Creates/upgrade FAQ table
     * 
     * @since 1.8
     */
    private function create_subscriptions_log_table() {

        global $wpdb;

         // Get the correct character collate
        $db_charset_collate = '';
        if ( ! empty($wpdb->charset) )
          $db_charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
          $db_charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE $this->subscriptions_log_table (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              mail_subject text NOT NULL,
              mail_recipients int(8) NOT NULL,
              mail_date bigint(20) NOT NULL,
              mail_settings text,
              max_email_ID bigint(20) NOT NULL,
              campaign_hash varchar(50) NOT NULL default '',
              PRIMARY KEY  (id),
              KEY campaign_hash (campaign_hash)
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

        $alter = "ALTER TABLE $this->subscriptions_log_table MODIFY COLUMN mail_settings text";
        $wpdb->query( $alter );

    }

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
              campaign_id bigint(20) NOT NULL,
              campaign_settings text,
              sent int(12) DEFAULT 0,
              sent_status TINYINT(1) DEFAULT 0,
              error_msg text DEFAULT '',
              PRIMARY KEY  (id),
              UNIQUE KEY campaign (blog_id,campaign_id,subscriber_email)
            )  ENGINE=MyISAM $db_charset_collate;";

        dbDelta($sql);

    }



    public function upgrade_247b() {
        global $wpdb;

        $results = $wpdb->get_results( "SELECT * FROM $this->subscriptions_log_table WHERE mail_settings != '' AND ( mails_list = '' OR mails_list IS NULL )");

        $emails_list = $this->get_email_list();
        foreach ( $emails_list as $key => $email_details ) {
            $emails_list[ $key ]['status'] = false;
        }
        foreach ( $results as $result ) {
            $new_emails_list = $emails_list;
            foreach ( $emails_list as $key => $email ) {
                $last_id = $key;
                if ( absint( $result->mail_recipients ) == absint( $key ) ) {
                    break;
                }
                else {
                    $new_emails_list[ $key ]['status'] = true;
                }
            }
            $this->update_log_emails_list( $result->id, $new_emails_list );
        }
        unset( $new_email_list );
        unset( $emails_list );
    }



    function get_active_subscribers_count() {
        global $wpdb;
        
        $query = "SELECT COUNT( ID ) subscriptions FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'subscriber' ORDER BY ID";
        $subscriptions = $wpdb->get_row( $query, ARRAY_A );
        return absint( $subscriptions['subscriptions'] );
    }


    public function get_remaining_batch_mail( $blog_id = false ) {
        global $wpdb;

        if ( ! $blog_id && is_multisite() )
            $blog_id = get_current_blog_id();

        $query = $wpdb->prepare( "SELECT campaign_id, campaign_settings FROM $this->subscriptions_queue_table WHERE sent = 0 AND blog_id = %d ORDER BY id ASC LIMIT 1", $blog_id );
        $results = $wpdb->get_row( $query );

        if ( ! empty( $results ) ) {
            $results->campaign_settings = maybe_unserialize( $results->campaign_settings );
            return $results;
        }

        return false;
    }





    public function update_log_emails_list( $id, $emails_list ) {
        global $wpdb;

        $wpdb->update(
            $this->subscriptions_log_table,
            array( 'mails_list' => maybe_serialize( $emails_list ) ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }



    public function drop_schema() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_log_table" );
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_queue_table" );
    }



    public function delete_queue_item( $id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM $this->subscriptions_queue_table WHERE id = %d", $id ) );
    }

   public function get_email_list() {
        global $wpdb;

        $query = "SELECT subscription_ID, subscription_email FROM $this->subscriptions_table WHERE confirmation_flag = 1 ORDER BY subscription_ID";
        $subscriptions = $wpdb->get_results( $query, ARRAY_A );

        $emails = array();
        foreach ( $subscriptions as $subscription ) {
            $emails[] = array(
                'id' => $subscription['subscription_ID'],
                'email' => $subscription['subscription_email']
            );
        }

        return $emails;
    }


}
