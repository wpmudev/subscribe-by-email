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
        $this->subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';

    }

    public function create_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_table();
        $this->create_subscriptions_log_table();
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
              subscription_email text NOT NULL,
              subscription_type varchar(200) NOT NULL,
              subscription_created bigint(20) NOT NULL,
              subscription_note varchar(200) NOT NULL,
              confirmation_flag tinyint(1) DEFAULT 0,
              user_key varchar(50) NOT NULL,
              subscription_settings text NOT NULL,
              PRIMARY KEY  (subscription_ID)
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
              mail_settings text DEFAULT '',
              PRIMARY KEY  (id)
            )  ENGINE=MyISAM $db_charset_collate;";
       
        dbDelta($sql);

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

    private function generate_user_key( $email ) {
        return substr( md5( time() . rand() . $email ), 0, 16 );
    }

    public function get_user_key( $email ) {
        global $wpdb;

        return $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT user_key FROM $this->subscriptions_table WHERE subscription_email = %s",
                $email 
            )
        );
    }

    public function get_subscribers( $current_page, $per_page, $sort = array(), $search = false ) {
        global $wpdb;

        $query = "SELECT * FROM $this->subscriptions_table ";

        if ( ! empty ( $search ) )
            $query .= sprintf( "WHERE subscription_email LIKE '%s'", '%' . esc_sql( $search ) . '%' );

        $total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT(*)', $query) );

        if ( $sort['email'][1] )
            $query .= " ORDER BY " . $sort['email'][0] . " " . $sort['email'][1];

        if ( $sort['created'][1] )
          $query .= " ORDER BY " . $sort['created'][0] . " " . $sort['created'][1];

        if ( $sort['subscription_type'][1] )
          $query .= " ORDER BY " . $sort['subscription_type'][0] . " " . $sort['subscription_type'][1];

        $query .= " LIMIT " . intval( ( $current_page - 1 ) * $per_page) . ", " . intval( $per_page );

        $subscriptions = $wpdb->get_results( $query, ARRAY_A );

        $results = array(
            'total' => $total,
            'subscribers' => $subscriptions
        );
        
        return $results;
    }

    public function get_all_subscribers( $count = false ) {
        global $wpdb;

        if ( ! $count ) {
            $query = "SELECT * FROM $this->subscriptions_table ORDER BY subscription_ID";
            $subscriptions = $wpdb->get_results( $query, ARRAY_A );
        }
        else {
            $query = "SELECT COUNT( subscription_ID ) FROM $this->subscriptions_table ORDER BY subscription_ID";
            $subscriptions = $wpdb->get_var( $query, ARRAY_A );
        }
        
        return $subscriptions;
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

    /**
     * Adds a subscriber or a list of them
     * 
     * @param String $email
     * 
     * @return Boolean False if the email already existed
     */
    public function add_subscriber( $email, $note, $type, $flag ) {
         global $wpdb;

         if ( ! $this->is_already_subscribed( $email ) ) {
            $result = $wpdb->insert( 
                $this->subscriptions_table, 
                array( 
                    'subscription_email' => $email, 
                    'subscription_note' => $note, 
                    'subscription_created' => time(), 
                    'subscription_type' => $type,
                    'user_key' => $this->generate_user_key( $email ),
                    'confirmation_flag' => $flag
                ),
                array(
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d'
                )
            );

            if ( ! $result ) {
                define( 'DIEONDBERROR', true );
                $wpdb->show_errors();
                wp_die(var_dump($wpdb->print_error()));
            }
            return $wpdb->insert_id;
         }
         else {
            return false;
         }
    }

    public function get_subscriber( $sid ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_table WHERE subscription_ID = %d", $sid ) );
    }

    public function get_subscriber_id( $email ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT * FROM $this->subscriptions_table WHERE subscription_email = %d", $email ) );
    }

    public function confirm_subscription( $key ) {
        global $wpdb;

        return $wpdb->update(
            $this->subscriptions_table,
            array( 'confirmation_flag' => 1 ),
            array( 'user_key' => $key ),
            array( '%d' ),
            array( '%s' )
        );
    }

    public function cancel_subscription( $key ) {
        global $wpdb;

        $pq = $wpdb->prepare( "DELETE FROM $this->subscriptions_table WHERE user_key = %s", $key );

        return $wpdb->query( $pq );
    }

    public function remove_old_subscriptions() {
        global $wpdb;

        $now = time();
        $lastweek_time = $now - Incsub_Subscribe_By_Email::$max_confirmation_time;

        $wpdb->query( "DELETE FROM $this->subscriptions_table WHERE confirmation_flag = 0 AND subscription_created < $lastweek_time" );
    }



    public function is_already_subscribed( $email ) {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(subscription_ID) FROM $this->subscriptions_table WHERE subscription_email = %s", $email ) );

        return ( $count > 0 ) ? true : false;
        
    }

    public function is_already_confirmed( $key ) {
        global $wpdb;

        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_table WHERE user_key = %s", $key ), ARRAY_A );

        if ( $result && isset( $result['confirmation_flag'] ) ) {
            if ( 1 == $result['confirmation_flag'] )
                return true;
            else
                return false;
        }

        return false;
        
    }

    public function is_subscriber( $key ) {
         global $wpdb;

        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_table WHERE user_key = %s", $key ), ARRAY_A );

        if ( ! empty( $result ) )
            return true;

        return false;
    }


    public function add_new_mail_log( $subject ) {
        global $wpdb;

        $wpdb->insert( 
            $this->subscriptions_log_table,
            array( 
                'mail_subject' => $subject,
                'mail_recipients' => 1,
                'mail_date' => time(),
                'mail_settings' => ''
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%s'
            )
        );

        return $wpdb->insert_id;

    }

    public function get_remaining_batch_mail() {
        global $wpdb;

        return $wpdb->get_row( "SELECT * FROM $this->subscriptions_log_table WHERE mail_settings != ''", ARRAY_A );
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
}
