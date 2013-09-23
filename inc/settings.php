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
	private $post_types;
	private $taxonomies;

	// Settings slug name
	private $settings_slug = 'incsub_sbe_settings';

	public function __construct() {
		$this->set_settings();
		add_action( 'init', array( &$this, 'set_settings' ) );
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
			'inmediately' 	=> __( 'Inmediately when a new post is published', INCSUB_SBE_LANG_DOMAIN ),
			'weekly'		=> __( 'Send a weekly digest with all posts from the previous week', INCSUB_SBE_LANG_DOMAIN ),
			'daily'			=> __( 'Send a daily digest with all posts from the previous 24 hours', INCSUB_SBE_LANG_DOMAIN ),
			'never'			=> __( 'Never', INCSUB_SBE_LANG_DOMAIN )
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

		// Get all post types
		$args = array(
		  'publicly_queryable'   => true,
		); 
		$this->post_types = get_post_types( $args, 'object' );
		unset( $this->post_types['attachment'] );

		// Get those taxonomies that are hierachical
		$this->taxonomies = array();
		foreach ( $this->post_types as $post_slug => $post_type ) {
			$post_type_taxonomies = get_object_taxonomies( $post_slug );

			if ( ! empty( $post_type_taxonomies ) ) {
				foreach ( $post_type_taxonomies as $taxonomy_slug ) {
					$taxonomy = get_taxonomy( $taxonomy_slug );

					if ( $taxonomy->hierarchical ) {
						$this->taxonomies[ $post_slug ][ $taxonomy_slug ] = $taxonomy;
					}
				}
				
			}

		}

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

		global $current_site;			

		if ( is_multisite() && is_subdomain_install() ) {
			$blog_details = get_blog_details();
			$base_domain = $blog_details->domain;
		}
		elseif ( is_multisite() && ! is_subdomain_install() ) {
			$blog_details = get_blog_details();
			$base_domain = $blog_details->path;
			$base_domain = preg_replace( '/^\//', '', $base_domain );
			$base_domain = preg_replace( '/\/$/', '', $base_domain );
			$base_domain = str_replace( '/', '.', $base_domain );
		}
		else {
			$base_domain = get_bloginfo('wpurl');
			$base_domain = str_replace( 'http://', '', $base_domain );
			$base_domain = preg_replace( '/^\//', '', $base_domain );
			$base_domain = preg_replace( '/\/$/', '', $base_domain );
			$base_domain = str_replace( '/', '.', $base_domain );
			$base_domain = str_replace( 'www.', '', $base_domain );
		}

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
			'manage_subs_page' => 0,
			'get_notifications' => false,
			'get_notifications_role' => 'administrator',
			'follow_button' => false,

			'post_types' => array( 'post' ),
			'taxonomies' => array( 'post' => array( 'category' => array( 'all' ) ) ),

			'logo' => '',
			'logo_width' => 200,
			'featured_image' => false,
			'header_text' => '',
			'show_blog_name' => true,
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

	public function get_post_types() {
		return $this->post_types;
	}

	public function get_taxonomies() {
		return $this->taxonomies;
	}

	public function get_taxonomies_by_post_type( $post_type_slug ) {
		return isset( $this->taxonomies[ $post_type_slug ] ) ? $this->taxonomies[ $post_type_slug ] : array();
	}

	public function get_selected_taxonomies_by_post_type( $post_type_slug ) {
		$taxonomies = array();
		if ( isset( $this->settings['taxonomies'][ $post_type_slug ] ) && in_array( 'all', $this->settings['taxonomies'][ $post_type_slug ] ) )
			return $this->get_taxonomies_by_post_type( $post_type_slug );
	}

}