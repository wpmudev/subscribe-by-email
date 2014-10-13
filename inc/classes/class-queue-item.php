<?php

function incsub_sbe_get_queue_item( $queue_item ) {
	return SBE_Queue_Item::get_instance( $queue_item );
}

function incsub_sbe_get_queue_items( $args ) {
	$model = incsub_sbe_get_model();
	$results = $model->get_queue_items( $args );
 
	$return = array();
	$return['items'] = array();
	$return['count'] = 0;
	foreach ( $results['items'] as $item )
		$return['items'][] = incsub_sbe_get_queue_item( $item );

	if ( isset( $results['count'] ) )
		$return['count'] = $results['count'];

	return $return;

}

class SBE_Queue_Item {

	public $id = 0;

	public $blog_id = 0;

	public $subscriber_email = '';

	public $campaign_id = 0;

	public $sent = false;

	public $sent_status = null;

	public $campaign_settings = array();

	private $posts = array();


	public static function get_instance( $queue_item ) {
		
		if ( is_object( $queue_item ) )
			return new self( $queue_item );

		$id = absint( $queue_item );
		if ( ! $id )
			return false;

		$model = incsub_sbe_get_model();
		$queue_item = $model->get_queue_item( $id );

		if ( ! $queue_item )
			return false;

		return new self( $queue_item );
		
	}

	public function __construct( $queue ) {
		foreach ( get_object_vars( $queue ) as $key => $value )
			$this->$key = $value;

		$posts_ids = isset( $this->campaign_settings['posts_ids'] ) ? $this->campaign_settings['posts_ids'] : array();

		$this->posts = array();
		if ( ! empty( $posts_ids ) ) {
			$this->posts = get_posts( array(
				'posts_per_page' => -1,
				'ignore_sticky_posts' => true,
				'post__in' => $posts_ids,
				'post_type' => 'any'
			) );
		}

	}

	public function get_queue_item_posts() {
		return $this->posts;
	}

	public function get_subscriber_posts() {
		$subscriber = incsub_sbe_get_subscriber( $this->subscriber_email );

		$settings = incsub_sbe_get_settings();
		$posts = array();
		if ( $subscriber ) {
			foreach ( $this->posts as $post ) {
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