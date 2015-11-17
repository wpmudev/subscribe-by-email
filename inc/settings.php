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
	private $follow_button_schemas;

	// Settings slug name
	private $settings_slug = 'incsub_sbe_settings';
	private $settings_network_slug = 'incsub_sbe_network_settings';

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
			'inmediately' 	=> __( 'Immediately when a new post is published', INCSUB_SBE_LANG_DOMAIN ),
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

		$current_settings = $this->get_blog_settings();


		if ( is_multisite() ) {
			$current_network_settings = $this->get_network_settings();
			$current_settings = array_merge( $current_settings, $current_network_settings );
		}

		$this->settings = wp_parse_args( $current_settings, $this->get_default_settings() );

		// Get all post types
		$args = array(
		  'publicly_queryable'   => true,
		); 
		$this->post_types = get_post_types( $args, 'object' );
		unset( $this->post_types['attachment'] );
		$this->post_types = apply_filters( 'sbe_get_post_types', $this->post_types );

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

		$this->extra_field_types = array( 
			'text' => array(
				'name' => __( 'Text field', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'text'
			), 
			'checkbox' => array(
				'name' => __( 'Checkbox', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'checkbox'
			)
		);

		$this->follow_button_schemas = array( 
			'dark' => array(
				'label' => __( 'Dark', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'dark'
			),
			'light' => array(
				'label' => __( 'Light', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'light' 
			) 
		);

		$this->follow_button_positions = array( 
			'bottom' => array(
				'label' => __( 'Bottom', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'bottom'
			),
			'left' => array(
				'label' => __( 'Left', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'left' 
			),
			'right' => array(
				'label' => __( 'Right', INCSUB_SBE_LANG_DOMAIN ),
				'slug' => 'right' 
			) 
		);

	}

	public function get_network_settings() {
		$defaults = $this->get_default_network_settings();
		$default_keys = array_keys( $defaults );
		$current_settings = get_site_option( $this->settings_network_slug, $defaults );
		$network_settings = array();
		foreach ( $current_settings as $current_setting => $value ) {
			if ( in_array( $current_setting, $default_keys ) )
				$network_settings[ $current_setting ] = $value;
		}
		return wp_parse_args( $network_settings, $defaults );
	}

	public function get_blog_settings() {
		$defaults = $this->get_default_blog_settings();

		if ( ! is_multisite() )
			$defaults = array_merge( $defaults, $this->get_default_network_settings() );
		
		$default_keys = array_keys( $defaults );
		$current_settings = get_option( $this->settings_slug, $defaults );
		$blog_settings = array();

		if ( ! is_array( $current_settings ) )
			$current_settings = $defaults;
		
		foreach ( $current_settings as $current_setting => $value ) {
			if ( in_array( $current_setting, $default_keys ) )
				$blog_settings[ $current_setting ] = $value;
		}

		return wp_parse_args( $blog_settings, $defaults );
	}

	public function get_settings() {
		if ( empty( $this->settings ) )
			$this->set_settings();

		return $this->settings;
	}

	public function update_settings( $settings ) {

		if ( is_multisite() ) {
			$network_settings = $this->get_network_settings();
			$blog_settings = $this->get_blog_settings();

			foreach ( $settings as $setting => $value ) {
				if ( array_key_exists( $setting, $network_settings ) )
					$network_settings[ $setting ] = $value;
				elseif ( array_key_exists( $setting, $blog_settings ) )
					$blog_settings[ $setting ] = $value;
			}

			update_site_option( $this->settings_network_slug, $network_settings );
			update_option( $this->settings_slug, $blog_settings );

		}
		else {
			update_option( $this->settings_slug, $settings );
		}

		$this->set_settings();
	}

	public function get_default_settings() {
		return array_merge( $this->get_default_blog_settings(), $this->get_default_network_settings() );
	}

	public function get_default_blog_settings() {

		global $current_site;

		$subscribe_email_content = _x( 'Howdy.', 'Confirmation email sent to subscribers.', INCSUB_SBE_LANG_DOMAIN ) . "\r\n\r\n";
		$subscribe_email_content .= __( 'You recently signed up to be notified of new posts on my blog. This means', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$subscribe_email_content .= __( 'once you confirm below, you will receive an email when posts are published.', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";
		$subscribe_email_content .= __( 'To activate, click confirm below. If you believe this is an error, ignore this message and nothing more will happen.', INCSUB_SBE_LANG_DOMAIN ) . "\r\n";

		$defaults = array(
			'auto-subscribe' => false,
			'subscribe_new_users' => false,
			'from_sender' => get_bloginfo( 'name' ),
			'subject' => get_bloginfo( 'name' ) . __( ': New post' ),
			'frequency' => 'inmediately',
			'time' => 0,
			'day_of_week' => 0,
			'manage_subs_page' => 0,
			'get_notifications' => false,
			'get_notifications_role' => 'administrator',

			'follow_button' => false,
			'follow_button_schema' => 'dark',
			'follow_button_position' => 'bottom',

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
			
			'send_full_post' => false,
			'subscribe_email_content' => $subscribe_email_content,

			'extra_fields' => array()
		);

		return apply_filters( 'sbe_blog_default_settings', $defaults );
	}

	function sanitize_template_settings( $new_settings ) {
		$settings = incsub_sbe_get_settings();

		foreach ( $settings as $setting_name => $setting_value ) {
			if ( isset( $new_settings[ $setting_name ] ) ) {
				switch ( $setting_name ) {
					case 'logo': {
						if ( empty( $new_settings[ $setting_name ] ) )
							$settings[ $setting_name ] = '';
						else
							$settings[ $setting_name ] = esc_url_raw( $new_settings[ $setting_name ] );
						break;
					}
					case 'logo_width': {
						if ( isset( $new_settings[ $setting_name ] ) && is_numeric( $new_settings[ $setting_name ] ) ) {
							$settings[ $setting_name ] = absint( $new_settings[ $setting_name ] );
						}
						break;
					}
					case 'send_full_post':
					case 'show_blog_name':
					case 'featured_image': {
						$settings[ $setting_name ] = (bool)$new_settings[ $setting_name ];
						break;
					}
					case 'header_color':
					case 'header_text_color': {
						if ( preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $new_settings[ $setting_name ] ) )
							$settings[ $setting_name ] = $new_settings[ $setting_name ];
						break;
					}
					case 'header_text': 
					case 'footer_text': 
					case 'subscribe_email_content':  {
						$settings[ $setting_name ] = wp_kses( $new_settings[ $setting_name ], wp_kses_allowed_html() );
					}
				}
			}
		}

		return $settings;
	}

	function get_default_network_settings() {
		if ( is_multisite() && is_subdomain_install() ) {
			$current_site = get_current_site();
			$blog_details = get_blog_details( $current_site->blog_id );
			$base_domain = $blog_details->domain;
		}
		elseif ( is_multisite() && ! is_subdomain_install() ) {
			$current_site = get_current_site();
			$blog_details = get_blog_details( $current_site->blog_id );
			$base_domain = $blog_details->domain;
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

		return array(
			'from_email' => 'no-reply@' . $base_domain,
			'keep_logs_for' => 31,
			'mails_batch_size' => 80
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

	public function get_extra_field_types() {
		return $this->extra_field_types;
	}

	public function get_follow_button_schemas() {
		return $this->follow_button_schemas;
	}

	public function get_follow_button_positions() {
		return $this->follow_button_positions;
	}

}