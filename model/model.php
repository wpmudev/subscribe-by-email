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
              PRIMARY KEY  (id)
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

    private function generate_user_key( $email ) {
        return substr( md5( time() . rand() . $email ), 0, 16 );
    }



    function get_active_subscribers_count() {
        global $wpdb;
        
        $query = "SELECT COUNT( ID ) subscriptions FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'subscriber' ORDER BY ID";
        $subscriptions = $wpdb->get_row( $query, ARRAY_A );
        return absint( $subscriptions['subscriptions'] );
    }





    public function add_new_mail_log( $subject, $args = array() ) {
        global $wpdb;

        $max_id = $wpdb->get_var( "SELECT MAX(ID) max_id FROM $wpdb->posts WHERE post_type = 'subscriber'" );

        $wpdb->insert( 
            $this->subscriptions_log_table,
            array( 
                'mail_subject' => $subject,
                'mail_recipients' => 0,
                'mail_date' => current_time( 'timestamp' ),
                'mail_settings' => maybe_serialize( $args ),
                'max_email_ID' => $max_id
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%d'
            )
        );

        return $wpdb->insert_id;

    }

    public function update_mail_log_subject( $log_id, $subject ) {
        global $wpdb;

        $wpdb->update(
            $this->subscriptions_log_table,
            array( 'mail_subject' => $subject ),
            array( 'id' => $log_id ),
            array( '%s' ),
            array( '%d' )
        );
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

    public function set_mail_log_settings( $id, $settings ) {
        global $wpdb;

        $wpdb->update(
            $this->subscriptions_log_table,
            array( 'mail_settings' => $settings ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public function clear_mail_log_settings( $id ) {
        global $wpdb;
      

        $wpdb->update(
            $this->subscriptions_log_table,
            array( 'mail_settings' => '' ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

    }

    public function increment_mail_log( $id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "UPDATE $this->subscriptions_log_table SET mail_recipients = mail_recipients + 1 WHERE id = %d", $id ) );

    }

    public function update_mail_log_recipients( $id, $mail_recipients ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "UPDATE $this->subscriptions_log_table SET mail_recipients = %d WHERE id = %d", $mail_recipients, $id ) );        
    }


    public function get_log_emails_list( $log_id, $batch_size = false ) {
        global $wpdb;

        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $log_id ) );

        if ( empty( $log ) )
            return false;

        $continue_from = $log->mail_recipients;
        $end_on = $log->max_email_ID;

        $query = $wpdb->prepare( 
            "SELECT post_title FROM $wpdb->posts 
            WHERE post_status = 'publish' 
            AND post_type = 'subscriber'
            AND ID <= %d",
            $end_on
        );

        if ( $batch_size !== false ) {
            $query .= $wpdb->prepare( " LIMIT %d, %d", $continue_from, $batch_size );
        }

        $subscribers = $wpdb->get_col( $query );


        return $subscribers;
    }

    public function get_campaign_emails_list_count( $log_id ) {
        global $wpdb;

        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $log_id ) );

        if ( empty( $log ) )
            return 0;

        $continue_from = $log->mail_recipients;
        $end_on = $log->max_email_ID;

        $query = $wpdb->prepare( 
            "SELECT COUNT(ID) FROM $wpdb->posts 
            WHERE post_status = 'publish' 
            AND post_type = 'subscriber'
            AND ID <= %d",
            $end_on
        );

        return absint( $wpdb->get_var( $query ) );
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


    public function get_log( $current_page, $per_page, $sort = array(), $search = false ) {
        global $wpdb;

        $query = "SELECT * FROM $this->subscriptions_log_table ";

        if ( ! empty ( $search ) )
            $query .= sprintf( "WHERE mail_subject LIKE '%s'", '%' . esc_sql( $search ) . '%' );

        $total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT(*)', $query) );

        if ( $sort['subject'][1] )
            $query .= " ORDER BY " . $sort['subject'][0] . " " . $sort['subject'][1];

        if ( $sort['date'][1] )
          $query .= " ORDER BY " . $sort['date'][0] . " " . $sort['date'][1];

        $query .= " LIMIT " . intval( ( $current_page - 1 ) * $per_page) . ", " . intval( $per_page );

        $logs = $wpdb->get_results( $query );

        $results = array(
            'total' => $total,
            'logs' => $logs
        );
        
        return $results;
    }

    public function get_single_log( $id ) {
        global $wpdb;

        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $id ) );

        if ( ! empty( $result ) ) {
            $result->campaign_settings = maybe_unserialize( $result->mail_settings );
            return $result;
        }

        return false;
    }

    public function get_old_logs_ids( $time ) {
        global $wpdb;

        $results = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $this->subscriptions_log_table WHERE mail_date < %d", $time ) );
        return $results;
    }

    public function delete_log( $log_id ) {
        global $wpdb;

        if ( is_array( $log_id ) && ! empty( $log_id ) ) {
            $where = "WHERE id IN (" . implode( ', ', $log_id ) . ")";
            $where_digest = "WHERE meta_key IN ('digest_sent_" . implode( "','digest_sent_", $log_id ) . "')";
        }
        else {
            $where = $wpdb->prepare( "WHERE id = %d", $log_id );   
            $meta_key = 'digest_sent_' . $log_id;
            $where_digest = "WHERE meta_key = '$meta_key'";   
        }

        $query = "DELETE FROM $this->subscriptions_log_table $where";
        $wpdb->query( $query );

        $wpdb->query( "DELETE FROM $wpdb->postmeta $where_digest" );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $this->subscriptions_queue_table
                WHERE campaign_id = %d",
                $log_id
            )
        );
    }



    public function drop_schema() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_log_table" );
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_queue_table" );
    }


    public function is_digest_sent( $sid, $mail_log_id ) {
        global $wpdb;

        $meta_key = 'digest_sent_' . $mail_log_id;
        $results = get_post_meta( $sid, $meta_key, true );

        if ( empty( $results ) )
            return false;

        return true;

    }


    public function get_posts_ids( $args ) {
        global $wpdb;

        $defaults = array(
            'post_type' => array( 'post' ),
            'post_status' => array( 'publish' ),
            'after_date' => '',
            'include' => ''
        );

        $args = wp_parse_args( $args, $defaults );

        extract( $args );

        $order = "ORDER BY p.post_date DESC";

        $where = array();

        // Post Type
        if ( empty( $post_type ) )
            return array();

        if ( is_string( $post_type ) )
            $post_type = array( $post_type );

        $where[] = "p.post_type IN ('" . join("', '", $post_type) . "')";

        // Post Status
        if ( empty( $post_status ) )
            return array();

        if ( is_string( $post_status ) )
            $post_status = array( $post_status );

        $where[] = "p.post_status IN ('" . join("', '", $post_status) . "')";

        // Date
        if ( ! empty( $after_date ) ) {
            $where[] = $wpdb->prepare( "p.post_date > %s", $after_date );
        }

        // Include IDs
        if ( ! empty( $include ) ) {
            if ( is_numeric( $include ) )
                $include = array( absint( $include ) );

            $where[] = "p.ID IN (" . implode(',', array_map( 'absint', $include )) . ")";
        }

        $where = "WHERE " . implode( " AND ", $where );

        $query = "SELECT ID FROM $wpdb->posts p $where $order";

        return $wpdb->get_col( $query );

        
    }

    public function insert_queue_items( $emails, $campaign_id, $settings ) {
        global $wpdb;

        $blog_id = get_current_blog_id();

        if ( empty( $emails ) )
            return;

        $settings = maybe_serialize( $settings );

        $query = "INSERT IGNORE INTO $this->subscriptions_queue_table ( blog_id, subscriber_email, campaign_id, campaign_settings ) VALUES ";
        $values = array();
        foreach ( $emails as $email ) {
            $values[] = $wpdb->prepare( "( %d, %s, %s, %s )", $blog_id, $email, $campaign_id, $settings );
        }

        $query .= implode( ' , ', $values );

        $wpdb->query( $query );
        
    }

    public function get_queue_item( $id ) {
        global $wpdb;

        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_queue_table WHERE id = %d", $id ) );
        if ( $result ) {
            $result->campaign_settings = maybe_unserialize( $result->campaign_settings );
        }

        return $result;
    }


    public function set_queue_item_sent( $id, $status ) {
        global $wpdb;

        $result = $wpdb->update(
            $this->subscriptions_queue_table,
            array( 
                'sent' => current_time( 'timestamp' ),
                'sent_status' => $status
            ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    public function delete_queue_item( $id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM $this->subscriptions_queue_table WHERE id = %d", $id ) );
    }

    public function delete_queue_items_before( $timestamp ) {
        global $wpdb;

        $timestamp = absint( $timestamp );
        if ( ! $timestamp )
            return;

        $wpdb->query( $wpdb->prepare( "DELETE FROM $this->subscriptions_queue_table WHERE sent <= %d AND sent != 0", $timestamp ) );
    }

    public function is_queue_empty_for_campaign( $campaign_id ) {
        global $wpdb;
        $blog_id = get_current_blog_id();
        $results = $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT COUNT(id) FROM $this->subscriptions_queue_table 
                WHERE blog_id = %d
                AND campaign_id = %s
                AND sent = 0",
                $blog_id, 
                $campaign_id
            ) 
        );

        if ( empty( $results ) )
            return true;
        else
            return false;
    }

}
