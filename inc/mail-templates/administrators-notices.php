<?php

abstract class Incsub_Subscribe_By_Email_Administrators_Notice_Template {

	protected $subscriber_email;

	public function __construct( $email ) {
		$this->subscriber_email = $email;
	}

	protected function get_administrators_emails() {
		$settings = incsub_sbe_get_settings();
		$administrators = get_users( array( 'role' => $settings['get_notifications_role'] ) );
		$administrators = apply_filters( 'sbe_get_notification_users', $administrators );

		for ( $i = 0; $i < count( $administrators ); $i++ ) {
			$administrators[ $i ] = $administrators[ $i ]->data->user_email;
		}


		return array_unique( $administrators );
	}

	public abstract function send_email();
}



class Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template extends Incsub_Subscribe_By_Email_Administrators_Notice_Template  {
	public function send_email() {
		$subject = sprintf( __( '[%s] New Subscriber', INCSUB_SBE_LANG_DOMAIN ), get_option( 'blogname' ) );

		$content = __( 'Dear user,

A new user (###SUBSCRIBER_EMAIL###) has has confirmed its subscription to your subcribers list.

Click this link to manage your subscribers:
###SBE_SUBSCRIBERS_URL###

Regards,
###BLOGNAME###', INCSUB_SBE_LANG_DOMAIN );
		
		$subscribers_link = Incsub_Subscribe_By_Email::$admin_subscribers_page->get_permalink();

		$content = str_replace( '###SBE_SUBSCRIBERS_URL###', esc_url( $subscribers_link ), $content );
		$content = str_replace( '###BLOGNAME###', get_option( 'blogname' ), $content );
		$content = str_replace( '###SUBSCRIBER_EMAIL###', $this->subscriber_email, $content );

		$admin_emails = $this->get_administrators_emails();

		foreach ( $admin_emails as $email )
			wp_mail( $email, $subject, $content );

	}
}



class Incsub_Subscribe_By_Email_Administrators_Unsubscribed_Notice_Template extends Incsub_Subscribe_By_Email_Administrators_Notice_Template  {
	public function send_email() {
		$subject = sprintf( __( '[%s] Subscriber removed', INCSUB_SBE_LANG_DOMAIN ), get_option( 'blogname' ) );

		$content = __( 'Dear user,

A user (###SUBSCRIBER_EMAIL###) has removed from your subcribers list.

Click this link to manage your subscribers:
###SBE_SUBSCRIBERS_URL###

Regards,
###BLOGNAME###', INCSUB_SBE_LANG_DOMAIN );
		
		$subscribers_link = Incsub_Subscribe_By_Email::$admin_subscribers_page->get_permalink();

		$content = str_replace( '###SBE_SUBSCRIBERS_URL###', esc_url( $subscribers_link ), $content );
		$content = str_replace( '###BLOGNAME###', get_option( 'blogname' ), $content );
		$content = str_replace( '###SUBSCRIBER_EMAIL###', $this->subscriber_email, $content );

		$admin_emails = $this->get_administrators_emails();

		foreach ( $admin_emails as $email )
			wp_mail( $email, $subject, $content );

	}
}