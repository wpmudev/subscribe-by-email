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
		$subscribers_link = Incsub_Subscribe_By_Email::$admin_subscribers_page->get_permalink();

		$message  = _x( 'Dear user,', 'Administrator notice sent when a new user is subscribed', INCSUB_SBE_LANG_DOMAIN ) . "\r\n\r\n";
		$message  .= sprintf ( __( 'A new user (%s) has confirmed his subscription to your subscribers list.', INCSUB_SBE_LANG_DOMAIN ), $this->subscriber_email ) . "\r\n\r\n";
		$message  .= __( 'Click this link to manage your subscribers:', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$message  .= $subscribers_link . "\r\n\r\n";
		$message  .= _x( 'Regards,', 'Administrator notice sent when a new user is subscribed', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$message  .= get_option( 'blogname' );

		$admin_emails = $this->get_administrators_emails();

		foreach ( $admin_emails as $email )
			wp_mail( $email, $subject, $message );

	}
}



class Incsub_Subscribe_By_Email_Administrators_Unsubscribed_Notice_Template extends Incsub_Subscribe_By_Email_Administrators_Notice_Template  {
	public function send_email() {
		$subject = sprintf( __( '[%s] Subscriber removed', INCSUB_SBE_LANG_DOMAIN ), get_option( 'blogname' ) );
		$subscribers_link = Incsub_Subscribe_By_Email::$admin_subscribers_page->get_permalink();

		$message  = _x( 'Dear user,', 'Administrator notice sent when a new user is unsubscribed', INCSUB_SBE_LANG_DOMAIN ) . "\r\n\r\n";
		$message  .= sprintf ( __( 'A new user (%s) has removed from your subcribers list.', INCSUB_SBE_LANG_DOMAIN ), $this->subscriber_email ) . "\r\n\r\n";
		$message  .= __( 'Click this link to manage your subscribers:', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$message  .= $subscribers_link . "\r\n\r\n";
		$message  .= _x( 'Regards,', 'Administrator notice sent when a new user is unsubscribed', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$message  .= get_option( 'blogname' );

		$admin_emails = $this->get_administrators_emails();

		foreach ( $admin_emails as $email )
			wp_mail( $email, $subject, $message );

	}
}