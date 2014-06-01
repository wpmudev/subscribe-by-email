<?php


class Incsub_Subscribe_By_Email_Model {

	public static $instance;
    private $subscriptions_table;
    private $subscriptions_log_table;

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
        
        $this->subscriptions_table = $wpdb->prefix . 'subscriptions';
        $this->subscriptions_meta_table = $wpdb->prefix . 'subscriptions_meta';
        $this->subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';

        $this->create_squema();

    }

    public function create_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_table();
        $this->create_subscriptions_log_table();
        $this->create_subscriptions_meta_table();
    }

    public function get_tables_list() {
        return array(
            $this->subscriptions_table,
            $this->subscriptions_meta_table,
            $this->subscriptions_log_table
        );
    }

    /**
     * Creates/upgrade FAQ table
     * 
     * @since 1.8
     */
    private function create_subscriptions_table() {

        global $wpdb;

         // Get the correct character collate
        $db_charset_collate = '';
        if ( ! empty($wpdb->charset) )
          $db_charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
          $db_charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE $this->subscriptions_table (
              subscription_ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              subscription_email varchar(100) NOT NULL,
              subscription_type varchar(200) NOT NULL,
              subscription_created bigint(20) NOT NULL,
              subscription_note varchar(200) NOT NULL,
              confirmation_flag tinyint(1) DEFAULT 0,
              user_key varchar(50) NOT NULL,
              subscription_settings text,
              PRIMARY KEY  (subscription_ID),
              UNIQUE KEY subscription_email (subscription_email)
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

        $alter = "ALTER TABLE $this->subscriptions_table MODIFY COLUMN subscription_settings text";
        $wpdb->query( $alter );

    }

    private function create_subscriptions_meta_table() {

        global $wpdb;

         // Get the correct character collate
        $db_charset_collate = '';
        if ( ! empty($wpdb->charset) )
          $db_charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
          $db_charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE $this->subscriptions_meta_table (
              id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              subscription_id bigint(20) NOT NULL,
              meta_key varchar(64) NOT NULL,
              meta_value longtext NOT NULL,
              PRIMARY KEY  (id),
              UNIQUE KEY id (id),
              KEY subscription_id (subscription_id),
              KEY meta_key (meta_key)
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

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
              mails_list text,
              max_email_ID bigint(20) NOT NULL,
              PRIMARY KEY  (id)
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

        $alter = "ALTER TABLE $this->subscriptions_log_table MODIFY COLUMN mail_settings text";
        $wpdb->query( $alter );
        
        $alter = "ALTER TABLE $this->subscriptions_log_table MODIFY COLUMN mails_list text";
        $wpdb->query( $alter );

    }

    public function upgrade_schema() {
        global $wpdb;
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_table();
        $this->create_subscriptions_log_table();
        
        $wpdb->query( "UPDATE $this->subscriptions_table SET confirmation_flag = 1");

        $result = $this->get_all_subscribers();
        foreach ($result as $row ) {
            $key = $this->generate_user_key( $row['subscription_email'] );
            $wpdb->query( 
                $wpdb->prepare(
                    "UPDATE $this->subscriptions_table SET user_key = %s WHERE subscription_email = %s",
                    $key,
                    $row['subscription_email']
                )
            );
        }
        
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


    public function get_all_subscribers( $count = false ) {
        global $wpdb;

        if ( ! $count ) {
            $query = "SELECT * FROM $this->subscriptions_table ORDER BY subscription_ID";
            $subscriptions = $wpdb->get_results( $query, ARRAY_A );
        }
        else {
            $query = "SELECT COUNT( subscription_ID ) subscriptions FROM $this->subscriptions_table ORDER BY subscription_ID";
            $subscriptions = $wpdb->get_row( $query, ARRAY_A );
            $subscriptions = absint( $subscriptions['subscriptions'] );
        }
        return $subscriptions;
    }

    function get_active_subscribers_count() {
        global $wpdb;
        
        $query = "SELECT COUNT( ID ) subscriptions FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'subscriber' ORDER BY ID";
        $subscriptions = $wpdb->get_row( $query, ARRAY_A );
        return absint( $subscriptions['subscriptions'] );
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



    public function add_new_mail_log( $subject ) {
        global $wpdb;

        $max_id = $wpdb->get_var( "SELECT MAX(ID) max_id FROM $wpdb->posts WHERE post_type = 'subscriber'" );

        $wpdb->insert( 
            $this->subscriptions_log_table,
            array( 
                'mail_subject' => $subject,
                'mail_recipients' => 0,
                'mail_date' => current_time( 'timestamp' ),
                'mail_settings' => '',
                'mails_list' => '',
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

    public function get_remaining_batch_mail() {
        global $wpdb;

        $query = "SELECT id, mail_recipients, mail_date, mail_settings FROM $this->subscriptions_log_table WHERE mail_settings != ''";
        return $wpdb->get_row( $query, ARRAY_A );
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


    public function get_log_emails_list( $log_id, $batch_size ) {
        global $wpdb;

        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $log_id ) );

        if ( empty( $log ) )
            return false;

        $continue_from = $log->mail_recipients;
        $end_on = $log->max_email_ID;

        $subscribers = $wpdb->get_col( 
            $wpdb->prepare( 
                "SELECT post_title FROM $wpdb->posts 
                WHERE post_status = 'publish' 
                AND post_type = 'subscriber'
                AND ID <= %d
                LIMIT %d, %d",
                $end_on,
                $continue_from,
                $batch_size 
            )
        );


        return $subscribers;
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

        $logs = $wpdb->get_results( $query, ARRAY_A );

        $results = array(
            'total' => $total,
            'logs' => $logs
        );
        
        return $results;
    }

    public function get_single_log( $id ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $id ) );
    }

    public function get_old_logs_ids( $time ) {
        global $wpdb;

        $results = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $this->subscriptions_log_table WHERE mail_date < %d AND mail_settings = ''", $time ) );
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
    }


    public function get_subscriber_settings( $user_key ) {
        global $wpdb;

        $results = $wpdb->get_var( 
            $wpdb->prepare(
                "SELECT subscription_settings FROM $this->subscriptions_table WHERE user_key = %s",
                $user_key
            )
        );

        if ( empty( $results ) )
            return false;
        else
            return maybe_unserialize( $results );

    }

    public function update_subscriber_settings( $key, $settings ) {
        global $wpdb;

        $ser_settings = maybe_serialize( $settings );

        $wpdb->update(
            $this->subscriptions_table,
            array( 'subscription_settings' => $ser_settings ),
            array( 'user_key' => $key ),
            array( '%s' ),
            array( '%s' )
        );
    }

    public function update_subscriber_email( $sid, $email ) {
        global $wpdb;

        $wpdb->update(
            $this->posts,
            array( 'post_title' => $email ),
            array( 'ID' => $sid ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public function drop_schema() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_table" );
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_log_table" );
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_meta_table" );
    }

    public function add_subscriber_meta( $subscription_id, $meta_key, $meta_value ) {
        global $wpdb;

        update_post_meta( $subscription_id, $meta_key, $meta_value );
        
    }


    public function update_subscriber_meta( $subscription_id, $meta_key, $meta_value ) {
        global $wpdb;

        update_post_meta( $subscription_id, $meta_key, $meta_value );

        return true;
    }

    public function delete_subscriber_meta( $subscription_id, $meta_key ) {
        global $wpdb;

        delete_post_meta( $subscription_id, $meta_key );
    }

    public function is_digest_sent( $sid, $mail_log_id ) {
        global $wpdb;

        $meta_key = 'digest_sent_' . $mail_log_id;
        $results = get_post_meta( $sid, $meta_key, true );

        if ( empty( $results ) )
            return false;

        return true;

    }
    public function set_digest_sent( $sid, $mail_log_id ) {
        global $wpdb;

        $meta_key = 'digest_sent_' . $mail_log_id;

        update_post_meta( $sid, $meta_key, 1 );
    }


    public function delete_subscriber_all_meta( $sid ) {
        global $wpdb;

        $q = "DELETE FROM $this->subscriptions_meta_table";

        if ( is_array( $sid ) ) {
            $where = array();
            foreach ( $sid as $value )
                $where[] = $wpdb->prepare( "%d", $value );

            $where = implode( ', ', $where );
            $where = "subscription_id IN ($where)";
            $q .= " WHERE $where";
        }
        else {
            $q = $wpdb->prepare(
                "DELETE FROM $this->subscriptions_meta_table
                WHERE subscription_id = %d",
                $sid
            );
        }

        return $wpdb->query( $q );
    }

    public function delete_subscribers_all_meta( $meta_key ) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $this->subscriptions_meta_table
                WHERE meta_key = %s",
                $meta_key
            )
        );  
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

}
