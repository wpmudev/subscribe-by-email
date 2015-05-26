<?php

class SBE_Digest_Sender {

	public $dummy = false;

	public function __construct( $dummy = false ) {
		$this->dummy = $dummy;
	}

	public function send_digest( $content, $subscriber = false ) {
		if ( ! is_array( $content ) || empty( $content ) )
			return 3;

		$this->add_wp_mail_filters();

		do_action( 'sbe_pre_send_emails', $this );

		if ( $this->dummy && is_email( $subscriber ) ) {
			// Test Email
			
			$key = '';
			$mail = $subscriber;

			$headers = array(
				'x-mailer-php' => "X-Mailer:PHP/".phpversion(),
				'reply-to' => "Reply-To: <$mail>",
			);

			$template = sbe_get_email_template( $content, false );
			$this->subject = $template->get_subject();
			$email_content = sbe_render_email_template( $template, false );
			$result = wp_mail( $mail, $template->get_subject(), $email_content, $headers );
		}
		elseif ( is_object( $subscriber ) ) {
			// Campaign email

			$key = $subscriber->subscription_key;
			$mail = $subscriber->subscription_email;
			$subscriber_id = $subscriber->ID;

			if ( empty( $key ) ) {
				$status = 2; // Empty key
				$this->remove_wp_mail_filters();
				return $status;
			}

			if ( empty( $content ) ) {
				$status = 3; // Empty user content
				$this->remove_wp_mail_filters();
				return $status;
			}

			$template = sbe_get_email_template( $content, $subscriber );
			$this->subject = $template->get_subject();
			$email_content = sbe_render_email_template( $template, false );

			// Send!
			$unsubscribe_url = $template->get_unsubscribe_url();
			$headers = array(
				'x-mailer-php' => "X-Mailer:PHP/".phpversion(),
				'reply-to' => "Reply-To: <$mail>",
				'list-unsubscribe' => "List-Unsubscribe: <$unsubscribe_url>"
			);

			
			$headers = apply_filters( 'sbe_template_mail_headers', $headers, $mail, $subscriber_id, null );
			$headers = array_values( $headers );

			do_action( 'sbe_before_send_single_email', $content, $mail );
			$result = wp_mail( $mail, $template->get_subject(), $email_content, $headers );
			do_action( 'sbe_after_send_single_email', $content, $mail );

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

	public function set_mail_charset( $charset ) {
		return get_bloginfo( 'charset' );
	}

	function set_mail_from( $content_type ) {
		$settings = incsub_sbe_get_settings();
		return apply_filters( 'incsub_sbe_from_email', $settings['from_email'] );
	}

	function set_phpmailer_atts( $phpmailer ) {

		// Text template
		$phpmailer->AltBody = wp_specialchars_decode( $phpmailer->Body, ENT_QUOTES );

		$text_template = $this->get_text_template();
		$phpmailer->AltBody = str_replace( '%sbe_content%', $phpmailer->AltBody, $text_template );

		$phpmailer->AltBody = wp_kses( $phpmailer->AltBody, array() );

		$phpmailer->AltBody = preg_replace('/^[ \t]*[\r\n]+/m', '', $phpmailer->AltBody);
		$phpmailer->AltBody = preg_replace('/\t+/', '', $phpmailer->AltBody);
		$phpmailer->AltBody = trim( $phpmailer->AltBody );
		
		// HTML template
		$links_pattern = '#<(https?://[^*]+)>#';
		$phpmailer->Body = preg_replace( $links_pattern, '$1', $phpmailer->Body );

		$phpmailer->Body = make_clickable( $phpmailer->Body );

		$html_template = $this->get_html_template();
		$phpmailer->Body = str_replace( '%sbe_content%', $phpmailer->Body, $html_template );
	}

	function set_mail_from_name( $name ) {
		$settings = incsub_sbe_get_settings();
	  	return $settings['from_sender'];
	}

	function set_content_type( $content_type ) {
		return 'text/html';
	}

	function get_html_template() {
		ob_start();
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		    <head>
		        <meta http-equiv="Content-Type" content="text/html;<?php echo get_option('blog_charset'); ?>">
		        <title><?php echo esc_html( $this->subject ); ?></title>
		        
		    </head>
		    <body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin: 0;padding: 0;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;height: 100% !important;width: 100% !important;">
				%sbe_content%
		    </body>
		</html>
		<?php
		return ob_get_clean();
	}

	function get_text_template() {
		ob_start();
		?>
			%sbe_content%
		<?php
		return ob_get_clean();
	}


}