<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: WPMU DEV
Version: 3.5.1
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

	public static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		self::$time_between_batches = apply_filters( 'sbe_time_between_batches', 1200 );

		$this->set_globals();
		$this->includes();

		$this->model = incsub_sbe_get_model();

		add_action( 'init', array( &$this, 'init_plugin' ), 1 );

		add_action( 'init', array( &$this, 'confirm_subscription' ), 2 );
		add_action( 'init', array( &$this, 'cancel_subscription' ), 20 );
		
		
		if ( ! get_transient( 'incsub_sbe_updating' ) ) {
			add_action( 'transition_post_status', array( &$this, 'process_instant_subscriptions' ), 2, 3);
			add_action( 'wp_loaded', array( &$this, 'process_scheduled_subscriptions' ) );
			add_action( 'init', array( &$this, 'maybe_delete_logs' ) );
		}
		

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'widgets_init', array( &$this, 'widget_init' ) );

		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

		add_action( 'wpmu_drop_tables', array( &$this, 'uninstall' ) );
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
			define( 'INCSUB_SBE_VERSION', '3.5.1' );
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
		require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/meta-boxes/do-not-send-meta-box.php' );

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

		// Log class
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

        $this->add_capabilities();

		update_option( 'incsub_sbe_version', INCSUB_SBE_VERSION );
		update_site_option( 'incsub_sbe_network_version', INCSUB_SBE_VERSION );

		$settings = incsub_sbe_get_settings();
		incsub_sbe_update_settings( $settings );
	}

	public function deactivate() {}

    public function add_capabilities() {
        $role = get_role( 'administrator' );
        $role->add_cap( 'manage_subscribe_by_email' );
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
			'edit_post'           => 'manage_subscribe_by_email',
			'read_post'           => 'manage_subscribe_by_email',
			'delete_post'         => 'manage_subscribe_by_email',
			'edit_posts'          => 'manage_subscribe_by_email',
			'edit_others_posts'   => 'manage_subscribe_by_email',
			'publish_posts'       => 'manage_subscribe_by_email',
			'read_private_posts'  => 'manage_subscribe_by_email',
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
		$new_tables = array( $model->subscriptions_log_table );
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

		if ( $current_network_version == INCSUB_SBE_VERSION )
			return;

		if ( $current_network_version === false ) {
			$this->activate();
			return;
		}


		if ( version_compare( $current_network_version, '2.9.1', '<' ) ) {
			$model = incsub_sbe_get_model();
			$model->create_network_squema();
		}

		if ( version_compare( $current_network_version, '3.0.2', '<' ) ) {
		    $model = incsub_sbe_get_model();
		    $model->create_network_squema();
		}

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
	public static function send_confirmation_mail( $subscription_id, $force = false ) {
		incsub_sbe_send_confirmation_email( $subscription_id, $force );
	}

	/**
	 * Loaded on front page, confirm a subscription linked from a user mail
	 */
	public function confirm_subscription() {
		if ( isset( $_GET['sbe_confirm'] ) ) {
			$subscriber = incsub_sbe_get_subscriber_by_key( $_GET['sbe_confirm'] );

			if ( ! $subscriber ) {
				$this->sbe_subscribing_notice( __( 'Sorry, your subscription no longer exists, please subscribe again.', INCSUB_SBE_LANG_DOMAIN ), __( 'Your subscription no longer exists', INCSUB_SBE_LANG_DOMAIN ) );
				wp_die();
			}

			if ( $subscriber->is_confirmed() ) {
				$redirect_to = remove_query_arg( 'sbe_confirm' );
				wp_redirect( $redirect_to );
				exit;
			}

			incsub_sbe_confirm_subscription( $subscriber->ID );

			$title = __( 'Thank you, your subscription has been confirmed.', INCSUB_SBE_LANG_DOMAIN );
			$this->sbe_subscribing_notice( $title, $title );

			$settings = incsub_sbe_get_settings();
			if ( $settings['get_notifications'] ) {
				require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/administrators-notices.php' );
				$admin_notice = new Incsub_Subscribe_By_Email_Administrators_Subscribed_Notice_Template( $subscriber->subscription_email );
				$admin_notice->send_email();
			}

			wp_die();

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

			$title =  __( 'Your email subscription has been successfully cancelled.', INCSUB_SBE_LANG_DOMAIN );
			$this->sbe_subscribing_notice( $title, $title );

			wp_die();
		}
	}


	public function sbe_subscribing_notice( $text, $title = false ) {
		nocache_headers();
        @header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

        if ( empty( $title ) )
        	$title = get_bloginfo( 'blogtitle' );

        ?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
	        <head>
	            <meta name="viewport" content="width=device-width" />
	            <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
	            <title><?php echo esc_html( $title ); ?></title>
	            <?php $this->subscribe_notices_styles(); ?>
	        </head>
			<body class="wp-core-ui">
				<div class="sbe-notice">
					<p> <?php echo esc_html( $text ); ?> </p>
					<a href="<?php echo get_home_url(); ?>" class="button-primary" title="<?php esc_attr_e( 'Go to site', INCSUB_SBE_LANG_DOMAIN ); ?>"><?php _e( 'Go to site', INCSUB_SBE_LANG_DOMAIN ); ?></a>
				</div>
			</body>
		</html>
		<?php
	}

	private function subscribe_notices_styles() {
		global $content_width;

		if ( empty( $content_width ) )
			$content_width = 900;

		$settings = incsub_sbe_get_settings();

		?>
			<style>
				.wp-core-ui {
					border-top:2px solid <?php echo $settings['header_color']; ?>;
					border-bottom:2px solid <?php echo $settings['header_color']; ?>;
				}
				.wp-core-ui .button-primary {
					background: #2ea2cc;
					border-color: #0074a2;
					-webkit-box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.5), 0 1px 0 rgba( 0, 0, 0, 0.15 );
					box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.5 ), 0 1px 0 rgba( 0, 0, 0, 0.15 );
					color: #fff;
					text-decoration: none;
					vertical-align: baseline;
					display: inline-block;
					font-size: 13px;
					line-height: 26px;
					height: 28px;
					margin: 0;
					padding: 0 10px 1px;
					cursor: pointer;
					border-width: 1px;
					border-style: solid;
					-webkit-appearance: none;
					-webkit-border-radius: 3px;
					border-radius: 3px;
					white-space: nowrap;
					-webkit-box-sizing: border-box;
					-moz-box-sizing: border-box;
					box-sizing: border-box;
				}
				.wp-core-ui .button-primary:hover {
					background: #1e8cbe;
					border-color: #0074a2;
					-webkit-box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.6 );
					box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.6 );
					color: #fff;
				}
				.wp-core-ui .button-primary:focus {
					-webkit-box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.6 ), 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, .8);
					box-shadow: inset 0 1px 0 rgba( 120, 200, 230, 0.6 ), 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, .8);
					background: #1e8cbe;
					border-color: #0074a2;
					color: #fff;
				}
				.wp-core-ui .button-primary:active {
					background: #1b7aa6;
					border-color: #005684;
					color: rgba( 255, 255, 255, 0.95 );
					-webkit-box-shadow: inset 0 1px 0 rgba( 0, 0, 0, 0.1 );
					box-shadow: inset 0 1px 0 rgba( 0, 0, 0, 0.1 );
					vertical-align: top;
					outline: none;
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

			// We first set the transient for at least 20min
			set_transient( self::$pending_mails_transient_slug, 'next', 1200 );

			do_action( 'sbe_before_send_pending_emails' );

			$settings = incsub_sbe_get_settings();

			$args = apply_filters( 'sbe_send_pending_emails_args', array( 
				'per_page' => $settings['mails_batch_size']
			) );

			$queue_items = incsub_sbe_get_queue_items( $args );

			$return = array();
			if ( ! empty( $queue_items['items'] ) ) {
				incsub_sbe_include_templates_files();
				$digest_sender = new SBE_Digest_Sender();
				$content_generator = new Incsub_Subscribe_By_Email_Content_Generator( $settings['frequency'], $settings['post_types'] );

				foreach ( $queue_items['items'] as $item ) {
					$subscriber = incsub_sbe_get_subscriber( $item->subscriber_email );

					// In order to avoid duplicated emails we'll set temporary this email as sent
					incsub_sbe_set_queue_item_sent_status( $item->id, 1 );
					
					incsub_sbe_increment_campaign_recipients( $item->campaign_id );
					$result = $digest_sender->send_digest( $item->get_subscriber_posts(), $subscriber );

					// Now we update the status
					incsub_sbe_set_queue_item_sent_status( $item->id, absint( $result ) );

					if ( $result === 4 ) {
						// There have been an error in PHPMailer?
						global $phpmailer;
						if ( ! empty( $phpmailer->ErrorInfo ) ) {
							incsub_sbe_set_queue_item_error_message( $item->id, $phpmailer->ErrorInfo );
						}
					}

					$return[ $item->subscriber_email ] = $result;

				}
			}

			do_action( 'sbe_after_send_pending_emails' );

			// Once we have finished we set the transient right
			set_transient( self::$pending_mails_transient_slug, 'next', self::$time_between_batches );

			return $return;

		}

		return false;
		
	}


	public function enqueue_emails( $posts_ids = array() ) {

		$args = array( 'posts_ids' => array() );

		$settings = incsub_sbe_get_settings();

		// Check if the post has been already sent
		foreach ( $posts_ids as $post_id ) {
			$is_sent = get_post_meta( $post_id, 'sbe_sent', true );
			$do_not_send = get_post_meta( $post_id, '_sbe_do_not_send', true );

			if ( ! $do_not_send && is_admin() && isset( $_POST['sbe-do-not-send'] ) )
				$do_not_send = true;
			if ( ! $is_sent && ! $do_not_send )
				$args['posts_ids'][] = $post_id;
		}

		// Check if the post type is valid
		$allowed_post_types = $settings['post_types'];
		foreach ( $args['posts_ids'] as $key => $post_id ) {
			if ( ! in_array( get_post_type( $post_id ), $allowed_post_types ) ) {
				unset( $args['posts_ids'][ $key ] );
			}
		}

		// Check if the taxonomy is valid
		foreach ( $args['posts_ids'] as $key => $post_id ) {
			$post_type = get_post_type( $post_id );

			if ( isset( $settings['taxonomies'][ $post_type ] ) ) {
				$allowed_post_type_taxonomies = array_keys( $settings['taxonomies'][ $post_type ] );
				$allowed_post_type_terms = array();
				foreach ( $allowed_post_type_taxonomies as $post_type_tax ) {
					$allowed_post_type_terms = array_merge( $allowed_post_type_terms, array_values( $settings['taxonomies'][ $post_type ][ $post_type_tax ] ) );
				}	
			}
			else {
				// The post type has no taxonomies, send it
				$allowed_post_type_terms = array( 'all' );
				$allowed_post_type_taxonomies = array();
			}
			

			if ( in_array( 'all', $allowed_post_type_terms ) ) {
				// All terms in this taxonomy are accepted
				continue;
			}

			$post_terms = wp_get_post_terms( $post_id, $allowed_post_type_taxonomies );
			if ( is_wp_error( $post_terms ) )
				$post_terms = array();

			$post_terms = wp_list_pluck( $post_terms, 'term_id' );

			$intersect = array_intersect( $post_terms, $allowed_post_type_terms );
			if ( empty( $intersect ) )
				unset( $args['posts_ids'][ $key ] );
		}

		$args = apply_filters( 'sbe_enqueue_emails_campaign_args', $args );

		if ( empty( $args['posts_ids'] ) )
			return;

		$campaign_id = incsub_sbe_insert_campaign( '', $args );

		$result = incsub_sbe_insert_queue_items( $campaign_id );
		if ( ! $result )
			incsub_sbe_delete_campaign( $campaign_id );			

		foreach ( $args['posts_ids'] as $post_id )
			update_post_meta( $post_id, 'sbe_sent', true );

		return $campaign_id;
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
			// Trigger the first batch
			delete_transient( self::$pending_mails_transient_slug );
		}
	}

	public function process_scheduled_subscriptions() {
		$settings = incsub_sbe_get_settings();

		if ( 'weekly' == $settings['frequency'] && $next_time = get_option( self::$freq_weekly_transient_slug ) ) {

			if ( current_time( 'timestamp' ) > $next_time ) {
				$model = incsub_sbe_get_model();
				self::set_next_week_schedule_time( $settings['day_of_week'], $settings['time'] );
				$days = self::get_last_x_days_sending_time( 7 );

				$args = array(
					'post_type' => $settings['post_types'],
					'after_date' => date( 'Y-m-d H:i:s', $days )
				);
				$posts_ids = incsub_sbe_get_digest_posts_ids( $args );

				$this->enqueue_emails( $posts_ids );
				// Trigger the first batch
				delete_transient( self::$pending_mails_transient_slug );

			}
		}
		elseif ( 'daily' == $settings['frequency'] && $next_time = get_option( self::$freq_daily_transient_slug ) ) {	
			
			if ( current_time( 'timestamp' ) > $next_time ) {
				$model = incsub_sbe_get_model();
				self::set_next_day_schedule_time( $settings['time'] );
				$days = self::get_last_x_days_sending_time( 1 );

				$args = array(
					'post_type' => $settings['post_types'],
					'after_date' => date( 'Y-m-d H:i:s', $days )
				);
				$posts_ids = incsub_sbe_get_digest_posts_ids( $args );

				$this->enqueue_emails( $posts_ids );
				
				// Trigger the first batch
				delete_transient( self::$pending_mails_transient_slug );

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

			$old_campaigns = incsub_sbe_get_campaigns_since( $timestamp_old );

			if ( ! empty( $old_campaigns ) ) {
				foreach ( $old_campaigns as $campaign )
					incsub_sbe_delete_campaign( $campaign->id );

			}
			set_transient( 'incsub_sbe_check_logs', true, 86400 ); // We'll check every day
		}
	}



}




if ( ! function_exists( 'subscribe_by_email' ) ) {
	function subscribe_by_email() {
		return Incsub_Subscribe_By_Email::get_instance();
	}

	global $subscribe_by_email_plugin;
	$subscribe_by_email_plugin = subscribe_by_email();
}

