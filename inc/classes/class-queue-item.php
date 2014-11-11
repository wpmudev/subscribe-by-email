<?php

function incsub_sbe_get_queue_item( $queue_item ) {
	return SBE_Queue_Item::get_instance( $queue_item );
}

function incsub_sbe_get_queue_items( $args ) {
	global $wpdb;

	$defaults = array(
        'campaign_id' => false,
        'page' => 1,
        'per_page' => 30,
        'blog_id' => get_current_blog_id(),
        'count' => false,
        'status' => 0,
        'orderby' => 'id',
        'order' => 'asc'
    );

    $args = wp_parse_args( $args, $defaults );

    // WHERE
	$where = array( "1 = 1" );
    if ( $args['blog_id'] )
    	$where[] = $wpdb->prepare( "blog_id = %d", $args['blog_id'] );

    if ( $args['campaign_id'] )
    	$where[] = $wpdb->prepare( "campaign_id = %d", $args['campaign_id'] );

    if ( $args['status'] === 'pending' )
        $where[] = "sent_status = 0";
    elseif ( $args['status'] === 'sent' )
        $where[] = "sent_status != 0";
    elseif ( $args['status'] !== 'all' )
        $where[] = $wpdb->prepare( "sent_status = %d", $args['status'] );

    $where = implode( " AND ", $where );

    // ORDER
    $allowed_orderby = array( 'id', 'blog_id', 'campaign_id' );
    $allowed_order = array( 'ASC', 'DESC' );
    $order = '';
    if ( in_array( $args['orderby'], $allowed_orderby ) && in_array( strtoupper( $args['order'] ), $allowed_order ) ) {
    	$orderby = $args['orderby'];
    	$order_field = strtoupper( $args['order'] );
    	$order = "ORDER BY $orderby $order_field, id";
    }

    // LIMIT
    $limit = '';
    if ( $args['per_page'] > -1 )
    	$limit = $wpdb->prepare( " LIMIT %d, %d", intval( ( $args['page'] - 1 ) * $args['per_page'] ), intval( $args['per_page'] ) );

    //RESULTS
    $where = apply_filters( 'sbe_get_queue_items_where', $where, $args );
    $order = apply_filters( 'sbe_get_queue_items_order', $order, $args );
    $limit = apply_filters( 'sbe_get_queue_items_limit', $limit, $args );

    $table = subscribe_by_email()->model->subscriptions_queue_table;
    $query = "SELECT * FROM $table WHERE $where $order $limit";

    if ( $args['count'] ) {
        $query_count = str_replace( '*', 'COUNT(id)', $query );
        $items_count = $wpdb->get_var( $query_count );
    }
    else {
        $items_count = 0;
    }

    $results = $wpdb->get_results( $query );

	$return = array();
	$return['items'] = array();
	$return['count'] = 0;
	foreach ( $results as $item )
		$return['items'][] = incsub_sbe_get_queue_item( $item );

	$return['count'] = absint( $items_count );

	return $return;

}

function incsub_sbe_update_queue_item( $id, $args = array() ) {
	global $wpdb;

	$queue_item = incsub_sbe_get_queue_item( $id );
	if ( ! $queue_item )
		return false;

	$fields = array( 'campaign_settings' => '%s', 'sent' => '%d', 'sent_status' => '%d' );

	if ( isset( $args['campaign_settings'] ) )
		$args['campaign_settings'] = maybe_serialize( $args['campaign_settings'] );

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

	$table = subscribe_by_email()->model->subscriptions_queue_table;
	$wpdb->update(
		$table,
		$update,
		array( 'id' => $id ),
		$update_wildcards,
		array( '%d' )
	);

	return true;
}

function incsub_sbe_set_queue_item_sent_status( $id, $status ) {
	$args = array(
		'sent_status' => absint( $status ),
		'sent' => current_time( 'timestamp' )
	);

	return incsub_sbe_update_queue_item( $id, $args );
}

function incsub_sbe_delete_queue_item( $id ) {
	global $wpdb;

	$table = subscribe_by_email()->model->subscriptions_queue_table;
	$query = $wpdb->prepare( "DELETE FROM $table WHERE id = %d", $id );
	$wpdb->query( $query );
}


function incsub_sbe_sanitize_queue_item_fields( $queue_item ) {
	$int_fields = array( 'id', 'blog_id', 'campaign_id', 'sent', 'sent_status' );
	$array_fields = array( 'campaign_settings' );

	foreach ( get_object_vars( $queue_item ) as $name => $value ) {
		if ( in_array( $name, $int_fields ) )
			$value = intval( $value );

		if ( in_array( $name, $array_fields ) )
			$value = maybe_unserialize( $value );

		$queue_item->$name = $value;
	}

	return $queue_item;
}

class SBE_Queue_Item {

	public $id = 0;

	public $blog_id = 0;

	public $subscriber_email = '';

	public $campaign_id = 0;

	public $sent = false;

	public $sent_status = null;

	public $campaign_settings = array();

	private $posts = null;


	public static function get_instance( $queue_item ) {
		global $wpdb;
		
		if ( is_object( $queue_item ) ) {
			$queue_item = incsub_sbe_sanitize_queue_item_fields( $queue_item );
			return new self( $queue_item );
		}

		$id = absint( $queue_item );
		if ( ! $id )
			return false;

		$table = subscribe_by_email()->model->subscriptions_queue_table;
		$queue_item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

		if ( ! $queue_item )
			return false;

		$queue_item = incsub_sbe_sanitize_queue_item_fields( $queue_item );

		return new self( $queue_item );
		
	}

	public function __construct( $queue ) {
		foreach ( get_object_vars( $queue ) as $key => $value )
			$this->$key = $value;
	}


	public function get_queue_item_posts() {
		if ( ! is_array( $this->posts ) ) {
			$posts_ids = isset( $this->campaign_settings['posts_ids'] ) ? $this->campaign_settings['posts_ids'] : array();
			$settings = incsub_sbe_get_settings();
			$this->posts = array();
			if ( ! empty( $posts_ids ) ) {
				$this->posts = get_posts( array(
					'posts_per_page' => -1,
					'ignore_sticky_posts' => true,
					'post__in' => $posts_ids,
					'post_type' => $settings['post_types']
				) );
			}
		}

		return $this->posts;
	}

	public function get_subscriber_posts() {
		$subscriber = incsub_sbe_get_subscriber( $this->subscriber_email );

		$settings = incsub_sbe_get_settings();
		$posts = array();
		if ( $subscriber ) {
			foreach ( $this->get_queue_item_posts() as $post ) {
				$post_types = $subscriber->subscription_post_types;
				if ( false === $post_types )
					$post_types = $settings['post_types'];

				if ( in_array( $post->post_type, $post_types ) )
					$posts[] = $post;
			}
			
		}

		return $posts;
	}


}