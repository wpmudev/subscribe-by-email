<?php

/**
 * @group queue_item
 */
class SBE_Queue_Item_Tests extends SBE_UnitTestCase {

	function insert_post() {
		
	}

	function insert_campaign( $post_id, $campaign_settings ) {

		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		
		return incsub_sbe_insert_campaign( 'The subject', $campaign_settings );
	}

	function test_insert_queue_items() {
		$post_id = $this->factory->post->create_object(
			$this->factory->post->generate_args()
		);
		$campaign_settings = array( 'posts_ids' => array( $post_id ) );

		$campaign_id = $this->insert_campaign( $post_id, $campaign_settings );

		$campaign = incsub_sbe_get_campaign( $campaign_id );

		$result = incsub_sbe_insert_queue_items( $campaign_id );
		$this->assertTrue( $result );

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );
		foreach ( $all_queue as $queue_item ) {
			$this->assertEquals( is_email( $queue_item->subscriber_email ), $queue_item->subscriber_email );
			$this->assertEquals( $campaign_settings, $queue_item->campaign_settings );
			$this->assertEquals( 0, $queue_item->sent );
			$this->assertCount( 1, $queue_item->get_queue_item_posts() );
			$this->assertCount( 1, $queue_item->get_subscriber_posts() );
			$this->assertInstanceOf( 'SBE_Queue_Item', $queue_item );
		}

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 3, $pending_queue );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 0, $sent_queue );

	}

	function test_send_queue_items() {
		$post_id = $this->factory->post->create_object(
			$this->factory->post->generate_args()
		);
		$campaign_settings = array( 'posts_ids' => array( $post_id ) );

		$campaign_id = $this->insert_campaign( $post_id, $campaign_settings );
		$campaign = incsub_sbe_get_campaign( $campaign_id );

		incsub_sbe_insert_queue_items( $campaign_id );

		$all_queue = $campaign->get_campaign_all_queue();

		// Let's send one item
		$sent_queue_item_id = $all_queue[2]->id;
		incsub_sbe_set_queue_item_sent_status( $sent_queue_item_id, 1 );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 1, $sent_queue );

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 2, $pending_queue );

		// Now let's send an item with an error
		$sent_error_queue_item_id = $all_queue[1]->id;
		incsub_sbe_set_queue_item_sent_status( $sent_error_queue_item_id, 4 );
		incsub_sbe_set_queue_item_error_message( $sent_error_queue_item_id, 'This is an error message' );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 2, $sent_queue );

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 1, $pending_queue );

		$error_queue_item = incsub_sbe_get_queue_item( $sent_error_queue_item_id );
		$this->assertEquals( $error_queue_item->sent_status, 4 );
		$this->assertTrue( is_integer( $error_queue_item->sent ) );
		$this->assertEquals( $error_queue_item->error_msg, 'This is an error message' );

		// Let's delete the last queue item and finish the campaign
		incsub_sbe_delete_queue_item( $all_queue[0]->id );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 2, $sent_queue );

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 0, $pending_queue );

		$this->assertEquals( $campaign->get_status(), 'pending' );
		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'finished' );
	}
	
}

