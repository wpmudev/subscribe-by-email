<?php

class SBE_Digest_Sender {

	public $dummy = false;

	public function __construct( $dummy = false ) {
		$this->dummy = $dummy;
	}

	public function send_digest( $content, $subscriber = false, $queue_item = false ) {
		if ( ! is_array( $content ) || empty( $content ) )
			return 3;

		$this->add_wp_mail_filters();
		$this->content = $content;

		do_action( 'sbe_pre_send_emails', $this );

		if ( $this->dummy && is_email( $subscriber ) ) {
			// Test Email
			
			$key = '';
			$mail = $subscriber;

			$headers = array(
				'x-mailer-php' => "X-Mailer:PHP/".phpversion(),
				'reply-to' => "Reply-To: <$mail>",
			);

			$template = sbe_get_email_template( $this->content, false );
			$content = sbe_render_email_template( $template, false );
			$result = wp_mail( $mail, $template->get_subject(), $content, $headers );
		}
		elseif ( $subscriber && ! empty( $queue_item->campaign_id ) ) {
			// Campaign email

			$key = $subscriber->subscription_key;
			$mail = $subscriber->subscription_email;
			$subscriber_id = $subscriber->ID;

			incsub_sbe_increment_campaign_recipients( $queue_item->campaign_id );

			if ( empty( $key ) ) {
				$status = 2; // Empty key
				$this->remove_wp_mail_filters();
				return $status;
			}

			$user_content = $queue_item->get_subscriber_posts();

			if ( empty( $user_content ) ) {
				$status = 3; // Empty user content
				$this->remove_wp_mail_filters();
				return $status;
			}

			$template = sbe_get_email_template( $user_content, $subscriber );
			$content = sbe_render_email_template( $template, false );

			// Send!
			$unsubscribe_url = $template->get_unsubscribe_url();
			$headers = array(
				'x-mailer-php' => "X-Mailer:PHP/".phpversion(),
				'reply-to' => "Reply-To: <$mail>",
				'list-unsubscribe' => "List-Unsubscribe: <$unsubscribe_url>"
			);

			
			$headers = apply_filters( 'sbe_template_mail_headers', $headers, $mail, $subscriber_id, $queue_item->campaign_id );
			$headers = array_values( $headers );

			do_action( 'sbe_before_send_single_email', $user_content, $mail );
			$result = wp_mail( $mail, $template->get_subject(), $content, $headers );
			do_action( 'sbe_after_send_single_email', $user_content, $mail );

			if ( ! $result ) {
				$status = 4; // Error
				$this->remove_wp_mail_filters();
				return $status;
			}


			// Everything went fine
			$status = 1;
		}
		else {
			return 5; // Subscriber does not exist
		}


		do_action( 'sbe_after_send_emails' );
		$this->remove_wp_mail_filters();

		return 1; // Sent :D
	}

	private function add_wp_mail_filters() {
		add_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );
		add_filter( 'wp_mail_charset', array( &$this, 'set_mail_charset' ) );
		add_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' ), 99 );
		add_action( 'phpmailer_init', array( &$this, 'set_phpmailer_atts' )  );
	}

	private function remove_wp_mail_filters() {
		remove_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );
		remove_filter( 'wp_mail_charset', array( &$this, 'set_mail_charset' ) );
		remove_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' ), 99 );
		remove_action( 'phpmailer_init', array( &$this, 'set_phpmailer_atts' )  );
	}

}