<?php

/**
 * @group digests
 * @group weekly
 */
class SBE_Weekly_Digest_Tests extends SBE_UnitTestCase {


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
		$settings['frequency'] = 'weekly';
		incsub_sbe_update_settings( $settings );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );

		$args = $this->factory->post->generate_args();
		$args['post_date'] = current_time( 'mysql' );
		$post_id_now = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-6 day', current_time( 'timestamp' ) ) );
		$post_id_6_days_ago = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-8 day', current_time( 'timestamp' ) ) );
		$post_id_dont_send = $this->factory->post->create_object( $args );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );


		update_option( 'next_week_scheduled', current_time( 'timestamp' ) - 5 );
		subscribe_by_email()->process_scheduled_subscriptions();

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 1, $campaigns['count'] );

		$campaign = $campaigns['items'][0];
		$this->assertContains( $post_id_now, $campaign->mail_settings['posts_ids'] );
		$this->assertContains( $post_id_6_days_ago, $campaign->mail_settings['posts_ids'] );
		$this->assertNotContains( $post_id_dont_send, $campaign->mail_settings['posts_ids'] );

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );

		foreach ( $all_queue as $queue_item ) {
			$subscriber = incsub_sbe_get_subscriber( $queue_item->subscriber_email );
			$this->assertTrue( $subscriber->is_confirmed() );
		}

		$pending_queue = $campaign->get_campaign_queue();
		$this->assertCount( 3, $pending_queue );

		$sent_queue = $campaign->get_campaign_sent_queue();
		$this->assertCount( 0, $sent_queue );

		$this->assertEquals( '1', get_post_meta( $post_id_now, 'sbe_sent', true ) );
		$this->assertEquals( '1', get_post_meta( $post_id_6_days_ago, 'sbe_sent', true ) );
		$this->assertEmpty( get_post_meta( $post_id_dont_send, 'sbe_sent', true ) );
	}

	function test_enqueue_emails_with_empty_subscribers() {
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'weekly';
		incsub_sbe_update_settings( $settings );

		$args = $this->factory->post->generate_args();
		$args['post_date'] = current_time( 'mysql' );
		$post_id_now = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-6 day', current_time( 'timestamp' ) ) );
		$post_id_6_days_ago = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-8 day', current_time( 'timestamp' ) ) );
		$post_id_dont_send = $this->factory->post->create_object( $args );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );

		update_option( 'next_week_scheduled', current_time( 'timestamp' ) - 5 );
		subscribe_by_email()->process_scheduled_subscriptions();

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 0, $campaigns['count'] );
	}

	function test_send_emails() {
		$this->insert_subscribers();

		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'weekly';
		$settings['mails_batch_size'] = 1;
		incsub_sbe_update_settings( $settings );
		
		$args = $this->factory->post->generate_args();
		$args['post_date'] = current_time( 'mysql' );
		$post_id_now = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-6 day', current_time( 'timestamp' ) ) );
		$post_id_6_days_ago = $this->factory->post->create_object( $args );

		$args['post_date'] = date( 'Y-m-d H:i:s', strtotime( '-8 day', current_time( 'timestamp' ) ) );
		$post_id_dont_send = $this->factory->post->create_object( $args );

		update_option( 'next_week_scheduled', current_time( 'timestamp' ) - 5 );
		subscribe_by_email()->process_scheduled_subscriptions();
		
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

