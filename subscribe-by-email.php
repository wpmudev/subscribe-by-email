<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: WPMU DEV
Version: 2.9
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
	static $time_between_batches;

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
		self::$time_between_batches = apply_filters( 'sbe_time_between_batches', 1200 );
		
		$this->set_globals();
		$this->includes();

		add_action( 'init', array( &$this, 'init_plugin' ), 1 );

		add_action( 'init', array( &$this, 'confirm_subscription' ), 2 );
		add_action( 'init', array( &$this, 'cancel_subscription' ), 20 );
		
		
		if ( ! get_transient( 'incsub_sbe_updating' ) ) {
			add_action( 'transition_post_status', array( &$this, 'process_instant_subscriptions' ), 2, 3);
			add_action( 'init', array( &$this, 'process_scheduled_subscriptions' ), 2, 3);
			add_action( 'init', array( &$this, 'maybe_delete_logs' ) );
		}
		

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'widgets_init', array( &$this, 'widget_init' ) );

		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

		add_action( 'wpmu_drop_tables', array( &$this, 'uninstall' ) );

		add_action( 'admin_head', array( &$this, 'render_icon_styles' ) );

	}

	public function render_icon_styles() {
		?>
		<style type="text/css">
			@font-face {
				font-family: 'SBEFont';
				src:url('<?php echo INCSUB_SBE_ASSETS_URL; ?>fonts/sbe.eot?-6xkqvi');
				src:url('<?php echo INCSUB_SBE_ASSETS_URL; ?>fonts/sbe.eot?#iefix-6xkqvi') format('embedded-opentype'),
					url('<?php echo INCSUB_SBE_ASSETS_URL; ?>fonts/sbe.woff?-6xkqvi') format('woff'),
					url('<?php echo INCSUB_SBE_ASSETS_URL; ?>fonts/sbe.ttf?-6xkqvi') format('truetype'),
					url('<?php echo INCSUB_SBE_ASSETS_URL; ?>fonts/sbe.svg?-6xkqvi#icomoon') format('svg');
				font-weight: normal;
				font-style: normal;
			}

		    .sbe_icon:before {
		    	font-family: 'SBEFont' !important;
		    }


		    .sbe_status_confirmed:before {
		    	content:"\e604";
		    }

		   

		    .sbe_status_confirmed:before {
		    	content:"\e603";
		    	color:green;
		    }
		    .sbe_status_awaiting:before {
		    	content:"\e602";
		    	color:#D55252;
		    }

		</style>
		<?php
	}

	public function init_plugin() {
		$this->register_taxonomies();

		$this->maybe_upgrade_network();
		$this->maybe_upgrade();
		

		if ( ! get_transient( 'incsub_sbe_updating' ) )
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
		if ( ! defined( 'INCSUB_SBE_VERSION' ) )
			define( 'INCSUB_SBE_VERSION', '2.9' );
		if ( ! defined( 'INCSUB_SBE_PLUGIN_URL' ) )
			define( 'INCSUB_SBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		if ( ! defined( 'INCSUB_SBE_PLUGIN_DIR' ) )
			define( 'INCSUB_SBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		if ( ! defined( 'INCSUB_SBE_LOGS_DIR' ) )
			define( 'INCSUB_SBE_LOGS_DIR', WP_CONTENT_DIR . '/subscribe-by-email-logs' );

		if ( ! defined( 'INCSUB_SBE_LANG_DOMAIN' ) )
			define( 'INCSUB_SBE_LANG_DOMAIN', 'subscribe-by-email' );

		if ( ! defined( 'INCSUB_SBE_ASSETS_URL' ) )
			define( 'INCSUB_SBE_ASSETS_URL', INCSUB_SBE_PLUGIN_URL . 'assets/' );

		if ( ! defined( 'INCSUB_SBE_PLUGIN_FILE' ) )
			define( 'INCSUB_SBE_PLUGIN_FILE', plugin_basename( __FILE__ ) );

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
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/classes/class-subscriber.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/classes/class-campaign.php' );
		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/classes/class-queue-item.php' );


	}

	public function activate() {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$model->create_squema();
		$model->create_network_squema();
		$this->register_taxonomies();
		flush_rewrite_rules();
		update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
		update_site_option( 'incsub_sbe_network_version', INCSUB_SBE_VERSION );
	}

	public function deactivate() {
	}

	public function register_taxonomies() {
		// Register the Subscriber Post Type
		$labels = array(
			'name'                => _x( 'Subscribers', 'Post Type General Name', 'subscribe-by-email' ),
			'singular_name'       => _x( 'Subscriber', 'Post Type Singular Name', 'subscribe-by-email' ),
			'menu_name'           => __( 'Subscribers', 'subscribe-by-email' ),
			'parent_item_colon'   => __( 'Parent Subscriber:', 'subscribe-by-email' ),
			'all_items'           => __( 'All Subscribers', 'subscribe-by-email' ),
			'view_item'           => __( 'View Subscriber', 'subscribe-by-email' ),
			'add_new_item'        => __( 'Add New Subscriber', 'subscribe-by-email' ),
			'add_new'             => __( 'Add New', 'subscribe-by-email' ),
			'edit_item'           => __( 'Edit Subscriber', 'subscribe-by-email' ),
			'update_item'         => __( 'Update Subscriber', 'subscribe-by-email' ),
			'search_items'        => __( 'Search Subscriber', 'subscribe-by-email' ),
			'not_found'           => __( 'Not found', 'subscribe-by-email' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'subscribe-by-email' ),
		);
		$capabilities = array(
			'edit_post'           => 'manage_options',
			'read_post'           => 'manage_options',
			'delete_post'         => 'manage_options',
			'edit_posts'          => 'manage_options',
			'edit_others_posts'   => 'manage_options',
			'publish_posts'       => 'manage_options',
			'read_private_posts'  => 'manage_options',
		);
		$args = array(
			'label'               => __( 'Subscriber', 'subscribe-by-email' ),
			'description'         => __( 'Subscribers', 'subscribe-by-email' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'menu_position'       => 5,
			'menu_icon'           => '',
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capabilities'        => $capabilities,
		);
		register_post_type( 'subscriber', $args );

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

		if ( $current_version == INCSUB_SBE_VERSION )
			return;

		if ( $current_version === false ) {
			$this->activate();
			return;
		}

		set_transient( 'incsub_sbe_updating', true, 1800 );

		do_action( 'sbe_upgrade', $current_version, INCSUB_SBE_VERSION );

		include_once( INCSUB_SBE_PLUGIN_DIR . 'upgrade.php' );

		update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );

		delete_transient( 'incsub_sbe_updating' );
		
	}

	private function maybe_upgrade_network() {
		$current_network_version = get_site_option( 'incsub_sbe_network_version' );

		if ( $current_network_version === false ) {
			$this->activate();
			return;
		}

		if ( $current_network_version == INCSUB_SBE_VERSION )
			return;
		
		update_site_option( 'incsub_sbe_network_version', INCSUB_SBE_VERSION );
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

		$args = array(
			'note' => $note,
			'type' => $type,
			'meta' => $meta
		);

		$sid = incsub_sbe_insert_subscriber( $user_email, $autopt, $args );
		
		return $sid;		
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
		$confirmation_mail = new Incsub_Subscribe_By_Email_Confirmation_Template( $settings, $subscriber->subscription_email );
		$confirmation_mail->send_mail();
	}

	/**
	 * Loaded on front page, confirm a subscription linked from a user mail
	 */
	public function confirm_subscription() {
		if ( isset( $_GET['sbe_confirm'] ) ) {
			$subscriber = incsub_sbe_get_subscriber_by_key( $_GET['sbe_confirm'] );

			if ( ! $subscriber ) {
				$this->sbe_subscribing_notice( __( 'Sorry, your subscription no longer exists, please subscribe again.', INCSUB_SBE_LANG_DOMAIN ) );
				die();
			}

			incsub_sbe_confirm_subscription( $subscriber->ID );

			$this->sbe_subscribing_notice( __( 'Thank you, your subscription has been confirmed.', INCSUB_SBE_LANG_DOMAIN ) );
			die();

		}
	}

	/**
	 * Loaded on front page, cancel a subscription linked from a user mail
	 */
	public function cancel_subscription() {
		if ( isset( $_GET['sbe_unsubscribe'] ) ) {
			$subscriber = incsub_sbe_get_subscriber_by_key( $_GET['sbe_unsubscribe'] );
			if ( $subscriber ) {
				incsub_sbe_cancel_subscription( $subscriber->ID );

				$settings = incsub_sbe_get_settings();
				if ( $settings['get_notifications'] ) {
					require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
					$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Unsubscribed_Notice_Template( $subscriber->subscription_email );
					$admin_notice->send_email();
				}
			}

			$this->sbe_subscribing_notice( __( 'Your email subscription has been successfully cancelled.', INCSUB_SBE_LANG_DOMAIN ) );

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
			$pending_log = $model->get_remaining_batch_mail();

			if ( ! $pending_log )
				return false;
				
			$pending_log_id = absint( $pending_log->campaign_id );

			$settings = incsub_sbe_get_settings();
			$settings = $settings + $pending_log->campaign_settings;

			$args = array( 
				'campaign_id' => $pending_log_id, 
				'per_page' => $settings['mails_batch_size'] 
			);
			$queue_items = incsub_sbe_get_queue_items( $args );

			require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/mail-template.php' );
			$mail_template = new Incsub_Subscribe_By_Email_Template( $settings, false );

			$return = array();
			foreach ( $queue_items['items'] as $item ) {
				$subscriber = incsub_sbe_get_subscriber( $item->subscriber_email );

				// In order to avoid duplicated emails we'll set temporary this email as sent
				$model->set_queue_item_sent( $item->id, 1 );

				$result = $mail_template->send_mail( $subscriber, $item );

				// Now we update the status
				$model->set_queue_item_sent( $item->id, absint( $result ) );

				$return[ $item->subscriber_email ] = $result;

			}

			return $return;

		}

		return false;
		
	}


	public function enqueue_emails( $posts_ids = array() ) {
		$model = incsub_sbe_get_model();

		$args = array( 'posts_ids' => array() );

		$settings = incsub_sbe_get_settings();
		foreach ( $posts_ids as $post_id ) {
			$is_sent = get_post_meta( $post_id, 'sbe_sent', true );
			if ( ! $is_sent )
				$args['posts_ids'][] = $post_id;
		}



		if ( empty( $args['posts_ids'] ) )
			return;

		$log_id = $model->add_new_mail_log( '', $args );

		$emails_list = $model->get_log_emails_list( $log_id );

		if ( ! empty( $emails_list ) ) {
			$model->insert_queue_items( $emails_list, $log_id, $args );
		}
		else {
			$model->delete_log( $log_id );
			Subscribe_By_Email_Logger::delete_log( $log_id );
		}

		foreach ( $args['posts_ids'] as $post_id )
			update_post_meta( $post_id, 'sbe_sent', true );

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
			$this->enqueue_emails( array( $post->ID ) );	
		}
	}

	public function process_scheduled_subscriptions() {
		$settings = incsub_sbe_get_settings();

		if ( 'weekly' == $settings['frequency'] && $next_time = get_option( self::$freq_weekly_transient_slug ) ) {

			if ( current_time( 'timestamp' ) > $next_time ) {
				$model = incsub_sbe_get_model();
				self::set_next_week_schedule_time( $settings['day_of_week'], $settings['time'] );
				$days = self::get_last_x_days_sending_time( 7 );

				$today_sending_time = self::get_today_sending_time();
				$args = array(
					'post_type' => $settings['post_types'],
					'after_date' => date( 'Y-m-d H:i:s', $days )
				);
				$posts_ids = $model->get_posts_ids( $args );

				$this->enqueue_emails( $posts_ids );
			}
		}
		elseif ( 'daily' == $settings['frequency'] && $next_time = get_option( self::$freq_daily_transient_slug ) ) {	
			
			if ( current_time( 'timestamp' ) > $next_time ) {
				$model = incsub_sbe_get_model();
				self::set_next_day_schedule_time( $settings['time'] );
				$days = self::get_last_x_days_sending_time( 1 );

				$today_sending_time = self::get_today_sending_time();
				$args = array(
					'post_type' => $settings['post_types'],
					'after_date' => date( 'Y-m-d H:i:s', $days )
				);
				$posts_ids = $model->get_posts_ids( $args );

				$this->enqueue_emails( $posts_ids );

			}
		}

	}

	public static function get_last_x_days_sending_time( $days ) {
		$today_sending_time = self::get_today_sending_time();
		return strtotime( '-' . $days . ' days', $today_sending_time );
	}

	public static function get_today_sending_time() {
		$settings = incsub_sbe_get_settings();
		$time = str_pad( $settings['time'], 2, '0', STR_PAD_LEFT );
		$current_date = date( 'Y-m-d', current_time( 'timestamp' ) );
		$sending_time = $current_date . ' ' . $time . ':00:00';
		return strtotime( $sending_time );	
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

	public static function get_next_scheduled_batch_date() {
		$timeout = get_option( '_transient_timeout_' . self::$pending_mails_transient_slug );
		$date = date_i18n( 'Y-m-d H:i:s', $timeout );
		return get_date_from_gmt( $date, get_option('date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s') );
	}

	public function maybe_delete_logs() {

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
