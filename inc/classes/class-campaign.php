<?php

function incsub_sbe_get_campaign( $campaign_item ) {
	return SBE_Campaign::get_instance( $campaign_item );
}

function incsub_sbe_get_campaigns( $args ) {
	$defaults = array(
		'current_page' => 1,
		'per_page' => 10,
		'sortable' => array(),
		'search' => false
	);

	$args = wp_parse_args( $args, $defaults );

	extract( $args );

	$model = incsub_sbe_get_model();
	$campaigns = $model->get_log( $current_page, $per_page, $sortable, $search );

	$return = array(
		'items' => array(),
		'count' => $campaigns['total']
	);
	foreach ( $campaigns['logs'] as $campaign ) {
		$campaign->mail_settings = maybe_unserialize( $campaign->mail_settings );
		$return['items'][] = incsub_sbe_get_campaign( $campaign );
	}

	return $return;
}

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

function incsub_sbe_get_sent_campaigns( $timestamp ) {
	$timestamp = absint( $timestamp );
	if ( ! $timestamp )
		return array();

	global $wpdb;

	$table = subscribe_by_email()->model->subscriptions_log_table;
    $results = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE mail_date < %d", $timestamp ) );

    $campaigns = array();
    foreach ( $results as $result ) {
    	$campaigns[] = incsub_sbe_get_campaign( $result );
    }

    return $campaigns;

}


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

class SBE_Campaign {

	public $id = 0;

	public $mail_subject = 0;

	public $mail_recipients = 0;

	public $mail_date = 0;

	public $mail_settings = array();

	public $max_email_ID = 0;

	public static function get_instance( $campaign_item ) {
		if ( is_object( $campaign_item ) ) {
			$campaign_item = incsub_sbe_sanitize_campaign_fields( $campaign_item );
			return new self( $campaign_item );
		}

		$id = absint( $campaign_item );
		if ( ! $id )
			return false;

		$model = incsub_sbe_get_model();
		$campaign_item = $model->get_single_log( $id );

		if ( ! $campaign_item )
			return false;

		$campaign_item = incsub_sbe_sanitize_campaign_fields( $campaign_item );

		return new self( $campaign_item );
		
	}

	public function __construct( $campaign_item ) {

		foreach ( get_object_vars( $campaign_item ) as $key => $value ) 
			$this->$key = $value;
		
	}

	public function get_total_emails_count() {
		$model = incsub_sbe_get_model();
		return $model->get_campaign_emails_list_count( $this->id );
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

	public function refresh_campaign_status() {
		$queue_items = $this->get_campaign_queue();
		

		if ( empty( $queue_items ) ) {
			$subscribers_count = $this->get_total_emails_count();
			$this->mail_recipients = $subscribers_count;
			$model = incsub_sbe_get_model();
			$model->update_mail_log_recipients( $this->id, $this->mail_recipients );
		}
	}


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

	$max_id = $campaign->max_email_ID;
	return incsub_sbe_update_campaign( $campaign->id, array( 'mail_recipients' => $max_id ) );
}