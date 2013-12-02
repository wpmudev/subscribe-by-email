<?php

class Incsub_Subscribe_By_Email_Admin_Settings_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	// Needed for registering settings
	private $settings_group;
	private $settings_name;

	// The settings
	private $settings;

	// Tabs
	private $tabs;

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;

		$this->tabs = array(
			'general' => __( 'General Settings', INCSUB_SBE_LANG_DOMAIN ),
			'content' => __( 'Contents', INCSUB_SBE_LANG_DOMAIN ),
			'template' => __( 'Mail template', INCSUB_SBE_LANG_DOMAIN ),
			'logs' => __( 'Logs', INCSUB_SBE_LANG_DOMAIN ),
			'extra-fields' => __( 'Extra Fields', INCSUB_SBE_LANG_DOMAIN )
		);

		$args = array(
			'slug' => 'sbe-settings',
			'page_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		$this->settings_name = incsub_sbe_get_settings_slug();
		$this->settings_group = incsub_sbe_get_settings_slug();
		$this->settings = incsub_sbe_get_settings();

		add_action( 'admin_init', array( &$this, 'register_settings' ) );

		add_action( 'admin_init', array( &$this, 'restore_default_template' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'admin_init', array( &$this, 'maybe_remove_extra_field' ) );

		add_filter( 'plugin_action_links_' . INCSUB_SBE_PLUGIN_FILE, array( &$this, 'add_plugin_list_link' ), 10 , 2 );

		add_action( 'wp_ajax_incsub_sbe_sort_extra_fields', array( &$this, 'sort_extra_fields' ) );

	}

	public function add_plugin_list_link( $actions, $file ) {
		$new_actions = $actions;
		$new_actions['settings'] = '<a href="' . self::get_permalink() . '" class="edit" title="' . __( 'Subscribe by Email Settings Page', INCSUB_SBE_LANG_DOMAIN ) . '">' . __( 'Settings', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
		return $new_actions;
	}


	/**
	 * Enqueue needed scripts
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->id == $this->get_page_id() ) {

			if ( 'general' == $this->get_current_tab() ) {
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-general.js', array( 'jquery' ), '20130721' );
			}
			elseif ( 'content' == $this->get_current_tab() ) {
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-content.js', array( 'jquery' ), '20130721' );
			}
			elseif ( 'template' == $this->get_current_tab() ) {
				wp_enqueue_script( 'thickbox' );
			    wp_enqueue_script( 'media-upload' );
			    wp_enqueue_script( 'farbtastic' );
			    wp_enqueue_script( 'jquery-ui-slider' );
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . 'js/settings-template.js', array( 'thickbox', 'media-upload' ), '20130721' );
			}
			elseif ( 'extra-fields' == $this->get_current_tab() ) {
				wp_enqueue_script( 'jquery-ui-sortable' );
			}
		    

			$l10n = array(
				'title_text' => __( 'Upload a logo', INCSUB_SBE_LANG_DOMAIN ),
				'button_text' => __( 'Upload logo', INCSUB_SBE_LANG_DOMAIN )
			);
			wp_localize_script( 'sbe-settings-scripts', 'sbe_captions', $l10n );

		}
	}



	/**
	 * Enqueue needed styles
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( $screen->id == $this->get_page_id() ) {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'farbtastic' );

			if ( 'template' == $this->get_current_tab() )
				wp_enqueue_style( 'jquery-ui-css', INCSUB_SBE_ASSETS_URL .'css/jquery-ui/jquery-ui-1.10.3.custom.min.css' );

			if ( 'extra-fields' == $this->get_current_tab() )
				wp_enqueue_style( 'sbe-settings', INCSUB_SBE_ASSETS_URL .'css/settings.css' );				
		}
	}

	public function maybe_remove_extra_field() {
		$screen = get_current_screen();
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && $this->get_current_tab() == 'extra-fields' && isset( $_GET['remove'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'remove_extra_field' ) )
				return false;

			$settings = incsub_sbe_get_settings();
			if ( isset( $settings['extra_fields'][ $_GET['remove'] ] ) ) {
				unset( $settings['extra_fields'][ $_GET['remove'] ] );
				remove_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );
				incsub_sbe_update_settings( $settings );
				add_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );

				$model = incsub_sbe_get_model();
				$model->delete_subscribers_all_meta( $_GET['remove'] );
				wp_redirect( 
					add_query_arg(
						array( 
							'tab' => 'extra-fields',
							'updated' => 'true' 
						),
						$this->get_permalink()
					)
				);
			}
		}
	}

	/**
	 * Register the settings, sections and fields
	 */
	public function register_settings() {
		register_setting( $this->settings_group, $this->settings_name, array( &$this, 'sanitize_settings' ) );

		if ( $this->get_current_tab() == 'general' ) {
			add_settings_section( 'general-settings', __( 'General Settings', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			//add_settings_field( 'auto-subscribe', __( 'Auto-subscribe', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_auto_subscribe_field' ), $this->get_menu_slug(), 'general-settings' ); 
			//add_settings_field( 'subscribe-new-users', __( 'Subscribe new users', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribe_new_users_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'from-sender', __( 'Notification From Sender', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_from_sender_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'from-email', __( 'Notification From Email', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_from_email_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'subject', __( 'Mail subject', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subject_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'frequency', __( 'Email Frequency', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_frequency_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'mail_batch', __( 'Mail batches', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_mail_batches_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'get-nofitications', __( 'Get notifications', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_get_notifications_field' ), $this->get_menu_slug(), 'general-settings' ); 

			add_settings_section( 'user-subs-page-settings', __( 'Subscription page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_section' ), $this->get_menu_slug() );
			add_settings_field( 'user-subs-page', __( 'Subscribers Management Page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_field' ), $this->get_menu_slug(), 'user-subs-page-settings' ); 

			add_settings_section( 'follow-button', __( 'Follow button', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'follow-button-field', __( 'Display a follow button?', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_follow_button_field' ), $this->get_menu_slug(), 'follow-button' ); 
			add_settings_field( 'follow-button-schema-field', __( 'Schema', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_follow_button_schema_field' ), $this->get_menu_slug(), 'follow-button' ); 
		}
		elseif ( $this->get_current_tab() == 'content' ) {
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();

			$post_types = $settings_handler->get_post_types();
			
				foreach ( $post_types as $post_type_slug => $post_type ) {
					add_settings_section( 'post-type-' . $post_type_slug . '-settings', $post_type->labels->name, null, $this->get_menu_slug() );
					add_settings_field( 'post-types' . $post_type_slug . '-send-content-field', __( 'Send this post type', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_send_content_field' ), $this->get_menu_slug(), 'post-type-' . $post_type_slug . '-settings', array( 'post_type_slug' => $post_type_slug, 'post_type_name' => $post_type->labels->name ) ); 	

					$taxonomies = $settings_handler->get_taxonomies_by_post_type( $post_type_slug );
					foreach ( $taxonomies as $tax_slug => $taxonomy ) {
						add_settings_field( 'post-types' . $post_type_slug . '-tax-' . $tax_slug, $taxonomy->labels->name, array( &$this, 'render_send_content_taxonomy_field' ), $this->get_menu_slug(), 'post-type-' . $post_type_slug . '-settings', array( 'taxonomy_slug' => $tax_slug, 'taxonomy' => $taxonomy, 'post_type_slug' => $post_type_slug ) ); 	
					}
					
				}
		}
		elseif ( $this->get_current_tab() == 'template' ) {
			add_settings_section( 'logo-settings', __( 'Logo', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'logo', __( 'Logo for notifications', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_logo_field' ), $this->get_menu_slug(), 'logo-settings' ); 
			add_settings_field( 'logo-width', __( 'Logo max width in pixels', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_logo_width_field' ), $this->get_menu_slug(), 'logo-settings' ); 

			add_settings_section( 'header-settings', __( 'Header', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'header-color', __( 'Header color', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_color_field' ), $this->get_menu_slug(), 'header-settings' ); 
			add_settings_field( 'header-text-color', __( 'Header text color', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_text_color_field' ), $this->get_menu_slug(), 'header-settings' ); 
			add_settings_field( 'header-text', __( 'Subtitle text', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_text_field' ), $this->get_menu_slug(), 'header-settings' ); 
			add_settings_field( 'header-blog-name', __( 'Show From Sender', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_show_blog_name_field' ), $this->get_menu_slug(), 'header-settings' ); 

			add_settings_section( 'footer-settings', __( 'Footer', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'footer-text', __( 'Footer text', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_footer_text_field' ), $this->get_menu_slug(), 'footer-settings' ); 

			add_settings_section( 'subscribe-email-settings', __( 'Subscribe Email', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'subscribe-email-content', __( 'Subscribe Email Content', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribe_email_content' ), $this->get_menu_slug(), 'subscribe-email-settings' ); 

			add_settings_section( 'other-styling-settings', __( 'Other options', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'featured-images', __( 'Show featured images', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_featured_image' ), $this->get_menu_slug(), 'other-styling-settings' ); 

			add_settings_section( 'email-preview', __( 'Email preview', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_email_preview_section' ), $this->get_menu_slug() );
		}
		elseif ( $this->get_current_tab() == 'logs' ) {
			add_settings_section( 'logs-settings', __( 'Logs', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'keep-logs-for', __( 'Keep logs files during', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_keep_logs_for_field' ), $this->get_menu_slug(), 'logs-settings' ); 
		}
		elseif ( $this->get_current_tab() == 'extra-fields' ) {
			add_settings_section( 'custom-fields', __( 'Extra Fields', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_extra_fields_section' ), $this->get_menu_slug() );
			add_settings_field( 'custom-fields-meta', __( 'Subscribers extra fields', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribers_extra_fields_field' ), $this->get_menu_slug(), 'custom-fields' ); 
		}

	}

	private function get_current_tab() {
		if ( isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->tabs ) ) {
			return $_GET['tab'];
		}
		else {
			return 'general';
		}
	}

	private function the_tabs() {
		$current_tab = $this->get_current_tab();

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->tabs as $key => $name ): ?>
			<a href="?page=<?php echo $this->get_menu_slug(); ?>&tab=<?php echo $key; ?>" class="nav-tab <?php echo $current_tab == $key ? 'nav-tab-active' : ''; ?>"><?php echo $name; ?></a>
		<?php endforeach;
			
		echo '</h2>';
		
	}

	public function render_page() {

		if ( ! current_user_can( $this->get_capability() ) )
			wp_die( __( 'You do not have enough permissions to access to this page', INCSUB_SBE_LANG_DOMAIN ) );

		?>
			<div class="wrap">
				
				<?php screen_icon( 'sbe' ); ?>
				
				<?php $this->the_tabs(); ?>

				<?php $this->render_content(); ?>

			</div>

		<?php

	}


	/**
	 * render the settings page
	 */
	public function render_content() {

		settings_errors( $this->settings_name );
		if ( isset( $_GET['settings-updated'] ) ) {
			?>
				<div class="updated"><p><?php _e( 'Settings updated', INCSUB_SBE_LANG_DOMAIN ); ?></p></div>
			<?php
		}
		?>
		
			<form action="options.php" method="post">
				<?php settings_fields( $this->settings_group ); ?>
				<?php do_settings_sections( $this->get_menu_slug() ); ?>
					
				<p class="submit">
					<?php submit_button( null, 'primary', $this->settings_name . '[submit_settings_' . $this->get_current_tab() . ']', false ) ?>
				</p>
			</form>
		
		<?php
	}

	/********************************/
	/* 		FIELDS RENDERINGS		*/
	/********************************/

	/**
	 * Auto Subscribe field
	 */
	public function render_auto_subscribe_field() {

		?>
			<label for="auto_subscribe_yes">
				<input id="auto_subscribe_yes" type="radio" name="<?php echo $this->settings_name; ?>[auto_subscribe]" value="yes" <?php checked( $this->settings['auto-subscribe'], true ); ?>>
				<?php _e( 'Yes', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label><br/>
			<label for="auto_subscribe_no">
				<input id="auto_subscribe_no" type="radio" name="<?php echo $this->settings_name; ?>[auto_subscribe]" value="no" <?php checked( $this->settings['auto-subscribe'], false ); ?>>
				<?php _e( 'No', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label><br/>
			<span class="description"><?php _e( 'Subscribe users without sending a confirmation mail.', INCSUB_SBE_LANG_DOMAIN ); ?></span>  
		<?php
	}

	

	/**
	 * From Sender field
	 */
	public function render_from_sender_field() {
		?>
			<input type="text" name="<?php echo $this->settings_name; ?>[from_sender]" class="regular-text" value="<?php echo esc_attr( $this->settings['from_sender'] ); ?>">
		<?php
	}

	/**
	 * From Email field
	 */
	public function render_from_email_field() {
		?>
			<input type="text" name="<?php echo $this->settings_name; ?>[from_email]" class="regular-text" value="<?php echo esc_attr( $this->settings['from_email'] ); ?>"><br/>
			<span class="description"><?php _e( 'Recommended: no-reply@yourdomain.com as spam filters may block other addresses.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	/**
	 * Subject field
	 */
	public function render_subject_field() {
		?>
			<input type="text" name="<?php echo $this->settings_name; ?>[subject]" class="regular-text" value="<?php echo esc_attr( $this->settings['subject'] ); ?>"><br/>
			<span><?php _e( 'You can use the <strong>%title%</strong> wildcard to show the latest post title/s, they will be shortened to no more than 50 charactes', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	/**
	 * Subject field
	 */
	public function render_mail_batches_field() {
		$minutes = Incsub_Subscribe_By_Email::$time_between_batches / 60;
		?>
			<label for="mail_batches"><?php printf( __( 'Send %s mails every %d minutes (maximum).', INCSUB_SBE_LANG_DOMAIN ), '<input id="mail_batches" type="number" name="' . $this->settings_name . '[mail_batches]" class="small-text" value="' . esc_attr( $this->settings['mails_batch_size'] ) . '">', $minutes ); ?></label><br/>
			<span class="description"><?php printf( __( 'If you are experiencing problems when sending mails, your server may be limiting the email volume. Try reducing this number. Mails will be sent every %d minutes in groups of X mails.', INCSUB_SBE_LANG_DOMAIN ), $minutes ); ?></span>
		<?php
	}

	public function render_get_notifications_field() {
		?>
			<label for="get-notifications">
				<input id="get-notifications" type="checkbox" name="<?php echo $this->settings_name; ?>[get_notifications]" <?php checked( $this->settings['get_notifications'] ); ?> /> 
				<?php _e( "If checked, the following role will get email notifications when there's a new subscriber or when someone ends their subscription", INCSUB_SBE_LANG_DOMAIN ); ?>
			</label>
			
			<select name="<?php echo $this->settings_name; ?>[get_notifications_role]" id="get-notifications-role">
				<?php echo wp_dropdown_roles( $this->settings['get_notifications_role'] ); ?>
			</select>

		<?php
	}

	/**
	 * Frequency field
	 */
	public function render_frequency_field() {
		$time_format = get_option( 'time_format', 'H:i' );

		?>
			<select name="<?php echo $this->settings_name; ?>[frequency]" id="frequency-select">
				<?php foreach ( incsub_sbe_get_digest_frequency() as $key => $freq ): ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['frequency'] ); ?>><?php echo $freq; ?></option>
				<?php endforeach; ?>
			</select>
			<br/><br/>
			<div id="time-wrap">
				<label for="time-select"><?php _e( 'What time should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[time]" id="time-select">
						<?php foreach ( incsub_sbe_get_digest_times() as $key => $t ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['time'] ); ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</label> 
				<span class="description"><?php printf( __( 'The time now is %s', INCSUB_SBE_LANG_DOMAIN ), date_i18n( $time_format ) ); ?></span>
			</div>

			<div id="day-of-week-wrap">
				<label for="day-of-week-select"><?php _e( 'What day of the week should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[day_of_week]" id="day-of-week-select">
						<?php foreach ( incsub_sbe_get_digest_days_of_week() as $key => $day ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['day_of_week'] ); ?>><?php echo $day; ?></option>
						<?php endforeach; ?>
					</select>
				</label><br/>
				<label for="time-select"><?php _e( 'What time should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[time]" id="time-select">
						<?php foreach ( incsub_sbe_get_digest_times() as $key => $t ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['time'] ); ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</label> 
			</div>
			
			<?php $next_scheduled = Incsub_Subscribe_By_Email::get_next_scheduled_date(); ?>
			<?php if ( $next_scheduled ): ?>
				<p><?php _e( 'Next digest will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?> <code><?php echo $next_scheduled; ?></code></p>
			<?php endif; ?>


		<?php
	}

	/**
	 * Post Types Section
	 */
	public function render_posts_types_section() {
		?>
			<p><?php _e( 'Check those Post Types that you want to send to your subscribers.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function render_send_content_field( $args ) {
		extract( $args );
		?>
			<label>
				<input class="post-type-checkbox" data-post-slug="<?php echo $post_type_slug; ?>" type="checkbox" <?php checked( in_array( $post_type_slug, $this->settings['post_types'] ) ); ?> name="<?php echo $this->settings_name; ?>[post_types][]" value="<?php echo $post_type_slug; ?>"> <?php printf( __( '%s will be included in the digests', INCSUB_SBE_LANG_DOMAIN ), $post_type_name ); ?>
			</label>
		<?php
	}

	public function render_send_content_taxonomy_field( $args ) {
		extract( $args );

		// All categories checkbox is checked?
		$all_checked = (
			( ! isset( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
			|| ( in_array( 'all', $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
			|| ( empty( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) )
		);

		// Checkboxes are disabled?
		$disabled = ! in_array( $post_type_slug, $this->settings['post_types'] );
		
		$base_name = $this->settings_name . '[tax_input]';

		if ( isset( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) && is_array( $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ] ) && ! $all_checked ) {
			$selected_cats = $this->settings['taxonomies'][ $post_type_slug ][ $taxonomy_slug ];
		}
		else {
			$selected_cats = array();
		}

		?>
			<p><?php printf( __( 'Choose between these %s:', INCSUB_SBE_LANG_DOMAIN ), $taxonomy->labels->name ); ?></p>
			<div id="poststuff" style="width:280px;margin-left:0;padding-top:0">
        		<div id="<?php echo $taxonomy_slug; ?>-categorydiv" class="postbox ">
					<h3 class="hndle"><span><?php echo $taxonomy->labels->name; ?></span></h3>
					<div class="inside">
						<div id="taxonomy-<?php echo $taxonomy_slug; ?>" class="categorydiv">
							<div id="<?php echo $taxonomy_slug; ?>-all" class="tabs-panel">
								<ul id="<?php echo $taxonomy_slug; ?>checklist" class="<?php echo $taxonomy_slug; ?>checklist form-no-clear">
									<li id="<?php echo $taxonomy_slug; ?>-all"><label class="selectit"><input class="settings-term-checkbox <?php echo $post_type_slug; ?>-checkbox" value="all" type="checkbox" <?php checked( $all_checked ); ?> <?php disabled( $disabled ); ?> name="<?php echo $base_name; ?>[<?php echo $post_type_slug; ?>][<?php echo $taxonomy_slug; ?>][]" id="in-<?php echo $taxonomy_slug; ?>-all"> <strong><?php _e( 'All', INCSUB_SBE_LANG_DOMAIN ); ?></strong></label></li>
									<?php 
										$walker = new Walker_SBE_Terms_Checklist;

										sbe_terms_checklist( 
											0, 
											array( 
												'taxonomy' => $taxonomy_slug,
												'walker' => $walker,
												'disabled' => $disabled,
												'taxonomy_slug' => $taxonomy_slug,
												'post_type_slug' => $post_type_slug,
												'base_name' => $base_name,
												'selected_cats' => $selected_cats
											) 
										); ?>
								</ul>
							</div>
									
						</div>
					</div>
				</div>
			</div>
		<?php
	}


	public function render_subscription_page_section() {
		?><p><?php _e( 'You can select a page where users will be able to subscribe/unsubscribe to any post type', INCSUB_SBE_LANG_DOMAIN ); ?></p><?php
	}


	public function render_subscription_page_field() {
		$args = array(
			'show_option_none' => __( '--Select a page--', INCSUB_SBE_LANG_DOMAIN ),
			'selected' => $this->settings['manage_subs_page'],
			'option_none_value' => 0,
			'name' => $this->settings_name . '[manage_subs_page]',
			'id' => 'manage_subs_page_selector'
		);
		wp_dropdown_pages( $args );
		?>
			 <span class="description"><?php _e( 'After a page is selected, the management form will be appended to the content of the page', INCSUB_SBE_LANG_DOMAIN ); ?></span>
			 <p><?php _e( "Users will receive a link to this page via email.", INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function render_follow_button_field() {
		?>	
			<label>
				<input type="checkbox" name="<?php echo $this->settings_name; ?>[follow_button]" <?php checked( $this->settings['follow_button'] ); ?> /> 
				<?php _e( 'Will place a follow button permanently in the bottom right of your site.', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label>
		<?php
	}

	public function render_follow_button_schema_field() {
		$settings = incsub_sbe_get_settings();
		$schemas = incsub_sbe_get_follow_button_schemas();
		foreach ( $schemas as $schema ) {
			?>
				<label>
					<input type="radio" name="<?php echo $this->settings_name; ?>[follow_button_schema]" value="<?php echo esc_attr( $schema['slug'] ); ?>" <?php checked( $this->settings['follow_button_schema'] == $schema['slug'] ); ?> /> 
					<?php echo $schema['label']; ?>
				</label><br/>
			<?php
		}
			
	}

	/**
	 * Logo field
	 */
	public function render_logo_field() {
		?>
			
			<input type="hidden" name="<?php echo $this->settings_name; ?>[logo]" id="upload-logo-value" value="<?php echo esc_url( $this->settings['logo'] ); ?>">
			<input type="button" class="button-secondary" id="upload-logo" value="<?php _e( 'Upload logo', INCSUB_SBE_LANG_DOMAIN ); ?>">
			<div class="sbe-logo-preview">
				<img style="max-width:300px;border:1px solid #DDD;padding:3px;background:#EFEFEF;margin-top:20px;" id="sbe-logo-img" src="<?php echo esc_url( $this->settings['logo'] ); ?>"></img>
				<?php submit_button( __( 'Remove logo', INCSUB_SBE_LANG_DOMAIN ), 'secondary', $this->settings_name . '[remove-logo]', true, array( 'id' => 'remove-logo-button' ) ); ?>
			</div>
		<?php
	}

	public function render_logo_width_field() {
		?>
			<div style="max-width:30%;" id="logo-width-slider"></div><br/>
			<p id="logo-width-caption"><span id="logo-width-quantity"><?php echo $this->settings['logo_width']; ?></span> <span class="description">px</span></p>

			<input type="hidden" class="small-text" name="<?php echo $this->settings_name; ?>[logo_width]" id="logo-width" value="<?php echo $this->settings['logo_width']; ?>" />
			<script>
			jQuery(document).ready(function($) {
				$( "#logo-width-slider" ).slider({
					value:<?php echo $this->settings['logo_width']; ?>,
					min: 100,
					max: 700,
					step: 10,
					slide: function( event, ui ) {
						$( "#logo-width" ).val( ui.value );
						$( "#logo-width-quantity" ).text( ui.value );
					}
				});
			    	$( "#logo-width" ).val( $( "#logo-width-slider" ).slider( "value" ) );
			    	$( "#logo-width-quantity" ).val( $( "#logo-width-slider" ).slider( "value" ) );
				});
			</script>
		<?php
	}


	/**
	 * Logo field
	 */
	public function render_featured_image() {
		?>
			<input type="checkbox" name="<?php echo $this->settings_name; ?>[featured_image]" id="featured-image" <?php checked( $this->settings['featured_image'] ); ?>> 
			<span class="description"><?php _e( 'If your theme allows it, the featured image for each post will appear to the left of the post excerpt.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	/**
	 * Header color field
	 */
	public function render_header_color_field() {
		?>
			<input type="text" id="header-color" name="<?php echo $this->settings_name; ?>[header_color]" value="<?php echo esc_attr( $this->settings['header_color'] ); ?>" />
			<div class="colorpicker-wrap" style="position:relative;">
 				<div class="colorpicker" id="header-color-picker" style="position:absolute;background:#FFF;border:1px solid #EFEFEF; left: 139px; top:-24px; display:none;"></div>
 			</div>
		<?php
	}

	/**
	 * Header text color field
	 */
	public function render_header_text_color_field() {
		?>
			<input type="text" id="header-text-color" name="<?php echo $this->settings_name; ?>[header_text_color]" value="<?php echo esc_attr( $this->settings['header_text_color'] ); ?>" />
			<div class="colorpicker-wrap" style="position:relative;">
 				<div class="colorpicker" id="header-text-color-picker" style="position:absolute;background:#FFF;border:1px solid #EFEFEF; left: 139px; top:-24px; display:none;"></div>
 			</div>
		<?php
	}

	/**
	 * Header text field
	 */
	public function render_header_text_field() {
		?>
			<textarea class="large-text" name="<?php echo $this->settings_name; ?>[header_text]" id="header-text" rows="4"><?php echo esc_textarea( $this->settings['header_text'] ); ?></textarea>
		<?php
	}

	/**
	 * Header text field
	 */
	public function render_show_blog_name_field() {
		?>
			<label for="show-blog-name">
				<input type="checkbox" name="<?php echo $this->settings_name; ?>[show_blog_name]" <?php checked( $this->settings['show_blog_name'] ); ?> id="show-blog-name"> 
				<?php _e( 'If checked, the From Sender Text will appear on the header', INCSUB_SBE_LANG_DOMAIN ); ?>
			</label>
		<?php
	}

	/**
	 * Footer text field
	 */
	public function render_footer_text_field() {
		?>
			<textarea class="large-text" name="<?php echo $this->settings_name; ?>[footer_text]" id="footer-text" rows="4"><?php echo esc_textarea( $this->settings['footer_text'] ); ?></textarea>
		<?php
	}

	public function render_email_preview_section() {
		?>
			<p>
				<?php submit_button( __( 'Refresh changes', INCSUB_SBE_LANG_DOMAIN ), 'primary', $this->settings_name . '[submit_refresh_changes]', false ) ?>
				<?php submit_button( __( 'Send a test mail to:', INCSUB_SBE_LANG_DOMAIN ), 'secondary', $this->settings_name . '[submit_test_email]', false ) ?>
				<input type="text" class="regular-text" name="<?php echo $this->settings_name; ?>[test_mail]" value="<?php echo esc_attr( get_option( 'admin_email') ); ?>"><br/>
			</p>

			<?php 
				$restore_link = add_query_arg( 
					'restore-template', 
					'true',
					self::get_permalink()
				);
			?>
			<p><a href="<?php echo $restore_link; ?>"><?php _e( 'Restore template to default', INCSUB_SBE_LANG_DOMAIN ); ?></a></p>

			<?php 
				$template = new Incsub_Subscribe_By_Email_Template( $this->settings, true ); 
				$template->render_mail_template();
			?>
		<?php
	}

	/**
	 * Subscribing Email Contents
	 */
	public function render_subscribe_email_content() {
		?>
			<textarea class="widefat" rows="8" name="<?php echo $this->settings_name; ?>[subscribe_email_content]"><?php echo esc_textarea( $this->settings['subscribe_email_content'] ); ?></textarea>
		<?php
	}

	public function render_keep_logs_for_field() {
		?>
			<input type="number" class="small-text" size="2" name="<?php echo $this->settings_name; ?>[keep_logs_for]" value="<?php echo absint( $this->settings['keep_logs_for'] ); ?>" /> <?php _e( 'Days', INCSUB_SBE_LANG_DOMAIN ); ?> <br/><span class="description"><?php _e( '31 max.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	public function render_extra_fields_section() {
		?>
			<p><?php _e( 'In this screen you can add new fields that subscribers can fill when they try to subscribe via widget or Follow Button', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}


	public function render_subscribers_extra_fields_field() {
		?>
			<label><?php _e( 'Field title', INCSUB_SBE_LANG_DOMAIN ); ?>
				<input type="text" name="<?php echo $this->settings_name; ?>[extra_field_name]" />
			</label>
			<label><?php _e( 'Field Slug', INCSUB_SBE_LANG_DOMAIN ); ?>
				<input type="text" name="<?php echo $this->settings_name; ?>[extra_field_slug]" />
			</label>
			<select name="<?php echo $this->settings_name; ?>[extra_field_type]" id="extra_field_type">
				<option value="">-- <?php _e( 'Field type', INCSUB_SBE_LANG_DOMAIN ); ?> --</option>
				<?php incsub_sbe_extra_field_types_dropdown(); ?>
			</select>
			<label>
				<?php _e( 'Required', INCSUB_SBE_LANG_DOMAIN ); ?>
				<input type="checkbox" name="<?php echo $this->settings_name; ?>[extra_field_required]" />

			</label>
			<?php submit_button( __( 'Add field', INCSUB_SBE_LANG_DOMAIN ), 'secondary', $this->settings_name . '[submit_new_extra_field]', false ); ?>

			<?php $allowed_types = incsub_sbe_get_extra_field_types(); ?>
			<?php $remove_link = add_query_arg( 'tab', 'extra-fields', $this->get_permalink() ); ?>
			<div id="extra-fields-list" class="extra-fields-sortables">
				<?php foreach ( $this->settings['extra_fields'] as $field_id => $value ): ?>
					<div class="extra-field-item" data-field-slug="<?php echo esc_attr( $value['slug'] ); ?>">	
						<div class="extra-field-item-top">
							<div class="extra-field-item-title-action">
								<a class="extra-field-item-edit" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'remove', $field_id, $remove_link ), 'remove_extra_field' ) ); ?>">
									<span class="remove"><?php _e( 'Remove', INCSUB_SBE_LANG_DOMAIN ); ?></span>
								</a>
							</div>
							<div class="extra-field-item-title"><h4><?php echo esc_html( $value['title'] ); ?> <?php echo $value['required'] ? '[' . __( 'Required', INCSUB_SBE_LANG_DOMAIN ) . ']' : ''; ?>:
								<span class="in-extra-field-item-title"><?php echo urldecode( $value['slug'] ) . ' [' . $allowed_types[ $value['type'] ]['name'] . ']'; ?></span></h4>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<script>
				jQuery(document).ready(function($) {
					$('.extra-fields-sortables').sortable({
						stop: function( event, ui ) {
							var nodes = $('.extra-field-item');
							var slugs = new Array();
							nodes.each( function ( i, element ) {
								slugs.push($(this).data('field-slug'));
							});

							$.ajax({
								url: ajaxurl,
								type: 'post',
								data: {
									slugs: slugs,
									action: 'incsub_sbe_sort_extra_fields',
									nonce: "<?php echo wp_create_nonce( 'sort_extra_fields' ); ?>"
								},
							});							
						}
					});
				});
			</script>
		<?php

	}

	public function sort_extra_fields() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'sort_extra_fields' ) )
			die();

		if ( empty( $_POST['slugs'] ) || ! is_array( $_POST['slugs'] ) )
			die();

		$slugs = $_POST['slugs'];

		$settings = incsub_sbe_get_settings();
		$extra_fields = $settings['extra_fields'];
		$new_extra_fields = array();

		foreach ( $slugs as $slug ) {
			foreach ( $extra_fields as $extra_field ) {
				if ( $slug == $extra_field['slug'] ) {
					$new_extra_fields[] = $extra_field;
					break;
				}
			}
		}

		$settings['extra_fields'] = $new_extra_fields;
		error_log(print_r($settings, true));

		remove_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );
		incsub_sbe_update_settings( $settings );
		add_filter( 'sanitize_option_' . $this->settings_name, array( &$this, 'sanitize_settings' ) );

		die();
	}


	/**
	 * Sanitizes the settings and return the values to be saved
	 * 
	 * @param Array $input $_POST values
	 * 
	 * @return Array New settings
	 */
	public function sanitize_settings( $input ) {

		$new_settings = $this->settings;

		if ( isset( $input['submit_settings_general'] ) ) {

			// Auto subscribe
			//if ( 'yes' == $input['auto_subscribe'] )
			//	$new_settings['auto-subscribe'] = true;
			//else
			//	$new_settings['auto-subscribe'] = false;

			// Subscribe new users
			//if ( 'yes' == $input['subscribe_new_users'] )
			//	$new_settings['subscribe_new_users'] = true;
			//else
			//	$new_settings['subscribe_new_users'] = false;

			// From Sender
			$from_email = sanitize_email( $input['from_email'] );

			if ( is_email( $from_email ) )
				$new_settings['from_email'] = $from_email;
			else
				add_settings_error( $this->settings_name, 'invalid-from-email', __( 'Notification From Email is not a valid email', INCSUB_SBE_LANG_DOMAIN ) );

			$from_sender = sanitize_text_field( $input['from_sender'] );
			if ( ! empty( $from_sender ) )
				$new_settings['from_sender'] = $from_sender;
			else
				add_settings_error( $this->settings_name, 'invalid-from-sender', __( 'Notification From Sender cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );

			// Mail subject
			$subject = sanitize_text_field( $input['subject'] );
			if ( ! empty( $subject ) )
				$new_settings['subject'] = $subject;
			else
				add_settings_error( $this->settings_name, 'invalid-subject', __( 'Mail subject cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );

			// Frequency
			if ( array_key_exists( $input['frequency'], incsub_sbe_get_digest_frequency() ) ) {
				$new_settings['frequency'] = $input['frequency'];
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
				$new_settings['frequency'] = $default_settings['frequency'];
			}

			// For daily frequencies
			if ( 'daily' == $new_settings['frequency'] && array_key_exists( $input['time'], incsub_sbe_get_digest_times() ) ) {
				$new_settings['time'] = $input['time'];
				if ( 'daily' != $this->settings['frequency'] || $input['time'] != $this->settings['time'] ) {
					// We have changed this setting
					Incsub_Subscribe_By_Email::set_next_day_schedule_time( $input['time'] );
				}
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
				$new_settings['time'] = $default_settings['time'];
			}

			// For weekly frequencies
			if ( 'weekly' == $new_settings['frequency'] && array_key_exists( $input['day_of_week'], incsub_sbe_get_digest_days_of_week() ) && array_key_exists( $input['time'], incsub_sbe_get_digest_times() ) ) {
				$new_settings['day_of_week'] = $input['day_of_week'];
				$new_settings['time'] = $input['time'];
				Incsub_Subscribe_By_Email::set_next_week_schedule_time( $input['day_of_week'], $input['time'] );

			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
				$new_settings['day_of_week'] = $default_settings['day_of_week'];
				$new_settings['time'] = $default_settings['time'];
			}

			// Management Page
			if ( isset( $input['manage_subs_page'] ) ) {
				$new_settings['manage_subs_page'] = absint( $input['manage_subs_page'] );
			}

			// Batches
		    if ( ! empty( $input['mail_batches'] ) )
				$new_settings['mails_batch_size'] = absint( $input['mail_batches'] );

			$new_settings['get_notifications'] = isset( $input['get_notifications'] );

			$new_settings['follow_button'] = isset( $input['follow_button'] );
			if ( ! empty( $input['follow_button_schema'] ) && array_key_exists( $input['follow_button_schema'], incsub_sbe_get_follow_button_schemas() ) )
				$new_settings['follow_button_schema'] = $input['follow_button_schema'];

			$new_settings['get_notifications_role'] = $input['get_notifications_role'];
		}

		if ( isset( $input['submit_settings_content'] ) ) {
			// Post types
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				$new_settings['post_types'] = $input['post_types'];
			}

			//Taxonomies
			if ( ! empty( $input['tax_input'] ) ) {
				$new_settings['taxonomies'] = array();

				foreach ( $input['tax_input'] as $post_type_slug => $taxonomies ) {
					foreach ( $taxonomies as $tax_slug => $taxonomy_items ) {
						if ( ! in_array( $post_type_slug, $new_settings['post_types'] ) ) {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array( 'all' );
							continue;
						}

						if ( in_array( 'all', $taxonomy_items ) ) {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array( 'all' );
						}
						else {
							$new_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = $taxonomy_items;
						}
					}
					
				}

			}
		}

		if ( isset( $input['submit_settings_template'] ) || isset( $input['remove-logo'] ) || isset( $input['submit_test_email'] ) || isset( $input['submit_refresh_changes'] ) ) {

			// Logo
			if ( isset( $input['remove-logo'] ) ) {
				$new_settings['logo'] = '';
			}
			else {
				$url = esc_url_raw( $input['logo'] );
				$new_settings['logo'] = $url;
			}

			// Logo Width
			if ( isset( $input['logo_width'] ) && is_numeric( $input['logo_width'] ) ) {
				$new_settings['logo_width'] = absint( $input['logo_width'] );
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
		    	$new_settings['logo_width'] = $default_settings['logo_width'];
			}

			// Featured image
			if ( isset( $input['featured_image'] ) )
				$new_settings['featured_image'] = true;
			else
				$new_settings['featured_image'] = false;

			// Colors
			if ( preg_match( '/^#[a-f0-9]{6}$/i', $input['header_color'] ) ) {
				$new_settings['header_color'] = $input['header_color'];
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
		    	$new_settings['header_color'] = $default_settings['header_color'];
		    }

		    if ( preg_match( '/^#[a-f0-9]{6}$/i', $input['header_text_color'] ) ) {
				$new_settings['header_text_color'] = $input['header_text_color'];
			}
			else {
				$default_settings = incsub_sbe_get_default_settings();
		    	$new_settings['header_text_color'] = $default_settings['header_text_color'];
		    }

		    if ( isset( $input['show_blog_name'] ) )
				$new_settings['show_blog_name'] = true;
			else
				$new_settings['show_blog_name'] = false;

			
			
			// Texts
			$new_settings['header_text'] = $input['header_text'];
			$new_settings['footer_text'] = $input['footer_text'];
			$new_settings['subscribe_email_content'] = $input['subscribe_email_content'];
			
			if ( isset( $input['submit_test_email'] ) ) {
				
				$mail = sanitize_email( $input['test_mail'] );

				if ( is_email( $mail ) ) {
					$template = new Incsub_Subscribe_By_Email_Template( $new_settings, true );
					$template->send_mail( $mail );
				}
			}

		}

		if ( isset( $input['submit_settings_logs'] ) ) {
			if ( ! empty( $input['keep_logs_for'] ) ) {
				$option = absint( $input['keep_logs_for'] );
				if ( $option > 31 ) {
					$new_settings['keep_logs_for'] = 31;
				}
				elseif ( $option < 1 ) {
					$new_settings['keep_logs_for'] = 1;	
				}
				else {
					$new_settings['keep_logs_for'] = $option;
				}
			}
				
		}

		if ( isset( $input['submit_new_extra_field'] ) ) {
			$extra_field_error = false;

			if ( empty( $input['extra_field_name'] ) ) {
				add_settings_error( $this->settings_name, 'extra-field-name', __( 'Name cannot be empty', INCSUB_SBE_LANG_DOMAIN ) );
				$extra_field_error = true;
			}
			else {
				$name = sanitize_text_field( $input['extra_field_name'] );
			}

			if ( ! $extra_field_error ) {
				if ( empty( $input['extra_field_slug'] ) )
					$slug = sanitize_title_with_dashes( $name );
				else
					$slug = sanitize_title_with_dashes( $input['extra_field_slug'] );

				$settings = incsub_sbe_get_settings();
				$slug_found = false;
				foreach ( $settings['extra_fields'] as $extra_field ) {
					if ( $extra_field['slug'] == $slug )
						$slug_found = true;
				}
				if ( $slug_found ) {
					add_settings_error( $this->settings_name, 'extra-field-slug', __( 'Slug already exist', INCSUB_SBE_LANG_DOMAIN ) );
					$extra_field_error = true;
				}

				$type = ! empty( $input['extra_field_type'] ) ? $input['extra_field_type'] : '';
				if ( ! $extra_field_error && array_key_exists( $type, incsub_sbe_get_extra_field_types() ) ) {
					$new_settings['extra_fields'][] = array(
						'slug' => $slug,
						'title' => $name,
						'type' => $type,
						'required' => ! empty( $input['extra_field_required'] )
					);
				}
				else {
					add_settings_error( $this->settings_name, 'extra-field-type', __( 'Select a field type', INCSUB_SBE_LANG_DOMAIN ) );
				}
			}
							
		}

		return $new_settings;

	}

	public function restore_default_template() {
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && isset( $_GET['restore-template'] ) ) {
			$default_settings = incsub_sbe_get_default_settings();

			$this->settings['logo'] = $default_settings['logo'];
			$this->settings['header_color'] = $default_settings['header_color'];
			$this->settings['header_text_color'] = $default_settings['header_text_color'];
			$this->settings['featured_image'] = $default_settings['featured_image'];

			incsub_sbe_update_settings( $this->settings );

			wp_redirect( $this->get_permalink() );
		}
	}

	

}