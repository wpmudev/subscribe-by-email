<?php

/**
 * @group post_types
 */
class SBE_Digests_Post_Types_Tests extends SBE_UnitTestCase {

	function insert_subscribers() {
		$confirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test1@email.com', true );
		$unconfirmed_subscriber_id_1 = incsub_sbe_insert_subscriber( 'test4@email.com', false );
		$confirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test5@email.com', true );
		$confirmed_subscriber_id_4 = incsub_sbe_insert_subscriber( 'test3@email.com', true );
		$unconfirmed_subscriber_id_2 = incsub_sbe_insert_subscriber( 'test0@email.com', false );
	}

	/**
	 * http://premium.wpmudev.org/forums/topic/subscribe-by-email-not-sending-notifications
	 * Send post types with no taxonomies
	 */
	function test_no_taxonomy_post_type() {
	
		register_post_type( 'no-tax-cpt' );

		$this->insert_subscribers();
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['post_types'] = array( 'no-tax-cpt' );
		$settings['mails_batch_size'] = 1;
		incsub_sbe_update_settings( $settings );

		$args = $this->factory->post->generate_args();
		$args['post_type'] = 'no-tax-cpt';
		$post_id = $this->factory->post->create_object( $args );

		$campaigns = incsub_sbe_get_campaigns();

		$this->assertEquals( 1, $campaigns['count'] );
		$campaign = $campaigns['items'][0];

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );

		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'finished' );

		$queue = $campaign->get_campaign_all_queue();
		foreach ( $queue as $item )
			$this->assertEquals( 1, $item->sent_status );
		
	}

	/**
	 * If no taxonomy is selected for a post type, the post should be sent
	 */
	function test_post_type_with_no_taxonomy_selected() {
		register_post_type( 'test-cpt' );
		register_taxonomy( 'test-tax', array( 'test-cpt' ) );

		$this->insert_subscribers();
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['post_types'] = array( 'test-cpt' );
		$settings['taxonomies']['test-cpt']['test-tax'] = array( 'all' );
		$settings['mails_batch_size'] = 1;
		incsub_sbe_update_settings( $settings );

		$args = $this->factory->post->generate_args();
		$args['post_type'] = 'test-cpt';
		$post_id = $this->factory->post->create_object( $args );
		
		$campaigns = incsub_sbe_get_campaigns();

		$this->assertEquals( 1, $campaigns['count'] );
		$campaign = $campaigns['items'][0];

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );

		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'finished' );

		$queue = $campaign->get_campaign_all_queue();
		foreach ( $queue as $item )
			$this->assertEquals( 1, $item->sent_status );

	}

	function test_post_type_with_taxonomy_selected() {
		register_post_type( 'test-cpt' );
		register_taxonomy( 'test-tax', array( 'test-cpt' ) );

		// Create one tax that should be sent
		$args = $this->factory->term->generate_args();
		$args['taxonomy'] = 'test-tax';
		$term_id_yes = $this->factory->term->create_object( $args );
		
		// Create another tax that should NOT be sent
		$args = $this->factory->term->generate_args();
		$args['taxonomy'] = 'test-tax';
		$term_id_no = $this->factory->term->create_object( $args );

		$this->insert_subscribers();
		$settings = incsub_sbe_get_settings();
		$settings['frequency'] = 'inmediately';
		$settings['post_types'] = array( 'test-cpt' );
		$settings['taxonomies']['test-cpt']['test-tax'] = array( $term_id_yes );
		$settings['mails_batch_size'] = 1;
		incsub_sbe_update_settings( $settings );

		$args = $this->factory->post->generate_args();
		$args['post_type'] = 'test-cpt';
		$args['post_status'] = 'draft';
		$post_id = $this->factory->post->create_object( $args );
		
		wp_set_object_terms( $post_id, array( $term_id_no ), 'test-tax' );
		wp_publish_post( $post_id );

		$campaigns = incsub_sbe_get_campaigns();

		// There should be no campaigns for that term
		$this->assertEmpty($campaigns['count'] );
		
		// Now insert a post with the correct term
		$args = $this->factory->post->generate_args();
		$args['post_type'] = 'test-cpt';
		$args['post_status'] = 'draft';
		$post_id = $this->factory->post->create_object( $args );

		wp_set_object_terms( $post_id, array( $term_id_yes ), 'test-tax' );
		wp_publish_post( $post_id );

		$campaigns = incsub_sbe_get_campaigns();
		$this->assertEquals( 1, $campaigns['count'] );

		$campaign = $campaigns['items'][0];

		$all_queue = $campaign->get_campaign_all_queue();
		$this->assertCount( 3, $all_queue );

		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		delete_transient( 'sbe_pending_mails_sent' );
		$result = subscribe_by_email()->maybe_send_pending_emails();
		$this->assertCount( 1, $result );
		$this->assertEquals( $campaign->get_status(), 'pending' );

		$campaign->refresh_campaign_status();
		$this->assertEquals( $campaign->get_status(), 'finished' );

		$queue = $campaign->get_campaign_all_queue();
		foreach ( $queue as $item )
			$this->assertEquals( 1, $item->sent_status );

	}
	
}

