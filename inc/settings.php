<?php

class Incsub_Subscribe_By_Email_Settings_Handler {

	static $instance;

	// Settings of the plugin
	private $settings = array();
	private $default_settings;

	// Settings properties
	private $frequency;
	private $time;
	private $day_of_week;
	private $confirmation_flag;

	// Settings slug name
	private $settings_slug = 'incsub_sbe_settings';

	public function __construct() {
		add_action( 'admin_init', array( &$this, 'set_settings' ), 1 );
	}


	/**
	 * Singleton Pattern
	 * 
	 * Gets the instance of the class
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) )
			self::$instance = new self();
		return self::$instance;
	}

	/**
	 * Set the settings for the plugin
	 */
	public function set_settings() {

		global $wp_locale;


		$this->settings_slug = 'incsub_sbe_settings';

		// The user can choose between these options
		$this->frequency = array(
			'inmediately' 	=> __( 'Immediately when a new post is published', INCSUB_SBE_LANG_DOMAIN ),
			'weekly'		=> __( 'Send a weekly digest with all posts from the previous week', INCSUB_SBE_LANG_DOMAIN ),
			'daily'			=> __( 'Send a daily digest with all posts from the previous 24 hours', INCSUB_SBE_LANG_DOMAIN )
		);

		$this->time = array();
		for ( $i = 0; $i < 24; $i++ ) {
			$this->time[$i] = str_pad( $i, 2, 0, STR_PAD_LEFT ) . ':00';
		}

		$this->day_of_week = $wp_locale->weekday;

		$this->confirmation_flag = array(
			0 => __( 'Awaiting confirmation'),
			1 => __( 'Email confirmed')
		);

		$current_settings = get_option( $this->settings_slug );		

		$this->settings = wp_parse_args( $current_settings, $this->get_default_settings() );

	}

	public function get_settings() {
		if ( empty( $this->settings ) )
			$this->set_settings();

		return $this->settings;
	}

	public function update_settings( $settings ) {
		$this->settings = $settings;

		update_option( $this->settings_slug, $settings );
	}

	public function get_default_settings() {

		if ( is_multisite() ) {
			$site_url = get_home_url( get_current_blog_id() );
		}
		else {
			$site_url = get_home_url();
		}

		$base_domain = str_replace( 'http://', '', $site_url );
		$base_domain = str_replace( 'https://', '', $base_domain );

		$subscribe_email_content = __( 'Howdy.

You recently signed up to be notified of new posts on my blog. This means
once you confirm below, you will receive an email when posts are published.

To activate, click confirm below. If you believe this is an error, ignore this message
and nothing more will happen.', INCSUB_SBE_LANG_DOMAIN );

		return array(
			'auto-subscribe' => false,
			'subscribe_new_users' => false,
			'from_email' => 'no-reply@' . $base_domain,
			'from_sender' => get_bloginfo( 'name' ),
			'subject' => get_bloginfo( 'name' ) . __( ': New post' ),
			'frequency' => 'inmediately',
			'time' => 0,
			'day_of_week' => 0,
			'post_types' => array( 'post' ),
			'manage_subs_page' => 0,
			'logo' => '',
			'featured_image' => false,
			'header_text' => '',
			'footer_text' => '',
			'header_color' => '#66aec2',
			'header_text_color' => '#000000',
			'mails_batch_size' => 80,
			'subscribe_email_content' => $subscribe_email_content
		);
	}

	/**
	 * Get the settings slug name
	 * 
	 * @return String
	 */
	public function get_settings_slug() {
		return $this->settings_slug;
	}

	public function get_frequency() {
		return $this->frequency;
	}

	public function get_time() {
		return $this->time;
	}

	public function get_day_of_week() {
		return $this->day_of_week;
	}

	public function get_confirmation_flag() {
		return $this->confirmation_flag;
	}

}