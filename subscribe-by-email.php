<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: WPMU DEV
Version: 2.7
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
	static $time_between_batches = 1200;

	// Max time in seconds during which the user can activate his subscription
	static $max_confirmation_time = 604800;

	//Menus
	static $admin_subscribers_page;
	static $admin_add_new_subscriber_page;
	static $admin_settings_page;
	static $network_settings_page;
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
		add_action( 'init', array( &$this, 'maybe_delete_logs' ) );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'widgets_init', array( &$this, 'widget_init' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

		add_action( 'wpmu_drop_tables', array( &$this, 'uninstall' ) );

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

		if ( ! is_admin() ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'front/manage-subscription.php' );
			$manage_subscription_page = new Incsub_Subscribe_By_Email_Manage_Subscription();
		}

		self::$admin_subscribers_page = new Incsub_Subscribe_By_Email_Admin_Subscribers_Page();
		self::$admin_add_new_subscriber_page = new Incsub_Subscribe_By_Email_Admin_Add_Subscribers_Page();
		self::$admin_settings_page = new Incsub_Subscribe_By_Email_Admin_Settings_Page();
		self::$admin_sent_emails_page = new Incsub_Subscribe_By_Email_Sent_Emails_Page();

		if ( is_multisite() ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/network-settings-page.php' );
			self::$network_settings_page = new Incsub_Subscribe_By_Email_Network_Settings_Page();
		}

		$this->init_follow_button();

		new Subscribe_By_Email_Shortcode();
	}

	public function init_follow_button() {
		if ( ! is_admin() ) {
			$settings = incsub_sbe_get_settings();

			if ( ! $settings['follow_button'] )
				return;

			require_once( INCSUB_SBE_PLUGIN_DIR . 'front/follow-button.php' );
			new Incsub_Subscribe_By_Email_Follow_Button( $settings );
		}
	}


	public function widget_init() {
		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/widget.php' );
		register_widget( 'Incsub_Subscribe_By_Email_Widget' );
	}

	public function enqueue_styles() {
		global $wp_version;

		if ( version_compare( $wp_version, '3.8', '>=' ) || is_plugin_active( 'mp6/mp6.php' ) ) {
			wp_enqueue_style( 'sbe-admin-icon', INCSUB_SBE_ASSETS_URL . 'css/icon-38-styles.css', array(), '20140114' );
		}
		else {
			wp_enqueue_style( 'sbe-admin-icon', INCSUB_SBE_ASSETS_URL . 'css/icon-styles.css', array(), '20140115' );
		}
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
		define( 'INCSUB_SBE_VERSION', '2.7' );
		define( 'INCSUB_SBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'INCSUB_SBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'INCSUB_SBE_LOGS_DIR', WP_CONTENT_DIR . '/subscribe-by-email-logs' );

		define( 'INCSUB_SBE_LANG_DOMAIN', 'subscribe-by-email' );

		define( 'INCSUB_SBE_ASSETS_URL', INCSUB_SBE_PLUGIN_URL . 'assets/' );

		define( 'INCSUB_SBE_PLUGIN_FILE', plugin_basename( __FILE__ ) );
		define( 'INCSUB_SBE_DEBUG', false );

	}

	/**
	 * Include needed files
	 */
	private function includes() {		

		// Admin pages
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/admin-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/admin-settings-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/admin-subscribers-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/admin-add-subscribers-page.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/pages/admin-sent-emails-page.php' );

		// Settings handler
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/settings.php' );
		
		// WPMUDEV Dashboard class
		if ( is_admin() ) {
			global $wpmudev_notices;
			$wpmudev_notices[] = array( 
				'id'=> 127,
				'name'=> 'Subscribe By Email', 
				'screens' => array( 
					'toplevel_page_sbe-subscribers',
					'subscriptions_page_sbe-add-subscribers',
					'subscriptions_page_sbe-settings',
					'subscriptions_page_sbe-sent-mails' 
				) 
			);
			include_once( INCSUB_SBE_PLUGIN_DIR . 'externals/wpmudev-dash-notification.php' );
		}

		// Model
		require_once( INCSUB_SBE_PLUGIN_DIR . 'model/model.php' );

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/integration.php' );

		require_once( INCSUB_SBE_PLUGIN_DIR . 'front/shortcode.php' );

		// Helpers
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/helpers/general-helpers.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/helpers/extra-fields-helpers.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/helpers/subscriber-helpers.php' );

		// Log class
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/logger.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/debugger.php' );

		// Subscriber class
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/classes/subscriber.php' );


	}

	public function activate() {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$model->create_squema();
		update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
	}

	public function deactivate() {
	}

	public function uninstall( $tables ) {
		$model = incsub_sbe_get_model();
		$new_tables = $model->get_tables_list();
		return array_merge( $tables, $new_tables );
	}


	

	/**
	 * Upgrade settings and tables to the new version
	 */
	public function maybe_upgrade() {

		$current_version = get_option( 'incsub_sbe_version' );

		if ( ! $current_version )
			$current_version = '1.0'; // This is the first version that includes some upgradings

		if ( $current_version == INCSUB_SBE_VERSION )
			return;

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

		}


		if ( version_compare( $current_version, '2.4.3', '<' ) ) {
			$settings = incsub_sbe_get_settings();
			if ( isset( $settings['taxonomies']['post']['categories'] ) ) {
				$categories = $settings['taxonomies']['post']['categories'];
				$settings['taxonomies']['post']['category'] = $categories;
				unset( $settings['taxonomies']['post']['categories'] );
				incsub_sbe_update_settings( $settings );
			}


		}

		if ( version_compare( $current_version, '2.4.4', '<' ) ) {
			$settings = incsub_sbe_get_settings();

			delete_transient( self::$freq_daily_transient_slug );
			delete_transient( self::$freq_weekly_transient_slug );
			if ( 'weekly' == $settings['frequency'] ) {
				self::set_next_week_schedule_time( $settings['day_of_week'], $settings['time'] );
			}
			
			if ( 'daily' == $settings['frequency'] ) {
				self::set_next_day_schedule_time( $settings['time'] );
			}


		}

		if ( version_compare( $current_version, '2.4.7b', '<' ) ) {

			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$model->create_squema();

			$model->upgrade_247b();


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


		}

		if ( version_compare( $current_version, '2.4.7RC2', '<' ) ) {
			$model = incsub_sbe_get_model();
			$model->create_squema();
		}

		if ( version_compare( $current_version, '2.4.9', '<' ) ) {

			set_transient( 'incsub_sbe_updating', true, 1800 );
			$model = incsub_sbe_get_model();
			$model->create_squema();
			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
			incsub_sbe_upgrade_249();


			delete_transient( 'incsub_sbe_updating' );

		}

		if ( version_compare( $current_version, '2.5', '<' ) ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
			incsub_sbe_upgrade_25();
		}

		if ( version_compare( $current_version, '2.7', '<' ) ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/upgrades.php' );
			incsub_sbe_upgrade_27();
		}

		update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
		
	}


	/**
	 * Subscribe a new user
	 * 
	 * @param Integer $subscription_id 
	 */
	public static function subscribe_user( $user_email, $note, $type, $autopt = false, $meta = array() ) {
		
		$subscribe_user = apply_filters( 'sbe_pre_subscribe_user', true, $user_email, $note, $type, $autopt, $meta );

		if ( ! $subscribe_user )
			return false;
		
		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		if ( $model->is_already_subscribed( $user_email ) ) {
			$subscriber = incsub_sbe_get_subscriber( $user_email );
			if ( $subscriber && ! $subscriber->get_confirmation_flag() ) {
				self::send_confirmation_mail( $subscriber->get_subscription_ID() );
			}
			else {
				return false;
			}
		}

		$sid = $model->add_subscriber( $user_email, $note, $type, 0 );

		if ( $sid && ! empty( $meta ) ) {
			foreach ( $meta as $meta_key => $meta_value ) {
				$model->add_subscriber_meta( $sid, $meta_key, $meta_value );
			}
		}

		if ( $autopt && $sid ) {

			$user_key = $model->get_user_key( $user_email );

			if ( $user_key ) {
				$model->confirm_subscription( $user_key );
			}

			return $sid;
		}

		if ( $sid ) {
			self::send_confirmation_mail( $sid );
			return $sid;	
		}

		return false;
		
	}

	/**
	 * Sends a confirmation mail. Make uses of Confirmation Mail Template
	 * 
	 * @param Integer $subscription_id 
	 */
	public static function send_confirmation_mail( $subscription_id ) {
		$model = incsub_sbe_get_model();
		$settings = incsub_sbe_get_settings();

		$subscriber = incsub_sbe_get_subscriber( $subscription_id );

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/confirmation-mail-template.php' );
		$confirmation_mail = new Incsub_Subscribe_By_Email_Confirmation_Template( $settings, $subscriber->get_subscription_email() );
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

			$this->sbe_subscribing_notice( __( 'Your email subscription has been successfully cancelled.', INCSUB_SBE_LANG_DOMAIN ) );
			
			$settings = incsub_sbe_get_settings();
			if ( $settings['get_notifications'] && is_email( $subscriber->subscription_email ) ) {
				require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
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
			$model = incsub_sbe_get_model();
			
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
		
		set_transient( 'sbe_sending', true, 600 );

		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		$settings = incsub_sbe_get_settings();
		$args = $settings;
		if ( ! empty( $posts_ids ) )
			$args['post_ids'] = $posts_ids;
		else
			$args['post_ids'] = array();

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/mail-template.php' );
		$mail_template = new Incsub_Subscribe_By_Email_Template( $args, false );

		if ( ! $log_id )
			$log_id = $model->add_new_mail_log( '' );


		$mail_template->send_mail( $log_id );

		delete_transient( 'sbe_sending' );
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

		if ( get_transient( 'incsub_sbe_updating' ) )
			return;

		if ( in_array( $post->post_type, $settings['post_types'] ) && $new_status != $old_status && 'publish' == $new_status && $settings['frequency'] == 'inmediately' ) {
			// Are we currently sending? Stop please
			if ( get_transient( 'sbe_sending' ) )
				return;

			$this->send_mails( array( $post->ID ) );	
		}
	}

	public function process_scheduled_subscriptions() {
		$settings = incsub_sbe_get_settings();

		if ( get_transient( 'incsub_sbe_updating' ) )
			return;

		if ( 'weekly' == $settings['frequency'] && $next_time = get_option( self::$freq_weekly_transient_slug ) ) {
			// Are we currently sending? Stop please
			if ( get_transient( 'sbe_sending' ) )
				return;

			if ( current_time( 'timestamp' ) > $next_time ) {
				self::set_next_week_schedule_time( $settings['day_of_week'], $settings['time'] );
				$this->send_mails();
			}
		}
		elseif ( 'daily' == $settings['frequency'] && $next_time = get_option( self::$freq_daily_transient_slug ) ) {	

			// Are we currently sending? Stop please
			if ( get_transient( 'sbe_sending' ) )
				return;
			
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
	public static function set_next_week_schedule_time( $day_of_week, $time ) {
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
		$next_time_date = date( 'Y-m-d', $next_time ) . ' ' . str_pad( $time, 2, 0, STR_PAD_LEFT ) . ':00:00';
		$next_time_timestamp = strtotime( $next_time_date );
		
		update_option( self::$freq_weekly_transient_slug, $next_time_timestamp );
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
			return date( get_option('date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s') , $time );
		}

		if ( 'weekly' == $settings['frequency'] && $time = get_option( self::$freq_weekly_transient_slug ) ) {
			return date( get_option('date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s') , $time );
		}

		
	}

	public function maybe_delete_logs() {
		if ( get_transient( 'incsub_sbe_updating' ) )
			return;

		if ( ! get_transient( 'incsub_sbe_check_logs' ) ) {
			$settings = incsub_sbe_get_settings();
			$model = incsub_sbe_get_model();

			$days_old = absint( $settings['keep_logs_for'] );
			$timestamp_old = current_time( 'timestamp' ) - ( $days_old * 24 * 60 * 60 );
			$logs_ids = $model->get_old_logs_ids( $timestamp_old );

			if ( ! empty( $logs_ids ) ) {
				$model->delete_log( $logs_ids );
			
				foreach ( $logs_ids as $log_id ) {
					Subscribe_By_Email_Logger::delete_log( $log_id );
				}
			}
			set_transient( 'incsub_sbe_check_logs', true, 86400 ); // We'll check every day
		}
	}



}

global $subscribe_by_email_plugin;
$subscribe_by_email_plugin = new Incsub_Subscribe_By_Email();

