<?php

/**
 * @group campaign
 */
class SBE_Campaign_Tests extends SBE_UnitTestCase {

	function test_insert_campaign() {
		$campaign_id = incsub_sbe_insert_campaign( 'The subject' );

		// There are not subscribers, campaign ID should be false
		$this->assertFalse( $campaign_id );

		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id = incsub_sbe_insert_campaign( 'The subject' );
		$campaign = incsub_sbe_get_campaign( $campaign_id );

		$this->assertEquals( 3, $campaign->get_total_emails_count() );
		$this->assertEquals( 'pending', $campaign->get_status() );

		// Nothing queued yet
		$this->assertCount( 0, $campaign->get_campaign_queue() );
		$this->assertCount( 0, $campaign->get_campaign_all_queue() );
		$this->assertCount( 0, $campaign->get_campaign_sent_queue() );
		$this->assertEquals( 'The subject', $campaign->mail_subject );
		$this->assertEquals( $confirmed_subscriber_id_4, $campaign->max_email_ID );
	}

	function test_get_campaigns() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id_1 = incsub_sbe_insert_campaign( 'The subject' );
		$campaign_id_2 = incsub_sbe_insert_campaign( 'The subject' );

		$campaigns = incsub_sbe_get_campaigns();

		$this->assertCount( 2, $campaigns['items'] );
		$this->assertEquals( 2, $campaigns['count'] );
	}

	function test_delete_campaign() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id_1 = incsub_sbe_insert_campaign( 'The subject' );
		incsub_sbe_delete_campaign( $campaign_id_1 );
		$campaign = incsub_sbe_get_campaign( $campaign_id_1 );

		$this->assertFalse( $campaign );
	}

	function test_update_campaign() {

	}

	function test_increment_mail_recipients() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id = incsub_sbe_insert_campaign( 'The subject' );
		$campaign = incsub_sbe_get_campaign( $campaign_id );
		
		$this->assertEquals( 0, $campaign->mail_recipients );
		incsub_sbe_increment_campaign_recipients( $campaign_id );

		$campaign = incsub_sbe_get_campaign( $campaign_id );
		$this->assertEquals( 1, $campaign->mail_recipients );
	}

	function test_get_sent_campaigns() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id_1 = incsub_sbe_insert_campaign( 'The subject' );
		$campaign_id_2 = incsub_sbe_insert_campaign( 'The subject' );

		$campaigns = incsub_sbe_get_campaigns();

		$this->assertCount( 2, $campaigns['items'] );
		$this->assertEquals( 2, $campaigns['count'] );
	}

	function test_get_subscribers_list() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$campaign_id = incsub_sbe_insert_campaign( 'The subject' );
		$campaign = incsub_sbe_get_campaign( $campaign_id );
		$this->assertCount( 3,  $campaign->get_subscribers_list() );

		foreach ( $campaign->get_subscribers_list() as $subscriber ) {
			$this->assertTrue( $subscriber->is_confirmed() );
		}

	}
	
}

