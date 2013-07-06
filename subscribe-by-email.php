<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: S H Mohanjith (Incsub), Ignacio (Incsub)
Version:2.1
Author URI: http://premium.wpmudev.org
WDP ID: 127
Text Domain: subscribe-by-email
*/

class Incsub_Subscribe_By_Email {

	// Settings of the plugin
	static $settings;
	static $default_settings;

	// Settings properties
	static $frequency;
	static $time;
	static $day_of_week;
	static $confirmation_flag;

	static $freq_weekly_transient_slug = 'next_week_scheduled';
	static $freq_daily_transient_slug = 'next_day_scheduled';

	// Settings slug name
	static $settings_slug = 'incsub_sbe_settings';

	// Max time in seconds during which the user can activate his subscription
	static $max_confirmation_time;

	// Max mail subject length
	static $max_subject_length = 120;

	//Menus
	static $admin_subscribers_page;
	static $admin_add_new_subscriber_page;
	static $admin_settings_page;
	static $admin_sent_emails_page;
	static $admin_export_subscribers_page;

	// Widget
	public $widget;


	public function __construct() {
		
		$this->set_globals();
		$this->includes();

		add_action( 'init', array( &$this, 'set_settings' ), 1 );

		add_action( 'init', array( &$this, 'set_admin_menus' ), 15 );

		add_action( 'init', array( &$this, 'confirm_subscription' ), 1 );
		add_action( 'init', array( &$this, 'cancel_subscription' ), 20 );
		
		add_action( 'transition_post_status', array( &$this, 'process_instant_subscriptions' ), 2, 3);
		add_action( 'init', array( &$this, 'process_scheduled_subscriptions' ), 2, 3);

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'widgets_init', array( &$this, 'widget_init' ) );

		// add_action( 'add_user_to_blog', array( &$this, 'add_user_to_blog' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

	}


	public function widget_init() {
		register_widget( 'Incsub_Subscribe_By_Email_Widget' );
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'sbe-admin-icon', INCSUB_SBE_ASSETS_URL . 'css/icon-styles.css', array(), '20130605' );
	}

	public function load_text_domain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), INCSUB_SBE_LANG_DOMAIN );

		load_textdomain( INCSUB_SBE_LANG_DOMAIN, WP_LANG_DIR . '/' . INCSUB_SBE_LANG_DOMAIN . '/' . INCSUB_SBE_LANG_DOMAIN . '-' . $locale . '.mo' );
		load_plugin_textdomain( INCSUB_SBE_LANG_DOMAIN, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Set the globals variables/constants
	 */
	private function set_globals() {
		define( 'INCSUB_SBE_VERSION', '2.1' );
		define( 'INCSUB_SBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'INCSUB_SBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

		define( 'INCSUB_SBE_LANG_DOMAIN', 'subscribe-by-email' );

		define( 'INCSUB_SBE_ASSETS_URL', INCSUB_SBE_PLUGIN_URL . 'assets/' );
	}

	/**
	 * Include needed files
	 */
	private function includes() {
		if ( is_admin() ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-settings-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-subscribers-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-add-subscribers-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-export-subscribers-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-sent-emails-page.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/subscribers-table.php' );
			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/log-table.php' );
			
		}
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-template.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/confirmation-mail-template.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'model/model.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/widget.php' );
	}

	public function activate() {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$model->create_squema();
	}

	public function deactivate() {
		delete_option( 'incsub_sbe_version' );
	}


	/**
	 * Set the settings for the plugin
	 */
	public function set_settings() {

	
		if ( is_multisite() ) {
			$blog_details = get_blog_details( get_current_blog_id() );
			$site_url = $blog_details->siteurl;
		}
		else {
			$site_url = site_url();
		}

		global $wp_locale;

		self::$settings_slug = 'incsub_sbe_settings';

		// The user can choose between these options
		self::$frequency = array(
			'inmediately' 	=> __( 'Immediately when a new post is published', INCSUB_SBE_LANG_DOMAIN ),
			'weekly'		=> __( 'Send a weekly digest with all posts from the previous week', INCSUB_SBE_LANG_DOMAIN ),
			'daily'			=> __( 'Send a daily digest with all posts from the previous 24 hours', INCSUB_SBE_LANG_DOMAIN )
		);

		self::$time = array();
		for ( $i = 0; $i < 24; $i++ ) {
			self::$time[$i] = str_pad( $i, 2, 0, STR_PAD_LEFT ) . ':00';
		}

		self::$day_of_week = $wp_locale->weekday;

		self::$confirmation_flag = array(
			0 => __( 'Awaiting confirmation'),
			1 => __( 'Email confirmed')
		);

		self::$max_confirmation_time = 604800;

		$this->maybe_upgrade();

		$current_settings = get_option( self::$settings_slug );

		$base_domain = str_replace( 'http://', '', $site_url );
		$base_domain = str_replace( 'https://', '', $base_domain );

		self::$default_settings = array(
			'auto-subscribe' => false,
			'subscribe_new_users' => false,
			'from_email' => 'no-reply@' . $base_domain,
			'from_sender' => get_bloginfo( 'name' ),
			'subject' => get_bloginfo( 'name' ) . __( ': New post' ),
			'frequency' => 'inmediately',
			'time' => 0,
			'day_of_week' => 0,
			'logo' => '',
			'featured_image' => false,
			'header_text' => '',
			'footer_text' => '',
			'header_color' => '#66aec2',
			'header_text_color' => '#000000',
			'mails_batch_size' => 80
		);

		var_dump(self::$default_settings);

		self::$settings = wp_parse_args( $current_settings, self::$default_settings );

		update_option( self::$settings_slug, self::$settings );

		// Do we have to remove old subscriptions?
		$transient = get_transient( 'sbe_remove_old_subscriptions' );
		if ( ! $transient ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->remove_old_subscriptions();
			set_transient( 'sbe_remove_old_subscriptions', true, 86400 );
		}

		$this->maybe_send_pending_emails();

	}

	/**
	 * Upgrade settings and tables to the new version
	 */
	public function maybe_upgrade() {
		$current_version = get_option( 'incsub_sbe_version', '1.0.0' );

		// We're going to join all options into one
		if ( 0 > version_compare($current_version, INCSUB_SBE_VERSION) ) {
			
			$new_settings = array();

			$new_settings['auto-subscribe'] = false;

			$new_settings['from_email'] = get_option( 'subscribe_by_email_instant_notification_from', get_option('admin_email') );

			$new_settings['subject'] = get_option( 'subscribe_by_email_instant_notification_subject', get_bloginfo( 'name' ) . __( ': New post' ) );

			$new_settings['subject'] = str_replace( 'BLOGNAME', get_bloginfo( 'name' ), $new_settings['subject'] );
			$new_settings['subject'] = str_replace( 'EXCERPT', '', $new_settings['subject'] );
			$new_settings['subject'] = str_replace( 'POST_TITLE', '', $new_settings['subject'] );

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->create_squema();
			$model->upgrade_schema();				

			update_option( self::$settings_slug, $new_settings );
			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		}
	}

	/**
	 * Initialize admin menus
	 */
	public function set_admin_menus() {
		if ( is_admin() ) {
			self::$admin_subscribers_page = new Incsub_Subscribe_By_Email_Admin_Subscribers_Page();
			self::$admin_add_new_subscriber_page = new Incsub_Subscribe_By_Email_Admin_Add_Subscribers_Page();
			self::$admin_export_subscribers_page = new Incsub_Subscribe_By_Email_Export_Subscribers_Page();
			self::$admin_settings_page = new Incsub_Subscribe_By_Email_Admin_Settings_Page();
			self::$admin_sent_emails_page = new Incsub_Subscribe_By_Email_Sent_Emails_Page();
		}
	}

	/**
	 * Triggered when a new user is added to the blog
	 * 
	 * @param type $user_id 
	 * @return type
	 */
	//public function add_user_to_blog( $user_id, $role, $blog_id ) {
//
	//	// The settings ares till not loaded
	//	$this->set_settings();
//
	//	if ( self::$settings['subscribe_new_users'] ) {
	//		switch_to_blog( $blog_id );
	//		$user = get_userdata( $user_id );
	//		self::subscribe_user( $user->data->user_email, __( 'Manual Subscription', INCSUB_SBE_LANG_DOMAIN ), __( 'Instant', INCSUB_SBE_LANG_DOMAIN ) );
	//		restore_current_blog();
	//	}
	//	
	//}

	/**
	 * Subscribe a new user
	 * 
	 * @param Integer $subscription_id 
	 */
	public static function subscribe_user( $user_email, $note, $type ) {

		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		$sid = $model->add_subscriber( $user_email, $note, $type, 0 );

		if ( $sid ) {
			self::send_confirmation_mail( $sid );
			return true;	
		}

		return false;
		
	}

	/**
	 * Sends a confirmation mail. Make uses of Confirmation Mail Template
	 * 
	 * @param Integer $subscription_id 
	 */
	public static function send_confirmation_mail( $subscription_id ) {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$subscriber = $model->get_subscriber( $subscription_id );
		$confirmation_mail = new Incsub_Subscribe_By_Email_Confirmation_Template( self::$settings, $subscriber->subscription_email );
		$confirmation_mail->send_mail();
	}

	/**
	 * Loaded on front page, confirm a subscription linked from a user mail
	 */
	public function confirm_subscription() {
		if ( isset( $_GET['sbe_confirm'] ) ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();

			if ( ! $model->is_already_confirmed( $_GET['sbe_confirm'] ) ) {
				$result = $model->confirm_subscription( $_GET['sbe_confirm'] );

				if ( ! $result ) {
					$this->sbe_subscribing_notice( __( 'Sorry, your subscription no longer exists, please subscribe again.', INCSUB_SBE_LANG_DOMAIN ) );
				}
				else {
					$this->sbe_subscribing_notice( __( 'Thank you, your subscription has been confirmed.', INCSUB_SBE_LANG_DOMAIN ) );
				}

				die();
			}
		}
	}

	/**
	 * Loaded on front page, cancel a subscription linked from a user mail
	 */
	public function cancel_subscription() {
		if ( isset( $_GET['sbe_unsubscribe'] ) ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->cancel_subscription( $_GET['sbe_unsubscribe'] );

			$this->sbe_subscribing_notice( __( 'Your email subscription has been succesfully cancelled.', INCSUB_SBE_LANG_DOMAIN ) );
			die();
		}
	}


	private function sbe_subscribing_notice( $text ) {
		$this->subscribe_notices_styles();
		?>
			<div class="sbe-notice">
				<p>
					<?php echo $text; ?>
				</p><br/><br/>
				<a href="<?php echo site_url(); ?>"><?php _e( 'Go to site', INCSUB_SBE_LANG_DOMAIN ); ?></a>
			</div>
		<?php
	}

	private function subscribe_notices_styles() {
		global $content_width;

		if ( empty( $content_width ) )
			$content_width = 900;

		?>
			<style>
				body {
					background-color:#EFEFEF;
					padding:25px;
				}
				.sbe-notice {
					border-top:5px solid <?php echo self::$settings['header_color']; ?>;
					border-bottom:5px solid <?php echo self::$settings['header_color']; ?>;
					background-color:#FFF;
					width:<?php echo $content_width; ?>px;
					margin: 0 auto;
					margin-top:15px;
					padding:25px;
				}
				.sbe-notice p {
					color:#333 !important;
					margin:0;
				}
				.sbe-notice a {
					background-color:#278AB6;
					border-radius:25px;
					text-decoration:none;
					color: #FFF !important;
					display: inline-block;
					line-height: 23px;
					height: 24px;
					padding: 0 10px 1px;
					cursor:pointer;
					box-sizing: border-box;
					font-size:12px;
					border:1px solid transparent;
				}
				.sbe-notice a:hover {
					border:1px solid #555;
				}
			</style>
		<?php
	}

	/**
	 * Send a batch of mails
	 * 
	 */
	public function maybe_send_pending_emails() {

		if ( ! get_transient( 'sbe_pending_mails_sent' ) ) {

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$mail_log = $model->get_remaining_batch_mail();

			if ( ! empty( $mail_log ) ) {
				$mail_settings = maybe_unserialize( $mail_log['mail_settings'] );
				$emails_from = absint( $mail_settings['email_from'] );
				$posts_ids = $mail_settings['posts_ids'];
				$log_id = $mail_log['id'];

				$this->send_mails( $posts_ids, $emails_from, $log_id );	
			}

			set_transient( 'sbe_pending_mails_sent', 'next', 60 );
		}
		
	}

	/**
	 * Send the emails to all the subscribers based on the Settings
	 * 
	 * @param Array $posts_ids A list of posts IDs to send. If not provided,
	 * the mail template will select them automatically
	 */
	public function send_mails( $posts_ids = array(), $email_from = false, $log_id = false ) {
		
		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		$emails_list = $model->get_email_list();

		if ( $email_from ) {

			$last_id = 0;

			// We need to know where did we finish last time
			foreach ( $emails_list as $key => $email ) {
				$last_id = $key;
				if ( absint( $email_from ) == absint( $email['id'] ) ) {
					$last_id++;
					break;
				}
			}

			// Just need the rest of the mails
			$emails_list = array_slice( $emails_list, absint( $last_id ) );
		}

		$mail_template = new Incsub_Subscribe_By_Email_Template( self::$settings, false );

		if ( ! empty( $posts_ids ) )
			$mail_template->set_posts( $posts_ids );

		$mail_template->send_mail( $emails_list, $log_id );
	}

	/**
	 * Executed each time a post changes its status. If the user
	 * wants to send a newsletter everytime a post is published
	 * it will call send_mail function
	 * 
	 * @param String $new_status 
	 * @param String $old_status 
	 * @param Object $post 
	 * @return type
	 */
	public function process_instant_subscriptions( $new_status, $old_status, $post ) {
		if ( $post->post_type == 'post' && $new_status != $old_status && 'publish' == $new_status && self::$settings['frequency'] == 'inmediately' ) {
			//send emails
			$this->send_mails( array( $post->ID ) );	
		}
	}

	public function process_scheduled_subscriptions() {

		if ( 'weekly' == self::$settings['frequency'] && ! get_transient( self::$freq_weekly_transient_slug ) ) {
			self::set_next_week_schedule_time( self::$settings['day_of_week'] );
			$this->send_mails();
		}
		elseif ( 'daily' == self::$settings['frequency'] && ! get_transient( self::$freq_daily_transient_slug ) ) {
			self::set_next_day_schedule_time( self::$settings['time'] );
			$this->send_mails();
		}

	}

	/**
	 * Set the next schedule for a weekly frequency
	 * 
	 * @param String $day_of_week 
	 */
	public static function set_next_week_schedule_time( $day_of_week ) {
		switch ( $day_of_week ) {
			case 0:
				$day = 'sunday';
				break;
			case 1:
				$day = 'monday';
				break;
			case 2:
				$day = 'tuesday';
				break;
			case 3:
				$day = 'wednesday';
				break;
			case 4:
				$day = 'thursday';
				break;
			case 5:
				$day = 'friday';
				break;
			case 6:
				$day = 'saturday';
				break;
		}

		$next_time = strtotime( 'next ' . $day );
		$seconds_to_next_week = $next_time - time();
		
		set_transient( self::$freq_weekly_transient_slug, 'weekly', $seconds_to_next_week );
		delete_transient( self::$freq_daily_transient_slug );

	}

	/**
	 * Set the next schedule for a daily frequency
	 * 
	 * @param Integer $time Hour of a day 
	 */
	public static function set_next_day_schedule_time( $time ) {
		$today = date( 'Y-m-d', time() );
		$today_at_time = $today . ' ' . str_pad( $time, 2, 0, STR_PAD_LEFT ) . ':00:00';
		$today_at_time_unix = strtotime( $today_at_time );
		$next_time = strtotime( '+1 day', $today_at_time_unix );
		$seconds_to_next_day = $next_time - time();

		set_transient( self::$freq_daily_transient_slug, 'daily', $seconds_to_next_day );
		delete_transient( self::$freq_weekly_transient_slug );
	}



}

new Incsub_Subscribe_By_Email();
