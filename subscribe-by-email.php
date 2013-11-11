<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: S H Mohanjith (Incsub), Ignacio (Incsub)
Version: 2.4.8
Author URI: http://premium.wpmudev.org
WDP ID: 127
Text Domain: subscribe-by-email
*/


class Incsub_Subscribe_By_Email {

	static $freq_weekly_transient_slug = 'next_week_scheduled';
	static $freq_daily_transient_slug = 'next_day_scheduled';

	static $pending_mails_transient_slug = 'sbe_pending_mails_sent';

	// Max mail subject length
	static $max_subject_length = 120;

	// Time between batches
	static $time_between_batches = 1800;

	// Max time in seconds during which the user can activate his subscription
	static $max_confirmation_time = 604800;

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

		add_action( 'init', array( &$this, 'init_plugin' ), 1 );

		add_action( 'init', array( &$this, 'confirm_subscription' ), 2 );
		add_action( 'init', array( &$this, 'cancel_subscription' ), 20 );
		
		
		add_action( 'transition_post_status', array( &$this, 'process_instant_subscriptions' ), 2, 3);
		add_action( 'init', array( &$this, 'process_scheduled_subscriptions' ), 2, 3);

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'widgets_init', array( &$this, 'widget_init' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

	}

	public function init_plugin() {
		
		// Do we have to remove old subscriptions?
		$transient = get_transient( 'sbe_remove_old_subscriptions' );
		if ( ! $transient ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->remove_old_subscriptions();
			set_transient( 'sbe_remove_old_subscriptions', true, 86400 );
		}

		$this->maybe_upgrade();

		$this->maybe_send_pending_emails();

		if ( ! is_admin() )
			$manage_subscription_page = new Incsub_Subscribe_By_Email_Manage_Subscription();

		self::$admin_subscribers_page = new Incsub_Subscribe_By_Email_Admin_Subscribers_Page();
		self::$admin_add_new_subscriber_page = new Incsub_Subscribe_By_Email_Admin_Add_Subscribers_Page();
		self::$admin_export_subscribers_page = new Incsub_Subscribe_By_Email_Export_Subscribers_Page();
		self::$admin_settings_page = new Incsub_Subscribe_By_Email_Admin_Settings_Page();
		self::$admin_sent_emails_page = new Incsub_Subscribe_By_Email_Sent_Emails_Page();

		$this->init_follow_button();
	}

	public function init_follow_button() {
		if ( ! is_admin() ) {
			$settings = incsub_sbe_get_settings();

			if ( ! $settings['follow_button'] )
				return;

			new Incsub_Subscribe_By_Email_Follow_Button( $settings );
		}
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
		define( 'INCSUB_SBE_VERSION', '2.4.8' );
		define( 'INCSUB_SBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'INCSUB_SBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'INCSUB_SBE_LOGS_DIR', WP_CONTENT_DIR . '/subscribe-by-email-logs' );

		define( 'INCSUB_SBE_LANG_DOMAIN', 'subscribe-by-email' );

		define( 'INCSUB_SBE_ASSETS_URL', INCSUB_SBE_PLUGIN_URL . 'assets/' );

		define( 'INCSUB_SBE_PLUGIN_FILE', plugin_basename( __FILE__ ) );

		if ( ! defined( 'INCSUB_SBE_DEBUG' ) )
			define( 'INCSUB_SBE_DEBUG', false );

	}

	/**
	 * Include needed files
	 */
	private function includes() {
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/subscribers-table.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/log-table.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/uninstall.php' );
			

		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-settings-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-subscribers-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-add-subscribers-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-export-subscribers-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/admin-sent-emails-page.php' );

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/settings.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/content-generator.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/mail-template.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/confirmation-mail-template.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/walker-terms-checklist.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/dash-notifications.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'model/model.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/widget.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/follow-button.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/manage-subscription.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/helpers.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/logger.php' );


	}

	public function activate() {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$model->create_squema();
	}

	public function deactivate() {
	}


	

	/**
	 * Upgrade settings and tables to the new version
	 */
	public function maybe_upgrade() {

		$current_version = get_option( 'incsub_sbe_version' );

		if ( ! $current_version )
			$current_version = '1.0'; // This is the first version that includes some upgradings

		if ( version_compare( $current_version, '1.0', '<=' ) ) {
			$new_settings = array();

			$new_settings['auto-subscribe'] = false;

			$defaults = incsub_sbe_get_default_settings();
			$new_settings['from_email'] = get_option( 'subscribe_by_email_instant_notification_from', $defaults['from_email'] );

			$new_settings['subject'] = get_option( 'subscribe_by_email_instant_notification_subject', $defaults['subject'] );

			$new_settings['subject'] = str_replace( 'BLOGNAME', get_bloginfo( 'name' ), $new_settings['subject'] );
			$new_settings['subject'] = str_replace( 'EXCERPT', '', $new_settings['subject'] );
			$new_settings['subject'] = str_replace( 'POST_TITLE', '', $new_settings['subject'] );

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->create_squema();
			$model->upgrade_schema();				

			incsub_sbe_update_settings( $new_settings );

			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
		}


		if ( version_compare( $current_version, '2.4.3', '<' ) ) {
			$settings = incsub_sbe_get_settings();
			if ( isset( $settings['taxonomies']['post']['categories'] ) ) {
				$categories = $settings['taxonomies']['post']['categories'];
				$settings['taxonomies']['post']['category'] = $categories;
				unset( $settings['taxonomies']['post']['categories'] );
				incsub_sbe_update_settings( $settings );
			}

			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		}

		if ( version_compare( $current_version, '2.4.4', '<' ) ) {
			$settings = incsub_sbe_get_settings();

			delete_transient( self::$freq_daily_transient_slug );
			delete_transient( self::$freq_weekly_transient_slug );
			if ( 'weekly' == $settings['frequency'] ) {
				self::set_next_week_schedule_time( $settings['day_of_week'] );
			}
			
			if ( 'daily' == $settings['frequency'] ) {
				self::set_next_day_schedule_time( $settings['time'] );
			}

			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		}

		if ( version_compare( $current_version, '2.4.7b', '<' ) ) {

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->create_squema();

			$model->upgrade_247b();

			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		}

		if ( version_compare( $current_version, '2.4.7RC1', '<' ) ) {

			$settings = incsub_sbe_get_settings();

			$post_types = $settings['post_types'];
			$taxonomies = $settings['taxonomies'];

			$new_taxonomies = $taxonomies;
			foreach ( $post_types as $post_type ) {
				$post_type_taxonomies = get_object_taxonomies( $post_type );
				if ( ! array_key_exists( $post_type, $taxonomies ) && ! empty( $post_type_taxonomies ) ) {
					foreach ( $post_type_taxonomies as $taxonomy_slug ) {
						$taxonomy = get_taxonomy( $taxonomy_slug );

						if ( $taxonomy->hierarchical ) {
							$new_taxonomies[ $post_type ][ $taxonomy_slug ] = array( 'all' );
						}
					}
				}
			}

			$settings['taxonomies'] = $new_taxonomies;
			incsub_sbe_update_settings( $settings );

			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		}

		if ( version_compare( $current_version, '2.4.7RC2', '<' ) ) {
			$model = incsub_sbe_get_model();
			$model->create_squema();
			update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
		}
		
	}


	/**
	 * Subscribe a new user
	 * 
	 * @param Integer $subscription_id 
	 */
	public static function subscribe_user( $user_email, $note, $type, $autopt = false ) {
		
		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		$sid = $model->add_subscriber( $user_email, $note, $type, 0 );

		if ( $autopt && $sid ) {

			$user_key = $model->get_user_key( $user_email );

			if ( $user_key ) {
				$model->confirm_subscription( $user_key );
			}

			return true;
		}

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
		$settings = incsub_sbe_get_settings();

		$subscriber = $model->get_subscriber( $subscription_id );
		$confirmation_mail = new Incsub_Subscribe_By_Email_Confirmation_Template( $settings, $subscriber->subscription_email );
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
			$subscriber = $model->get_subscriber_by_key( $_GET['sbe_unsubscribe'] );
			$model->cancel_subscription( $_GET['sbe_unsubscribe'] );

			$this->sbe_subscribing_notice( __( 'Your email subscription has been succesfully cancelled.', INCSUB_SBE_LANG_DOMAIN ) );
			
			$settings = incsub_sbe_get_settings();
			if ( $settings['get_notifications'] ) {
				$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Unsubscribed_Notice_Template( $subscriber->subscription_email );
				$admin_notice->send_email();
			}

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
				<a href="<?php echo get_home_url(); ?>"><?php _e( 'Go to site', INCSUB_SBE_LANG_DOMAIN ); ?></a>
			</div>
		<?php
	}

	private function subscribe_notices_styles() {
		global $content_width;

		if ( empty( $content_width ) )
			$content_width = 900;

		$settings = incsub_sbe_get_settings();

		?>
			<style>
				body {
					background-color:#EFEFEF;
					padding:25px;
				}
				.sbe-notice {
					border-top:5px solid <?php echo $settings['header_color']; ?>;
					border-bottom:5px solid <?php echo $settings['header_color']; ?>;
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

		if ( ! get_transient( self::$pending_mails_transient_slug ) ) {

			set_transient( self::$pending_mails_transient_slug, 'next', self::$time_between_batches );

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$mail_log = $model->get_remaining_batch_mail();

			if ( ! empty( $mail_log ) ) {

				$mail_settings = maybe_unserialize( $mail_log['mail_settings'] );
				$posts_ids = $mail_settings['posts_ids'];
				$log_id = absint( $mail_log['id'] );

				$this->send_mails( $posts_ids, $log_id );	
			}

		}
		
	}

	/**
	 * Send the emails to all the subscribers based on the Settings
	 * 
	 * @param Array $posts_ids A list of posts IDs to send. If not provided,
	 * the mail template will select them automatically
	 */
	public function send_mails( $posts_ids = array(), $log_id = false ) {
		
		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		$settings = incsub_sbe_get_settings();
		$args = $settings;
		if ( ! empty( $posts_ids ) )
			$args['post_ids'] = $posts_ids;
		else
			$args['post_ids'] = array();

		$mail_template = new Incsub_Subscribe_By_Email_Template( $args, false );

		if ( ! $log_id )
			$log_id = $model->add_new_mail_log( '' );


		$mail_template->send_mail( $log_id );

		return $log_id;
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
		$settings = incsub_sbe_get_settings();

		if ( in_array( $post->post_type, $settings['post_types'] ) && $new_status != $old_status && 'publish' == $new_status && $settings['frequency'] == 'inmediately' ) {
			//send emails
			$this->send_mails( array( $post->ID ) );	
		}
	}

	public function process_scheduled_subscriptions() {
		$settings = incsub_sbe_get_settings();

		if ( 'weekly' == $settings['frequency'] && $next_time = get_option( self::$freq_weekly_transient_slug ) ) {
			if ( current_time( 'timestamp' ) > $next_time ) {
				self::set_next_week_schedule_time( $settings['day_of_week'] );
				$this->send_mails();
			}
		}
		elseif ( 'daily' == $settings['frequency'] && $next_time = get_option( self::$freq_daily_transient_slug ) ) {
			if ( current_time( 'timestamp' ) > $next_time ) {
				self::set_next_day_schedule_time( $settings['time'] );
				$this->send_mails();
			}
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

		$next_time = strtotime( 'next ' . $day, current_time( 'timestamp' ) );
		
		update_option( self::$freq_weekly_transient_slug, $next_time );
		delete_option( self::$freq_daily_transient_slug );

	}

	/**
	 * Set the next schedule for a daily frequency
	 * 
	 * @param Integer $time Hour of a day 
	 */
	public static function set_next_day_schedule_time( $time ) {
		$today = date( 'Y-m-d', current_time( 'timestamp' ) );
		$today_at_time = $today . ' ' . str_pad( $time, 2, 0, STR_PAD_LEFT ) . ':00:00';
		$today_at_time_unix = strtotime( $today_at_time );
		$next_time = strtotime( '+1 day', $today_at_time_unix );
		$next_time_mysql = date( 'Y-m-d H:i:s', $next_time );

		update_option( self::$freq_daily_transient_slug, $next_time );
		delete_option( self::$freq_weekly_transient_slug );
	}

	public static function get_next_scheduled_date() {
		$settings = incsub_sbe_get_settings();
		if ( 'daily' == $settings['frequency'] && $time = get_option( self::$freq_daily_transient_slug ) ) {
			return date_i18n( get_option('date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s') , $time );
		}

		if ( 'weekly' == $settings['frequency'] && $time = get_option( self::$freq_weekly_transient_slug ) ) {
			return date_i18n( get_option('date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s') , $time );
		}

		
	}



}

global $subscribe_by_email_plugin;
$subscribe_by_email_plugin = new Incsub_Subscribe_By_Email();
