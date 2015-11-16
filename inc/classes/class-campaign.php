<?php


class SBE_Campaign {

	public $id = 0;

	public $mail_subject = 0;

	public $mail_recipients = 0;

	public $mail_date = 0;

	public $mail_settings = array();

	public $max_email_ID = 0;

	public $campaign_hash = '';

	public static function get_instance( $campaign_item ) {
		global $wpdb;

		if ( is_object( $campaign_item ) ) {
			$campaign_item = incsub_sbe_sanitize_campaign_fields( $campaign_item );
			return new self( $campaign_item );
		}

		$id = absint( $campaign_item );
		if ( ! $id )
			return false;

		$model = incsub_sbe_get_model();
		$table = $model->subscriptions_log_table;

        $campaign_item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		if ( ! $campaign_item )
			return false;

        $campaign_item->campaign_settings = maybe_unserialize( $campaign_item->mail_settings );

		$campaign_item = incsub_sbe_sanitize_campaign_fields( $campaign_item );

		return new self( $campaign_item );
		
	}

	public function __construct( $campaign_item ) {
		foreach ( get_object_vars( $campaign_item ) as $key => $value ) 
			$this->$key = $value;		
	}

	/**
	 * Get the total subscribers that this campaign is goign to send emails to
	 *
	 * @return Integer
	 */
	public function get_total_emails_count() {		
        return incsub_sbe_get_subscribers_count( $this->max_email_ID );
	} 

	public function get_status() {
		$subscribers_count = $this->get_total_emails_count();

		if ( $subscribers_count == 0 )
			return 'empty';
		elseif ( $subscribers_count <= $this->mail_recipients )
			return 'finished';
		else
			return 'pending';
	}

	public function get_subscribers_list() {
		global $wpdb;

        $end_on = $this->max_email_ID;

        $query = $wpdb->prepare( 
            "SELECT ID FROM $wpdb->posts 
            WHERE post_status = 'publish' 
            AND post_type = 'subscriber'
            AND ID <= %d",
            $end_on
        );

        $subscribers_ids = $wpdb->get_col( $query );
        if ( empty( $subscribers_ids ) )
        	return array();

        $subscribers = array_map( 'incsub_sbe_get_subscriber', $subscribers_ids );

        return $subscribers;

	}

	/**
	 * Get the pending subscribers for this campaign
	 * 
	 * @return array list of queue items pending to be sent
	 */
	public function get_campaign_queue() {
		$args = array(
			'campaign_id' => $this->id,
			'per_page' => -1,
			'status' => 'pending',
			'blog_id' => get_current_blog_id()
		);
		$return = incsub_sbe_get_queue_items( $args );
		return $return['items'];
	}

	/**
	 * Get the whole queue for this campaign including pending/sent emails
	 * 
	 * @return array list of queue items
	 */
	public function get_campaign_all_queue() {
		$args = array(
			'campaign_id' => $this->id,
			'per_page' => -1,
			'status' => 'all',
			'blog_id' => get_current_blog_id()
		);
		$return = incsub_sbe_get_queue_items( $args );
		return $return['items'];
	}

	/**
	 * Get the sent queue for this campaign
	 * 
	 * @return array list of sent queue items
	 */
	public function get_campaign_sent_queue() {
		$args = array(
			'campaign_id' => $this->id,
			'per_page' => -1,
			'status' => 'sent',
			'blog_id' => get_current_blog_id()
		);
		$return = incsub_sbe_get_queue_items( $args );
		return $return['items'];
	}

	/**
	 * Refresh the campaign status if the queue has finished
	 */
	public function refresh_campaign_status() {
		global $wpdb;

		$queue_items = $this->get_campaign_queue();

		if ( empty( $queue_items ) ) {
			$subscribers_count = $this->get_total_emails_count();
			$this->mail_recipients = $subscribers_count;
			$model = incsub_sbe_get_model();
			$table = $model->subscriptions_log_table;
        	$wpdb->query( $wpdb->prepare( "UPDATE $table SET mail_recipients = %d WHERE id = %d", $this->mail_recipients, $this->id ) );    
		}
	}


}

/**
 * Get a Campaign instance
 * 
 * @param  Instance $sid Campaign ID
 * @return Object      SBE_Campaign instance/ False in case of error
 */
function incsub_sbe_get_campaign( $campaign_item ) {
	return SBE_Campaign::get_instance( $campaign_item );
}

/**
 * Insert a new campaign
 * 
 * @param  String $subject Campaign Subject
 * @param  array  $args    Campaign Settings
 * @param Boolean $force It will insert the campaign even if there's another one with the same hash
 * 
 * @return mixed          New camapign ID/False
 */
function incsub_sbe_insert_campaign( $subject, $args = array(), $force = false ) {
    global $wpdb;

    $table = subscribe_by_email()->model->subscriptions_log_table;

    $max_id = $wpdb->get_var( "SELECT MAX(ID) max_id FROM $wpdb->posts WHERE post_type = 'subscriber' AND post_status = 'publish'" );

	if ( ! $max_id )
		return false;

	// Sanitize args
	$defaults = apply_filters( 'sbe_campaign_default_args', array(
		'posts_ids' => array()
	) );

	$args = wp_parse_args( $args, $defaults );

	if ( empty( $args['posts_ids'] ) )
		return false;

	// Sort the arguments so we can produce better hashes
	ksort( $args );

	//also sort the post_ids
	sort( $args['posts_ids'] );

	$hash = md5( maybe_serialize( $args ) );

	if ( ! $force ) {
		// Check if the hash already exists
		$campaign_exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE campaign_hash = %s", $hash ) );
		if ( $campaign_exists )
			return false;
	}

	$wpdb->insert(
        $table,
        array( 
            'mail_subject' => $subject,
            'mail_recipients' => 0,
            'mail_date' => current_time( 'timestamp' ),
            'mail_settings' => maybe_serialize( $args ),
            'max_email_ID' => $max_id,
	        'campaign_hash' => $hash
        ),
        array(
            '%s',
            '%d',
            '%d',
            '%s',
            '%d',
            '%s'
        )
    );

    return $wpdb->insert_id;
}

/**
 * Get a list of campaigns based on an arguments list
 * 
 * @param  Array $args Arguments
 * @return array       
 	array(
 		'items' => List of SBE_Campaign instances objects
 		'total' => Number of campaigns found in DB
	)
 */
function incsub_sbe_get_campaigns( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'current_page' => 1,
		'per_page' => 10,
		'sortable' => array(),
		'search' => false
	);

	$args = wp_parse_args( $args, $defaults );

	extract( $args );

	$model = incsub_sbe_get_model();

	$table = $model->subscriptions_log_table;

    $query = "SELECT * FROM $table";

    if ( ! empty ( $search ) )
        $query .= sprintf( "WHERE mail_subject LIKE '%s'", '%' . esc_sql( $search ) . '%' );

    $total = $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT(*)', $query) );

    $default_sortable = array(
    	'subject' => array( false, false ),
    	'date' => array( false, false ),
    );
    $sortable = wp_parse_args( $default_sortable, $sortable );

    if ( $sortable['subject'][1] )
        $query .= " ORDER BY " . $sortable['subject'][0] . " " . $sortable['subject'][1];

    if ( $sortable['date'][1] )
      $query .= " ORDER BY " . $sortable['date'][0] . " " . $sortable['date'][1];

    $query .= " LIMIT " . intval( ( $current_page - 1 ) * $per_page) . ", " . intval( $per_page );

    $campaigns = $wpdb->get_results( $query );
    $campaigns = array_map( 'incsub_sbe_get_campaign', $campaigns );

	return array(
		'items' => $campaigns,
		'count' => absint( $total )
	);

}

/**
 * Delete a campaign
 * 
 * @param  Integer $id Campaign ID
 * 
 * @return Boolean     True if everything went OK
 */
function incsub_sbe_delete_campaign( $id ) {
	global $wpdb;

	$campaign = incsub_sbe_get_campaign( $id );
	if ( ! $campaign )
		return false;

	$args = array(
		'campaign_id' => $id,
		'per_page' => -1,
		'status' => 'all'
	);
	$items = $campaign->get_campaign_all_queue();

	foreach ( $items as $item )
		incsub_sbe_delete_queue_item( $item->id );

	$table = subscribe_by_email()->model->subscriptions_log_table;

	$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id = %d", $id ) );

	return true;

}

function incsub_sbe_get_campaigns_since( $timestamp ) {
	$timestamp = absint( $timestamp );
	if ( ! $timestamp )
		return array();

	global $wpdb;

	$table = subscribe_by_email()->model->subscriptions_log_table;
    $results = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE mail_date < %d", $timestamp ) );

    $campaigns = array_map( 'incsub_sbe_get_campaign', $results );

    return $campaigns;

}

/**
 * Increment the number of users that have received n email for a campaign
 *
 * @param  Integer $campaign_id
 */
function incsub_sbe_increment_campaign_recipients( $campaign_id ) {
	global $wpdb;

	$model = incsub_sbe_get_model();
	$table = $model->subscriptions_log_table;
    $wpdb->query( $wpdb->prepare( "UPDATE $table SET mail_recipients = mail_recipients + 1 WHERE id = %d", $campaign_id ) );
}

/**
 * Sanitize fields for the SBE_Campaign instance
 * 
 * @param  SBE_Campaign $campaign_item SBE_Campaign instance
 * @return SBE_Campaign                Sanitized SBE_Campaign instance
 */
function incsub_sbe_sanitize_campaign_fields( $campaign_item ) {
	$int_fields = array( 'id', 'mail_recipients', 'mail_date', 'max_email_ID' );
	$array_fields = array( 'mail_settings' );

	foreach ( get_object_vars( $campaign_item ) as $name => $value ) {
		if ( in_array( $name, $int_fields ) )
			$value = intval( $value );

		if ( in_array( $name, $array_fields ) )
			$value = maybe_unserialize( $value );

		$campaign_item->$name = $value;
	}

	return $campaign_item;
}


function incsub_sbe_update_campaign( $campaign_id, $args ) {
	global $wpdb;

	$campaign = incsub_sbe_get_campaign( $campaign_id );
	if ( ! $campaign )
		return false;

	$fields = array( 'mail_subject' => '%s', 'mail_recipients' => '%d' );
	$update = array();
	$update_wildcards = array();

	foreach ( $fields as $field => $wildcard ) {
		if ( isset( $args[ $field ] ) ) {
			$update[ $field ] = $args[ $field ];
			$update_wildcards[] = $wildcard;
		}
	}

	if ( empty( $update ) )
		return false;

	$campaign_table = subscribe_by_email()->model->subscriptions_log_table;

	$result = $wpdb->update(
		$campaign_table,
		$update,
		array( 'id' => $campaign->id ),
		$update_wildcards,
		array( '%d' )
	);

	return $result;

}

function incsub_sbe_finish_campaign( $campaign_item ) {
	$campaign = incsub_sbe_get_campaign( $campaign_item );
	if ( ! $campaign )
		return false;

	$max_id = $campaign->get_total_emails_count();
	return incsub_sbe_update_campaign( $campaign->id, array( 'mail_recipients' => $max_id ) );
}