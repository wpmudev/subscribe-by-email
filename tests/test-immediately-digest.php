<?php

/**
 * @group digests
 * @group immediately
 */
class SBE_Immediately_Digest_Tests extends SBE_UnitTestCase {


	function insert_subscribers() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test0@email.com', false );
	}

	function test_enqueue_emails() {
		$this->insert_subscribers();
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['post_types'] = array( 'post' );
		incsub_sbe_update_settings( $settings );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );

		$post_id = $this->factory->post->create_object(
			$this->factory->post->generate_args()
		);
		
		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 1, $campaigns['count'] );

		$campaign = $campaigns['items'][0];

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );

		foreach ( $all_queue as $queue_item ) {
			$subscriber = incsub_sbe_get_subscriber( $queue_item->subscriber_email );
			$this->assertTrue( $subscriber->is_confirmed() );

			$this->assertEquals( $queue_item->campaign_settings, array( 'posts_ids' => array( $post_id ) ) );
		}

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 3, $pending_queue );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 0, $sent_queue );

		$this->assertEquals( '1', get_post_meta( $post_id, 'sbe_sent', true ) );
	}

	function test_enqueue_emails_with_empty_subscribers() {
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['post_types'] = array( 'post' );
		incsub_sbe_update_settings( $settings );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );

		$post_id = $this->factory->post->create_object(
			$this->factory->post->generate_args()
		);

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );
	}

	function test_send_emails() {
		$this->insert_subscribers();

		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['mails_batch_size'] = 1;
		$settings['post_types'] = array( 'post' );
		incsub_sbe_update_settings( $settings );

		$post_id = $this->factory->post->create_object(
			$this->factory->post->generate_args()
		);
		$campaigns = incsub_sbe_get_campaigns();
		$campaign = $campaigns['items'][0];

		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );


		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertFalse( $result );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'finished' );

	}

	
}

