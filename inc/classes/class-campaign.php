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

class SBE_Campaign {

	public $id = 0;

	public $mail_subject = 0;

	public $mail_recipients = 0;

	public $mail_date = 0;

	public $mail_settings = array();

	public static function get_instance( $campaign_item ) {
		if ( is_object( $campaign_item ) )
			return new self( $campaign_item );

		$id = absint( $campaign_item );
		if ( ! $id )
			return false;

		$model = incsub_sbe_get_model();
		$campaign_item = $model->get_single_log( $id );

		if ( ! $campaign_item )
			return false;

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
			'status' => 'pending'
		);
		$return = incsub_sbe_get_queue_items( $args );
		return $return['items'];
	}

	public function get_campaign_sent_queue() {
		$args = array(
			'campaign_id' => $this->id,
			'per_page' => -1,
			'status' => 'sent'
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