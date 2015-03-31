<?php

class SBE_Subscriber_Tests extends SBE_UnitTestCase {

	function test_insert_subscriber() {
		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		$subscriber = incsub_sbe_get_subscriber( $subscriber_id );

		$this->assertFalse( $subscriber->is_confirmed() );
		$this->assertEquals( $subscriber->subscription_email, 'test@email.com' );
		$this->assertEquals( $subscriber->subscription_note, 'A note' );
		$this->assertEquals( $subscriber->a_meta_sample, 'meta_value' );

		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		$this->assertFalse( $subscriber_id );
	}

	function test_delete_subscriber() {
		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		incsub_sbe_cancel_subscription( $subscriber_id, true );

		$subscriber = incsub_sbe_get_subscriber( $subscriber_id );
		$this->assertFalse( $subscriber );	
	}

	function test_get_subscribers() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$args = array(
			'per_page' => 4
		);
		$subscribers = incsub_sbe_get_subscribers( $args );

		$this->assertCount( 4, $subscribers->subscribers );
		$this->assertEquals( 5, $subscribers->total );

		$args = array(
			'status' => 'publish'
		);
		$subscribers = incsub_sbe_get_subscribers( $args );
		$this->assertCount( 3, $subscribers->subscribers );
		
	}

	function test_update_subscriber() {
		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		incsub_sbe_update_subscriber( $subscriber_id, array( 'email' => 'another@example.com', 'a_meta_sample' => 'new_meta_value' ) );
		$subscriber = incsub_sbe_get_subscriber( $subscriber_id );

		$this->assertEquals( $subscriber->subscription_email, 'another@example.com' );
		$this->assertEquals( $subscriber->subscription_note, 'A note' );
		$this->assertEquals( $subscriber->a_meta_sample, 'new_meta_value' );
	}

	function test_confirm_subscriber() {
		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		$subscriber = incsub_sbe_get_subscriber( $subscriber_id );

		$this->assertFalse( $subscriber->is_confirmed() );

		incsub_sbe_confirm_subscription( $subscriber_id );

		$this->assertTrue( $subscriber->is_confirmed() );
	}

	function test_get_subscriber_by_key() {
		$subscriber_id = incsub_sbe_insert_subscriber( 'test@email.com', false, array( 'note' => 'A note', 'meta' => array( 'a_meta_sample' => 'meta_value' ) ) );
		$subscriber = incsub_sbe_get_subscriber( $subscriber_id );
		$subscriber_key = $subscriber->subscription_key;

		$subscriber = incsub_sbe_get_subscriber_by_key( $subscriber_key );

		$this->assertInstanceOf( 'SBE_Subscriber', $subscriber );
	}

	function test_get_subscribers_count() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test0@email.com', false );

		$count = incsub_sbe_get_subscribers_count();
		$this->assertEquals( 5, $count );

		$count = incsub_sbe_get_subscribers_count( $confirmed_subscriber_id_2 );
		$this->assertEquals( 2, $count );
	}
}

