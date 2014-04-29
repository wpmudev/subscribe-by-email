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
        $this->subscriptions_log_table = $wpdb->prefix . 'subscriptions_log_table';
        $this->create_squema();

    }

    public function create_squema() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $this->create_subscriptions_log_table();
    }

    public function get_tables_list() {
        return array(
            $this->subscriptions_log_table
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

    public function _get_log_emails_list( $id ) {
        global $wpdb;

        $emails_list = $wpdb->get_var( $wpdb->prepare( "SELECT mails_list FROM $this->subscriptions_log_table WHERE id = %d", $id ) );
        return maybe_unserialize( $emails_list );
    }

    public function get_log_emails_list( $log_id, $batch_size ) {
        global $wpdb;

        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->subscriptions_log_table WHERE id = %d", $log_id ) );

        if ( empty( $log ) )
            return false;

        $continue_from = $log->mail_recipients;
        $end_on = $log->max_email_ID;

        $subscribers_ids = $wpdb->get_col( 
            $wpdb->prepare( 
                "SELECT ID FROM $this->posts 
                WHERE post_status = 'publish'
                AND post_type = 'subscriber'
                AND ID <= %d
                LIMIT %d, %d",
                $end_on,
                $continue_from,
                $batch_size 
            )
        );

        $args = array(
            'per_page' => -1,
            'include' => $subscribers_ids
        );
        $results = incsub_sbe_get_subscribers( $args );

        return $results->subscribers;
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

    public function set_log_email_status( $id, $mail, $message ) {
        global $wpdb;

        $this->get_log_emails_list( $id );
        if ( isset( $emails_list[ $email_sent['id'] ] ) ) {
            $emails_list[ $email_sent['id'] ] = $message;
            $this->update_log_emails_list( $id, $emails_list );
        }
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

        $query = "DELETE FROM $this->subscriptions_meta_table $where_digest";
        $wpdb->query( $query );
    }



    public function drop_schema() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS $this->subscriptions_log_table" );
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
