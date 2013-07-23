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
			'template' => __( 'Mail template', INCSUB_SBE_LANG_DOMAIN )
		);

		$args = array(
			'slug' => 'sbe-settings',
			'page_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Settings', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		$this->settings_name = Incsub_Subscribe_By_Email::$settings_slug;
		$this->settings_group = Incsub_Subscribe_By_Email::$settings_slug;
		$this->settings = Incsub_Subscribe_By_Email::$settings;

		add_action( 'admin_init', array( &$this, 'register_settings' ) );

		add_action( 'admin_init', array( &$this, 'restore_default_template' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

	}


	/**
	 * Enqueue needed scripts
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->id == $this->get_page_id() ) {

			if ( 'general' == $this->get_current_tab() ) {
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . '/js/settings-general.js', array( 'jquery' ), '20130721' );
			}
			elseif ( 'template' == $this->get_current_tab() ) {
				wp_enqueue_script( 'thickbox' );
			    wp_enqueue_script( 'media-upload' );
			    wp_enqueue_script( 'farbtastic' );
				wp_enqueue_script( 'sbe-settings-scripts', INCSUB_SBE_ASSETS_URL . '/js/settings-template.js', array( 'thickbox', 'media-upload' ), '20130721' );
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
			add_settings_field( 'frequency', __( 'Send How Often?', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_frequency_field' ), $this->get_menu_slug(), 'general-settings' ); 
			add_settings_field( 'mail_batch', __( 'Mail batches', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_mail_batches_field' ), $this->get_menu_slug(), 'general-settings' ); 

			add_settings_section( 'posts-settings', __( 'Posts Settings', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_posts_types_section' ), $this->get_menu_slug() );
			add_settings_field( 'post-types', __( 'Posts Types', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_posts_types_field' ), $this->get_menu_slug(), 'posts-settings' ); 

			add_settings_section( 'user-subs-page-settings', __( 'Subscription page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_section' ), $this->get_menu_slug() );
			add_settings_field( 'user-subs-page', __( 'Subscribers Management Page', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscription_page_field' ), $this->get_menu_slug(), 'user-subs-page-settings' ); 
		}
		elseif ( $this->get_current_tab() == 'template' ) {
			add_settings_section( 'style-settings', __( 'Styling Settings', INCSUB_SBE_LANG_DOMAIN ), null, $this->get_menu_slug() );
			add_settings_field( 'logo', __( 'Logo for notifications', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_logo_field' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'featured-images', __( 'Show featured images', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_featured_image' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'header-color', __( 'Header color', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_color_field' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'header-text-color', __( 'Header text color', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_text_color_field' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'header-text', __( 'Header text', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_header_text_field' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'footer-text', __( 'Footer text', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_footer_text_field' ), $this->get_menu_slug(), 'style-settings' ); 
			add_settings_field( 'subscribe-email-content', __( 'Subscribe Email Content', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_subscribe_email_content' ), $this->get_menu_slug(), 'style-settings' ); 

			add_settings_section( 'email-preview', __( 'Email preview', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_email_preview_section' ), $this->get_menu_slug() );
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

		$errors = get_settings_errors( $this->settings_name ); 
		if ( ! empty( $errors ) ) {
			?>	
				<div class="error">
					<ul>
						<?php
						foreach ( $errors as $error ) {
							?>
								<li><?php echo $error['message']; ?></li>
							<?php
						}
						?>
					</ul>
				</div>
			<?php
		}
		elseif ( isset( $_GET['settings-updated'] ) ) {
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
			<span><?php _e( 'You can use <strong>%title%</strong> wildcard to show the latest post title/s, they will be shorted to no more than 50 charactes', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	/**
	 * Subject field
	 */
	public function render_mail_batches_field() {
		?>
			<label for="mail_batches"><?php printf( __( 'Send %s mails every hour.', INCSUB_SBE_LANG_DOMAIN ), '<input id="mail_batches" type="number" name="' . $this->settings_name . '[mail_batches]" class="small-text" value="' . esc_attr( $this->settings['mails_batch_size'] ) . '">' ); ?></label><br/>
			<span class="description"><?php _e( 'If you are experiencing problems when sending mails, your server may not support so many sendings at the same time, try reducing this number. Mails will be sent every hour in groups of X mails.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
		<?php
	}

	/**
	 * Frequency field
	 */
	public function render_frequency_field() {
		$time_format = get_option( 'time_format', 'H:i' );
		?>
			<select name="<?php echo $this->settings_name; ?>[frequency]" id="frequency-select">
				<?php foreach ( Incsub_Subscribe_By_Email::$frequency as $key => $freq ): ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['frequency'] ); ?>><?php echo $freq; ?></option>
				<?php endforeach; ?>
			</select>
			<br/><br/>
			<div id="time-wrap">
				<label for="time-select"><?php _e( 'What time should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[time]" id="time-select">
						<?php foreach ( Incsub_Subscribe_By_Email::$time as $key => $t ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['time'] ); ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</label> 
				<span class="description"><?php printf( __( 'The time now is %s', INCSUB_SBE_LANG_DOMAIN ), date_i18n( $time_format ) ); ?></span>
			</div>

			<div id="day-of-week-wrap">
				<label for="day-of-week-select"><?php _e( 'What day of the week should the digest email be sent?', INCSUB_SBE_LANG_DOMAIN ); ?>
					<select name="<?php echo $this->settings_name; ?>[day_of_week]" id="day-of-week-select">
						<?php foreach ( Incsub_Subscribe_By_Email::$day_of_week as $key => $day ): ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $this->settings['day_of_week'] ); ?>><?php echo $day; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
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

	/**
	 * Post Types field
	 */
	public function render_posts_types_field() {
		$args=array(
		  'publicly_queryable'   => true,
		); 
		$post_types = get_post_types( $args, 'object' );
		unset( $post_types['attachment'] );
		
		foreach ( $post_types as $post_slug => $post_type ) {
			$label = $post_type->labels->name;
			?>
				<label for="post-type-<?php echo $post_slug; ?>">
					<input type="checkbox" <?php checked( in_array( $post_slug, $this->settings['post_types'] ) ); ?> id="post-type-<?php echo $post_slug; ?>" name="<?php echo $this->settings_name; ?>[post_types][]" value="<?php echo $post_slug; ?>"> 
					<?php echo $label; ?>
					<br/>
				</label>
			<?php
		}
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
			 <p><?php _e( "Users will be able to access to the page through a mail link. If you want to test it, just go to the page when you're logged in as administrator", INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
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


	/**
	 * Logo field
	 */
	public function render_featured_image() {
		?>
			<input type="checkbox" name="<?php echo $this->settings_name; ?>[featured_image]" id="featured-image" <?php checked( $this->settings['featured_image'] ); ?>> 
			<span class="description"><?php _e( 'If your theme allows it, every post in the mail will have its feature image on its left.', INCSUB_SBE_LANG_DOMAIN ); ?></span>
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
			if ( array_key_exists( $input['frequency'], Incsub_Subscribe_By_Email::$frequency ) )
				$new_settings['frequency'] = $input['frequency'];
			else
				$new_settings['frequency'] = Incsub_Subscribe_By_Email::$default_settings['frequency'];

			// For daily frequencies
			if ( 'daily' == $new_settings['frequency'] && array_key_exists( $input['time'], Incsub_Subscribe_By_Email::$time ) ) {
				$new_settings['time'] = $input['time'];
				if ( 'daily' != $this->settings['frequency'] || $input['time'] != $this->settings['time'] ) {
					// We have changed this setting
					Incsub_Subscribe_By_Email::set_next_day_schedule_time( $input['time'] );
				}
			}
			else {
				$new_settings['time'] = Incsub_Subscribe_By_Email::$default_settings['time'];
			}

			// For weekly frequencies
			if ( 'weekly' == $new_settings['frequency'] && array_key_exists( $input['day_of_week'], Incsub_Subscribe_By_Email::$day_of_week ) ) {
				$new_settings['day_of_week'] = $input['day_of_week'];

				if ( 'weekly' != $this->settings['frequency'] || $input['day_of_week'] != $this->settings['day_of_week'] ) {
					// We have changed this setting
					Incsub_Subscribe_By_Email::set_next_week_schedule_time( $input['day_of_week'] );
				}
			}
			else {
				$new_settings['day_of_week'] = Incsub_Subscribe_By_Email::$default_settings['day_of_week'];
			}

			// Post types
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				$new_settings['post_types'] = $input['post_types'];
			}

			// Management Page
			if ( isset( $input['manage_subs_page'] ) ) {
				$new_settings['manage_subs_page'] = absint( $input['manage_subs_page'] );
			}

			// Batches
		    if ( ! empty( $input['mail_batches'] ) )
				$new_settings['mails_batch_size'] = absint( $input['mail_batches'] );
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

			// Featured image
			if ( isset( $input['featured_image'] ) )
				$new_settings['featured_image'] = true;
			else
				$new_settings['featured_image'] = false;

			// Colors
			if ( preg_match( '/^#[a-f0-9]{6}$/i', $input['header_color'] ) )
				$new_settings['header_color'] = $input['header_color'];
			else 
		    	$new_settings['header_color'] = Incsub_Subscribe_By_Email::$default_settings['header_color'];

		    if ( preg_match( '/^#[a-f0-9]{6}$/i', $input['header_text_color'] ) )
				$new_settings['header_text_color'] = $input['header_text_color'];
			else 
		    	$new_settings['header_text_color'] = Incsub_Subscribe_By_Email::$default_settings['header_text_color'];

			
			
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

		return $new_settings;

	}

	public function restore_default_template() {
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && isset( $_GET['restore-template'] ) ) {
			$defaults = Incsub_Subscribe_By_Email::$default_settings;

			$this->settings['logo'] = $defaults['logo'];
			$this->settings['header_color'] = $defaults['header_color'];
			$this->settings['header_text_color'] = $defaults['header_text_color'];
			$this->settings['featured_image'] = $defaults['featured_image'];

			update_option( $this->settings_name, $this->settings );

			wp_redirect( $this->get_permalink() );
		}
	}

	

}