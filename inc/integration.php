<?php

// HTML Email Templates integration
if ( class_exists( 'HTML_emailer' ) ) {
	add_action( 'sbe_pre_send_emails', 'sbe_remove_html_email_templates_hooks' );
	add_action( 'sbe_after_send_emails', 'sbe_remove_html_email_templates_hooks' );
	
	function sbe_remove_html_email_templates_hooks() {
		remove_filter( 'wp_mail', array( 'HTML_emailer', 'wp_mail' ) );
		remove_action( 'phpmailer_init', array( &$this,'convert_plain_text' ) );	
	}

	function sbe_restore_html_email_templates_hooks() {
		add_filter( 'wp_mail', array( 'HTML_emailer', 'wp_mail' ) );
		add_action( 'phpmailer_init', array( &$this,'convert_plain_text' ) );	
	}
	
}