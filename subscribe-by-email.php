<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: S H Mohanjith (Incsub)
Version: 1.1.0
Author URI: http://premium.wpmudev.org
WDP ID: 127
*/

/*
Copyright 2007-2010 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$subscribe_by_email_current_version = '1.1.0';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
global $subscribe_by_email_instant_notification_content;

$subscribe_by_email_instant_notification_content =
	get_option('subscribe_by_email_instant_notification_content',
"Dear Subscriber,

BLOGNAME has posted a new item: POST_TITLE

You can read the post in full here: POST_URL

EXCERPT

Thanks,
BLOGNAME

Cancel subscription: CANCEL_URL");
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('admin_head', 'subscribe_by_email_make_current');
add_action('admin_menu', 'subscribe_by_email_plug_pages');
add_action('wp_enqueue_scripts', 'subscribe_by_email_enqueue_js');
add_action('wp_head', 'subscribe_by_email_output_modalbox_css');
add_action('wp_head', 'subscribe_by_email_output_modalbox_js');
add_action('wp_head', 'subscribe_by_email_output_js',99);
add_action('widgets_init', 'subscribe_by_email_widget_init');
add_action('transition_post_status', 'subscribe_by_email_process_instant_subscriptions', $priority = 2, $accepted_args = 3);
add_action('add_user_to_blog', 'subscribe_by_email_add_user_to_blog', 3, 3);
add_action('admin_footer', 'subscribe_by_email_check_blog_users');
add_action('init', 'subscribe_by_email_init');
add_action('init', 'subscribe_by_email_cancel_process');
add_action('init', 'subscribe_by_email_internal_cancel_subscription');
add_action('init', 'subscribe_by_email_internal_create_subscription');
add_action('sbe_send_scheduled_notifications','subscribe_by_email_send_scheduled_notifications');

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function subscribe_by_email_init() {
	load_plugin_textdomain('subscribe-by-email', false, 'subscribe-by-email/languages');
}

function subscribe_by_email_make_current() {
	global $wpdb, $subscribe_by_email_current_version;
	if (get_option( "subscribe_by_email_version" ) == '') {
		add_option( 'subscribe_by_email_version', '0.0.0' );
	}

	if (get_option( "subscribe_by_email_version" ) == $subscribe_by_email_current_version) {
		// do nothing
	} else {
		//update to current version
		update_option( "subscribe_by_email_version", $subscribe_by_email_current_version );
		subscribe_by_email_blog_install();
		subscribe_by_email_s2_import();
	}
}

function subscribe_by_email_blog_install() {
	global $wpdb, $subscribe_by_email_current_version;
	if (get_option( "subscribe_by_email_installed" ) == '') {
		add_option( 'subscribe_by_email_installed', 'no' );
	}

	if (get_option( "subscribe_by_email_installed" ) == "yes") {
		// do nothing
	} else {

		$subscribe_by_email_table1 = "CREATE TABLE `" . $wpdb->prefix . "subscriptions` (
  `subscription_ID` bigint(20) unsigned NOT NULL auto_increment,
  `subscription_email` TEXT NOT NULL,
  `subscription_type` varchar(200) NOT NULL,
  `subscription_created` bigint(20) NOT NULL,
  `subscription_note` varchar(200) NOT NULL,
  PRIMARY KEY  (`subscription_ID`)
) ENGINE=MyISAM;";

		$wpdb->query( $subscribe_by_email_table1 );
		update_option( "subscribe_by_email_installed", "yes" );
	}
}

function subscribe_by_email_plug_pages() {
	add_menu_page(__('Subscriptions', 'subscribe-by-email'), __('Subscriptions', 'subscribe-by-email'), 'manage_options', 'subscription', 'subscribe_by_email_manage_output');
	add_submenu_page('subscription', __('Settings', 'subscribe-by-email'), __('Settings', 'subscribe-by-email'), 'manage_options', 'subscription_settings', 'subscribe_by_email_settings_output' );
}

function subscribe_by_email_s2_import() {
	global $wpdb;
	$count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "subscribe2 WHERE active = '1'");
	if ( $count > 0 ) {
		$query = "SELECT email FROM " . $wpdb->prefix . "subscribe2 WHERE active = '1'";
		$emails = $wpdb->get_results( $query, ARRAY_A );
		if ( count( $emails ) > 0 ) {
			foreach ($emails as $email) {
				subscribe_by_email_create_subscription($email['email'],"S2 Import Subscription");
			}
		}
	}

}

function subscribe_by_email_form() {
	$content = '';
	$content .= '<form method="post" id="subscribe-by-email-form">';
	$content .= '<div id="subscribe-by-email-msg"></div>';
        $content .= '<input id="subscription_email" name="subscription_email" style="width:97%;" maxlength="50" value="ex: john@hotmail.com" onfocus="this.value=\'\';" type="text">';
	$content .= '<center>';
	$content .= '<input type="button" class="button" name="create_subscription" value="'.__('Create Subscription', 'subscribe-by-email').'" style="width:99%;" onclick="SubscribeByEmailCreate();" />';
	$content .= '</center>';
	$content .= '<input type="hidden" name="action" value="external-create-subscription">';
	$content .= '</form>';

	return $content;
}

class subscribe_by_email extends WP_Widget {

	/**
	 * Widget setup.
	 */
	function subscribe_by_email() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'subscribe-by-email', 'description' => __('This widget allows visitors to subscribe to receive email updates when a new post is made to your blo', 'subscribe-by-email') );

		/* Widget control settings. */
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'subscribe-by-email' );

		/* Create the widget. */
		$this->WP_Widget( 'subscribe-by-email', __('Subscribe by Email', 'subscribe-by-email'), $widget_ops, $control_ops );
	}

	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters('widget_title', $instance['title'] );
		$text = stripslashes($instance['text']);

		echo $before_widget;
			echo $before_title . $title . $after_title;
            ?>
            <ul>
                <?php echo subscribe_by_email_form(); ?>
                <br />
                <p style="padding-top:10px;">
                <?php echo $text; ?>
                </p>
			</ul>
            <?php
		echo $after_widget;
	}

	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['text'] = strip_tags( $new_instance['text'] );

		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('Subscribe by Email', 'subscribe-by-email'), 'text' => __('Completely spam free, opt out any time.', 'subscribe-by-email'));
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'hybrid', 'subscribe-by-email'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:75%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'text' ); ?>"><?php _e('Text:', 'example', 'subscribe-by-email'); ?></label>
			<input id="<?php echo $this->get_field_id( 'text' ); ?>" name="<?php echo $this->get_field_name( 'text' ); ?>" value="<?php echo $instance['text']; ?>" style="width:75%;" />
		</p>
	<?php
	}
}

function subscribe_by_email_widget_init() {
	global $wpdb;
	register_widget( 'subscribe_by_email' );
}

function subscribe_by_email_enqueue_js() {
	wp_enqueue_script('scriptaculous');
	wp_enqueue_script('prototype');
}

function subscribe_by_email_create_subscription($email,$note) {
	global $wpdb;

	$count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "subscriptions WHERE subscription_email = '" . $email . "'");

	if ( $count < 1 ) {
		$wpdb->query( "INSERT INTO " . $wpdb->prefix . "subscriptions (subscription_email, subscription_note, subscription_created, subscription_type) VALUES ( '" . $email . "', '" . $note . "', '" . time() . "', 'instant')" );
		return true;
	}
	return false;
}

function subscribe_by_email_cancel_subscription($id) {
	global $wpdb;
	
	return $wpdb->query( "DELETE FROM " . $wpdb->prefix . "subscriptions WHERE subscription_ID = '" . $id . "'" );
}

function subscribe_by_email_cancel_subscription_by_email($email) {
	global $wpdb;
	
	return $wpdb->query( "DELETE FROM " . $wpdb->prefix . "subscriptions WHERE subscription_email = '" . $email . "'" );
}

function subscribe_by_email_cancel_process() {
	wp_enqueue_script('jquery');
	if ( isset($_GET['action']) && $_GET['action'] == 'cancel-subscription' ) {
	subscribe_by_email_cancel_subscription($_GET['sid']);
	?>
	<script type='text/javascript'>
	jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('Your subscription has been successfully canceled', 'subscribe-by-email'); ?>!</strong></ceneter></p></div>');
	</script>
	<?php
	}
}

function subscribe_by_email_internal_create_subscription() {
	if ( isset($_POST['action']) && $_POST['action'] == 'internal-create-subscription' ) {
		if (!subscribe_by_email_create_subscription(str_replace("PLUS", "+", $_POST['email']),"Visitor Subscription")) {
			header("HTTP/1.1 405 Method Not Allowed");
			echo "Not allowed";
			exit();
		}
	} else if ( isset($_POST['action']) && $_POST['action'] == 'external-create-subscription') {
		return subscribe_by_email_create_subscription($_POST['subscription_email'],"Visitor Subscription");
	}
}

function subscribe_by_email_internal_cancel_subscription() {
	if ( isset($_POST['action']) && $_POST['action'] == 'internal-cancel-subscription' ) {
		subscribe_by_email_cancel_subscription_by_email(str_replace("PLUS", "+", $_POST['email']));
	}
}

function subscribe_by_email_process_instant_subscriptions($new_status, $old_status = '', $post) {
	if ($post->post_type == 'post'){
		if ($new_status != $old_status){
			if ($new_status == 'publish'){
				//send emails
				subscribe_by_email_send_instant_notifications($post);
			}
		}
	}
}

function subscribe_by_email_send_scheduled_notifications($post_id) {
	$post = get_post($post_id);
	subscribe_by_email_send_instant_notifications($post);
}

function subscribe_by_email_send_instant_notifications($post) {
	global $wpdb, $subscribe_by_email_instant_notification_content;

	$subscribe_by_email_excerpts = get_option('subscribe_by_email_excerpts', 'no');

	$cancel_url = get_option('siteurl') . '?action=cancel-subscription&sid=';
	$admin_email = get_option('admin_email');
	$post_id = $post->ID;
	$post_title = $post->post_title;
	$post_content = $post->post_content;
	$post_url = get_permalink($post_id);
	//cleanup title
	$post_title = strip_tags($post_title);
	//cleanup content
	$post_content = strip_tags($post_content);
	//get excerpt
	if ($post->post_excerpt && !empty($post->post_excerpt)) {
		$post_excerpt = $post->post_excerpt;
	} else {
		$post_excerpt = $post_content;
		if (strlen($post_excerpt) > 255) {
			$post_excerpt = substr($post_excerpt,0,252) . '...';
		}
	}
	
	//get blog name
	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text arena of emails.
	$blog_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
	
	//format notification text
	$subscribe_by_email_instant_notification_content = str_replace("BLOGNAME",$blog_name,$subscribe_by_email_instant_notification_content);
	$subscribe_by_email_instant_notification_content = str_replace("POST_TITLE",$post_title,$subscribe_by_email_instant_notification_content);
	if ( $subscribe_by_email_excerpts == 'yes' ) {
		$subscribe_by_email_instant_notification_content = str_replace("EXCERPT",$post_excerpt,$subscribe_by_email_instant_notification_content);
	} else {
		$subscribe_by_email_instant_notification_content = str_replace("EXCERPT","",$subscribe_by_email_instant_notification_content);
	}
	$subscribe_by_email_instant_notification_content = str_replace("POST_URL",$post_url,$subscribe_by_email_instant_notification_content);
	$subscribe_by_email_instant_notification_content = str_replace("\'","'",$subscribe_by_email_instant_notification_content);

	$query = "SELECT * FROM " . $wpdb->prefix . "subscriptions WHERE subscription_type = 'instant'";
	$subscription_emails = $wpdb->get_results( $query, ARRAY_A );

	if (count($subscription_emails) > 0 && get_post_meta($post->ID, 'sbe_notified', true) != 'yes') {
		wp_schedule_single_event(time()+120, 'sbe_send_scheduled_notifications', array($post->ID));
		
		$i = 0;
		foreach ($subscription_emails as $subscription_email){
			$done_count = intval(get_post_meta($post->ID, 'sbe_notified', true));
			if ($done_count > $i) {
				$i++;
				continue;
			}
			$i++;
			//=========================================================//
			$loop_notification_content = $subscribe_by_email_instant_notification_content;
			//format notification text
			$loop_notification_content = str_replace("CANCEL_URL",$cancel_url . $subscription_email['subscription_ID'],$loop_notification_content);
			$subject_content = $blog_name . ': ' . __('New Post', 'subscribe-by-email');
			$from_email = $admin_email;
			$message_headers = "MIME-Version: 1.0\n" . "From: " . $blog_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
			wp_mail($subscription_email['subscription_email'], $subject_content, $loop_notification_content, $message_headers);
			update_post_meta($post->ID, 'sbe_notified', $i);
			//=========================================================//
		}
		update_post_meta($post->ID, 'sbe_notified', 'yes');
	}
}

function subscribe_by_email_add_user_to_blog($user_id, $role, $blog_id) {
	global $wpdb;
	switch_to_blog($blog_id);
	if ( get_option('subscribe_by_email_auto_subscribe', 'no') != 'no' && get_option( "subscribe_by_email_installed" ) == 'yes' ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "subscriptions ");
		if ( is_numeric($count) ) {
			if ( $count > 0 ) {
				$email = $wpdb->get_var( "SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $user_id . "'");
				$email_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "subscriptions WHERE subscription_email = '" . $email . "'");
				if ( $email_count < 1 ) {
					$wpdb->query( "INSERT INTO " . $wpdb->prefix . "subscriptions (subscription_email, subscription_note, subscription_created, subscription_type) VALUES ( '" . $email . "', 'Auto Subscription', '" . time() . "', 'instant')" );
				}
			}
		}
	}
	restore_current_blog();
}

function subscribe_by_email_check_blog_users() {
	global $wpdb;
	if ( get_option('subscribe_by_email_auto_subscribe', 'no') != 'no' && get_option( "subscribe_by_email_installed" ) == 'yes' ) {
		$query = "SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->prefix . "capabilities'";
		$users = $wpdb->get_results( $query, ARRAY_A );

		if ( count( $users ) > 0 ) {
			foreach ( $users as $user ) {
				$email = $wpdb->get_var( "SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $user['user_id'] . "'");
				subscribe_by_email_create_subscription($email,"Auto Subscription");
			}
		}
	}
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function subscribe_by_email_output_js() {
	?>
    <script type='text/javascript'>
	function SubscribeByEmailCreate() {
		var SubscriptionEmail = document.getElementById('subscription_email').value;
		var http = new XMLHttpRequest();
		if ( SubscriptionEmail != '' && SubscriptionEmail != 'ex: john@hotmail.com' ) {
			var url = "<?php echo get_option('siteurl'); ?>/";
			var params = "action=internal-create-subscription&email=" + SubscriptionEmail.replace("+","PLUS");
			http.open("POST", url, true);

			http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			http.setRequestHeader("Content-length", params.length);
			http.setRequestHeader("Connection", "close");
			http.onreadystatechange = function() {
				if(http.readyState == 4) {
					if (http.status == 200) {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('Your subscription has been successfully created', 'subscribe-by-email'); ?>!</strong></ceneter></p></div>');
						document.getElementById('subscription_email').value = '';
					} else {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('You are already subscribed', 'subscribe-by-email'); ?>!</strong></ceneter></p></div>');
					}
				}
			}
			http.send(params);
		}
	}
	function SubscribeByEmailCancel() {
		var SubscriptionEmail = document.getElementById('subscription_email').value;
		var http = new XMLHttpRequest();
		if ( SubscriptionEmail != '' && SubscriptionEmail != 'ex: john@hotmail.com' ) {
			var url = "<?php echo get_option('siteurl'); ?>";
			var params = "action=internal-cancel-subscription&email=" + SubscriptionEmail.replace("+","PLUS");
			http.open("POST", url, true);

			http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			http.setRequestHeader("Content-length", params.length);
			http.setRequestHeader("Connection", "close");

			http.onreadystatechange = function() {
				if(http.readyState == 4) {
					if (http.status == 200) {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('Your subscription has been successfully canceled', 'subscribe-by-email'); ?>!</strong></ceneter></p></div>');
						document.getElementById('subscription_email').value = '';
					} else {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('Failed to cancel your subscription', 'subscribe-by-email'); ?>!</strong></ceneter></p></div>');
					}
				}
			}
			http.send(params);
		}
	}
	jQuery(document).ready(function() {
		jQuery("#subscribe-by-email-form").submit(function () {
			SubscribeByEmailCreate();
			return false;
		});
	});
	</script>
    <?php
}

function subscribe_by_email_output_modalbox_js() {
	?>
    <script type='text/javascript'>
	if (!window.Modalbox)
		var Modalbox = new Object();

	Modalbox.Methods = {
		overrideAlert: false, // Override standard browser alert message with ModalBox
		focusableElements: new Array,
		options: {
			title: "ModalBox Window", // Title of the ModalBox window
			overlayClose: true, // Close modal box by clicking on overlay
			width: 500, // Default width in px
			height: 90, // Default height in px
			overlayOpacity: .75, // Default overlay opacity
			overlayDuration: .25, // Default overlay fade in/out duration in seconds
			slideDownDuration: .5, // Default Modalbox appear slide down effect in seconds
			slideUpDuration: .15, // Default Modalbox hiding slide up effect in seconds
			resizeDuration: .2, // Default resize duration seconds
			inactiveFade: true, // Fades MB window on inactive state
			transitions: true, // Toggles transition effects. Transitions are enabled by default
			loadingString: "Please wait. Loading...", // Default loading string message
			closeString: "Close window", // Default title attribute for close window link
			params: {},
			method: 'get' // Default Ajax request method
		},
		_options: new Object,

		setOptions: function(options) {
			Object.extend(this.options, options || {});
		},

		_init: function(options) {
			// Setting up original options with default options
			Object.extend(this._options, this.options);
			this.setOptions(options);
			//Create the overlay
			this.MBoverlay = Builder.node("div", { id: "MB_overlay", opacity: "0" });
			//Create the window
			this.MBwindow = Builder.node("div", {id: "MB_window", style: "display: none"}, [
				this.MBframe = Builder.node("div", {id: "MB_frame"}, [
					this.MBheader = Builder.node("div", {id: "MB_header"}, [
						this.MBcaption = Builder.node("div", {id: "MB_caption"}),
						this.MBclose = Builder.node("a", {id: "MB_close", title: this.options.closeString, href: "#"}, [
							Builder.build("<span>&times;</span>"),
						]),
					]),
					this.MBcontent = Builder.node("div", {id: "MB_content"}, [
						this.MBloading = Builder.node("div", {id: "MB_loading"}, this.options.loadingString),
					]),
				]),
			]);
			// Inserting into DOM
			document.body.insertBefore(this.MBwindow, document.body.childNodes[0]);
			document.body.insertBefore(this.MBoverlay, document.body.childNodes[0]);

			// Initial scrolling position of the window. To be used for remove scrolling effect during ModalBox appearing
			this.initScrollX = window.pageXOffset || document.body.scrollLeft || document.documentElement.scrollLeft;
			this.initScrollY = window.pageYOffset || document.body.scrollTop || document.documentElement.scrollTop;

			//Adding event observers
			this.hide = this.hide.bindAsEventListener(this);
			this.close = this._hide.bindAsEventListener(this);
			this.kbdHandler = this.kbdHandler.bindAsEventListener(this);
			this._initObservers();

			this.initialized = true; // Mark as initialized
			this.active = true; // Mark as active
			this.currFocused = 0;
		},

		show: function(content, options) {
			if(!this.initialized) this._init(options); // Check for is already initialized

			this.content = content;
			this.setOptions(options);

			Element.update(this.MBcaption, this.options.title); // Updating title of the MB

			if(this.MBwindow.style.display == "none") { // First modal box appearing
				this._appear();
				this.event("onShow"); // Passing onShow callback
			}
			else { // If MB already on the screen, update it
				this._update();
				this.event("onUpdate"); // Passing onUpdate callback
			}
		},

		hide: function(options) { // External hide method to use from external HTML and JS
			if(this.initialized) {
				if(options) Object.extend(this.options, options); // Passing callbacks
				if(this.options.transitions)
					Effect.SlideUp(this.MBwindow, { duration: this.options.slideUpDuration, afterFinish: this._deinit.bind(this) } );
				else {
					Element.hide(this.MBwindow);
					this._deinit();
				}
			} else throw("Modalbox isn't initialized");
		},

		alert: function(message){
			var html = '<div class="MB_alert"><p>' + message + '</p><input type="button" onclick="jQuery(\'#subscribe-by-email-msg\').html();" value="OK" /></div>';
			jQuery('#subscribe-by-email-msg').html(html);
		},

		_hide: function(event) { // Internal hide method to use inside MB class
			if(event) Event.stop(event);
			this.hide();
		},

		_appear: function() { // First appearing of MB
			if (navigator.appVersion.match(/\bMSIE\b/))
				this._toggleSelects();
			this._setOverlay();
			this._setWidth();
			this._setPosition();
			if(this.options.transitions) {
				Element.setStyle(this.MBoverlay, {opacity: 0});
				new Effect.Fade(this.MBoverlay, {
						from: 0,
						to: this.options.overlayOpacity,
						duration: this.options.overlayDuration,
						afterFinish: function() {
							new Effect.SlideDown(this.MBwindow, {
								duration: this.options.slideDownDuration,
								afterFinish: function(){
									this._setPosition();
									this.loadContent();
								}.bind(this)
							});
						}.bind(this)
				});
			} else {
				Element.setStyle(this.MBoverlay, {opacity: this.options.overlayOpacity});
				Element.show(this.MBwindow);
				this._setPosition();
				this.loadContent();
			}
			this._setWidthAndPosition = this._setWidthAndPosition.bindAsEventListener(this);
			Event.observe(window, "resize", this._setWidthAndPosition);
		},

		resize: function(byWidth, byHeight, options) { // Change size of MB without loading content
			var wHeight = Element.getHeight(this.MBwindow);
			var wWidth = Element.getWidth(this.MBwindow);
			var hHeight = Element.getHeight(this.MBheader);
			var cHeight = Element.getHeight(this.MBcontent);
			var newHeight = ((wHeight - hHeight + byHeight) < cHeight) ? (cHeight + hHeight - wHeight) : byHeight;
			this.setOptions(options); // Passing callbacks
			if(this.options.transitions) {
				new Effect.ScaleBy(this.MBwindow, byWidth, newHeight, {
						duration: this.options.resizeDuration,
						afterFinish: function() {
							this.event("_afterResize"); // Passing internal callback
							this.event("afterResize"); // Passing callback
						}.bind(this)
					});
			} else {
				this.MBwindow.setStyle({width: wWidth + byWidth + "px", height: wHeight + newHeight + "px"});
				setTimeout(function() {
					this.event("_afterResize"); // Passing internal callback
					this.event("afterResize"); // Passing callback
				}.bind(this), 1);

			}

		},

		_update: function() { // Updating MB in case of wizards
			Element.update(this.MBcontent, "");
			this.MBcontent.appendChild(this.MBloading);
			Element.update(this.MBloading, this.options.loadingString);
			this.currentDims = [this.MBwindow.offsetWidth, this.MBwindow.offsetHeight];
			Modalbox.resize((this.options.width - this.currentDims[0]), (this.options.height - this.currentDims[1]), {_afterResize: this._loadAfterResize.bind(this) });
		},

		loadContent: function () {
			if(this.event("beforeLoad") != false) { // If callback passed false, skip loading of the content
				if(typeof this.content == 'string') {

					var htmlRegExp = new RegExp(/<\/?[^>]+>/gi);
					if(htmlRegExp.test(this.content)) { // Plain HTML given as a parameter
						this._insertContent(this.content);
						this._putContent();
					} else
						new Ajax.Request( this.content, { method: this.options.method.toLowerCase(), parameters: this.options.params,
							onComplete: function(transport) {
								var response = new String(transport.responseText);
								this._insertContent(transport.responseText.stripScripts());
								response.extractScripts().map(function(script) {
									return eval(script.replace("<!--", "").replace("// -->", ""));
								}.bind(window));
								this._putContent();
							}.bind(this)
						});

				} else if (typeof this.content == 'object') {// HTML Object is given
					this._insertContent(this.content);
					this._putContent();
				} else {
					Modalbox.hide();
					throw('Please specify correct URL or HTML element (plain HTML or object)');
				}
			}
		},

		_insertContent: function(content){
			Element.extend(this.MBcontent);
			this.MBcontent.update("");
			if(typeof content == 'string')
				this.MBcontent.hide().update(content);
			else if (typeof this.content == 'object') { // HTML Object is given
				var _htmlObj = content.cloneNode(true); // If node already a part of DOM we'll clone it
				// If clonable element has ID attribute defined, modifying it to prevent duplicates
				if(this.content.id) this.content.id = "MB_" + this.content.id;
				/* Add prefix for IDs on all elements inside the DOM node */
				this.content.getElementsBySelector('*[id]').each(function(el){ el.id = "MB_" + el.id });
				this.MBcontent.hide().appendChild(_htmlObj);
				this.MBcontent.down().show(); // Toggle visibility for hidden nodes
			}
		},

		_putContent: function(){
			// Prepare and resize modal box for content
			if(this.options.height == this._options.height)
				Modalbox.resize(0, this.MBcontent.getHeight() - Element.getHeight(this.MBwindow) + Element.getHeight(this.MBheader), {
					afterResize: function(){
						this.MBcontent.show();
						this.focusableElements = this._findFocusableElements();
						this._setFocus(); // Setting focus on first 'focusable' element in content (input, select, textarea, link or button)
						this.event("afterLoad"); // Passing callback
					}.bind(this)
				});
			else { // Height is defined. Creating a scrollable window
				this._setWidth();
				this.MBcontent.setStyle({overflow: 'auto', height: Element.getHeight(this.MBwindow) - Element.getHeight(this.MBheader) - 13 + 'px'});
				this.MBcontent.show();
				this.focusableElements = this._findFocusableElements();
				this._setFocus(); // Setting focus on first 'focusable' element in content (input, select, textarea, link or button)
				this.event("afterLoad"); // Passing callback
			}
		},

		activate: function(options){
			this.setOptions(options);
			this.active = true;
			Event.observe(this.MBclose, "click", this.close);
			if(this.options.overlayClose) Event.observe(this.MBoverlay, "click", this.hide);
			Element.show(this.MBclose);
			if(this.options.transitions && this.options.inactiveFade) new Effect.Appear(this.MBwindow, {duration: this.options.slideUpDuration});
		},

		deactivate: function(options) {
			this.setOptions(options);
			this.active = false;
			Event.stopObserving(this.MBclose, "click", this.close);
			if(this.options.overlayClose) Event.stopObserving(this.MBoverlay, "click", this.hide);
			Element.hide(this.MBclose);
			if(this.options.transitions && this.options.inactiveFade) new Effect.Fade(this.MBwindow, {duration: this.options.slideUpDuration, to: .75});
		},

		_initObservers: function(){
			Event.observe(this.MBclose, "click", this.close);
			if(this.options.overlayClose) Event.observe(this.MBoverlay, "click", this.hide);
			Event.observe(document, "keypress", Modalbox.kbdHandler );
		},

		_removeObservers: function(){
			Event.stopObserving(this.MBclose, "click", this.close);
			if(this.options.overlayClose) Event.stopObserving(this.MBoverlay, "click", this.hide);
			Event.stopObserving(document, "keypress", Modalbox.kbdHandler );
		},

		_loadAfterResize: function() {
			this._setWidth();
			this._setPosition();
			this.loadContent();
		},

		_setFocus: function() { // Setting focus to be looped inside current MB
			if(this.focusableElements.length > 0) {
				var i = 0;
				var firstEl = this.focusableElements.find(function findFirst(el){
					i++;
					return el.tabIndex == 1;
				}) || this.focusableElements.first();
				this.currFocused = (i == this.focusableElements.length - 1) ? (i-1) : 0;
				firstEl.focus(); // Focus on first focusable element except close button
			} else
				$("MB_close").focus(); // If no focusable elements exist focus on close button
		},

		_findFocusableElements: function(){ // Collect form elements or links from MB content
			var els = this.MBcontent.getElementsBySelector('input:not([type~=hidden]), select, textarea, button, a[href]');
			els.invoke('addClassName', 'MB_focusable');
			return this.MBcontent.getElementsByClassName('MB_focusable');
		},

		kbdHandler: function(e) {
			var node = Event.element(e);
			switch(e.keyCode) {
				case Event.KEY_TAB:
					Event.stop(e);
					if(!e.shiftKey) { //Focusing in direct order
						if(this.currFocused == this.focusableElements.length - 1) {
							this.focusableElements.first().focus();
							this.currFocused = 0;
						} else {
							this.currFocused++;
							this.focusableElements[this.currFocused].focus();
						}
					} else { // Shift key is pressed. Focusing in reverse order
						if(this.currFocused == 0) {
							this.focusableElements.last().focus();
							this.currFocused = this.focusableElements.length - 1;
						} else {
							this.currFocused--;
							this.focusableElements[this.currFocused].focus();
						}
					}
					break;
				case Event.KEY_ESC:
					if(this.active) this._hide(e);
					break;
				case 32:
					this._preventScroll(e);
					break;
				case 0: // For Gecko browsers compatibility
					if(e.which == 32) this._preventScroll(e);
					break;
				case Event.KEY_UP:
				case Event.KEY_DOWN:
				case Event.KEY_PAGEDOWN:
				case Event.KEY_PAGEUP:
				case Event.KEY_HOME:
				case Event.KEY_END:
					// Safari operates in slightly different way. This realization is still buggy in Safari.
					if(/Safari|KHTML/.test(navigator.userAgent) && !["textarea", "select"].include(node.tagName.toLowerCase()))
						Event.stop(e);
					else if( (node.tagName.toLowerCase() == "input" && ["submit", "button"].include(node.type)) || (node.tagName.toLowerCase() == "a") )
						Event.stop(e);
					break;
			}
		},

		_preventScroll: function(event) { // Disabling scrolling by "space" key
			if(!["input", "textarea", "select", "button"].include(Event.element(event).tagName.toLowerCase()))
				Event.stop(event);
		},

		_deinit: function()
		{
			this._removeObservers();
			Event.stopObserving(window, "resize", this._setWidthAndPosition );
			if(this.options.transitions) {
				Effect.toggle(this.MBoverlay, 'appear', {duration: this.options.overlayDuration, afterFinish: this._removeElements.bind(this) });
			} else {
				this.MBoverlay.hide();
				this._removeElements();
			}
			Element.setStyle(this.MBcontent, {overflow: '', height: ''});
		},

		_removeElements: function () {
			if (navigator.appVersion.match(/\bMSIE\b/)) {
				this._prepareIE("", ""); // If set to auto MSIE will show horizontal scrolling
				window.scrollTo(this.initScrollX, this.initScrollY);
			}
			Element.remove(this.MBoverlay);
			Element.remove(this.MBwindow);

			/* Replacing prefixes 'MB_' in IDs for the original content */
			if(typeof this.content == 'object' && this.content.id && this.content.id.match(/MB_/)) {
				this.content.getElementsBySelector('*[id]').each(function(el){ el.id = el.id.replace(/MB_/, ""); });
				this.content.id = this.content.id.replace(/MB_/, "");
			}
			/* Initialized will be set to false */
			this.initialized = false;

			if (navigator.appVersion.match(/\bMSIE\b/))
				this._toggleSelects(); // Toggle back 'select' elements in IE
			this.event("afterHide"); // Passing afterHide callback
			this.setOptions(this._options); //Settings options object into intial state
		},

		_setOverlay: function () {
			if (navigator.appVersion.match(/\bMSIE\b/)) {
				this._prepareIE("100%", "hidden");
				if (!navigator.appVersion.match(/\b7.0\b/)) window.scrollTo(0,0); // Disable scrolling on top for IE7
			}
		},

		_setWidth: function () { //Set size
			Element.setStyle(this.MBwindow, {width: this.options.width + "px", height: this.options.height + "px"});
		},

		_setPosition: function () {
			Element.setStyle(this.MBwindow, {left: Math.round((Element.getWidth(document.body) - Element.getWidth(this.MBwindow)) / 2 ) + "px"});
		},

		_setWidthAndPosition: function () {
			Element.setStyle(this.MBwindow, {width: this.options.width + "px"});
			this._setPosition();
		},

		_getScrollTop: function () { //From: http://www.quirksmode.org/js/doctypes.html
			var theTop;
			if (document.documentElement && document.documentElement.scrollTop)
				theTop = document.documentElement.scrollTop;
			else if (document.body)
				theTop = document.body.scrollTop;
			return theTop;
		},
		// For IE browsers -- IE requires height to 100% and overflow hidden (taken from lightbox)
		_prepareIE: function(height, overflow){
			var body = document.getElementsByTagName('body')[0];
			body.style.height = height;
			body.style.overflow = overflow;

			var html = document.getElementsByTagName('html')[0];
			html.style.height = height;
			html.style.overflow = overflow;
		},
		// For IE browsers -- hiding all SELECT elements
		_toggleSelects: function() {
			var selects = $$("select");
			if(this.initialized) {
				selects.invoke('setStyle', {'visibility': 'hidden'});
			} else {
				selects.invoke('setStyle', {'visibility': ''});
			}

		},
		event: function(eventName) {
			if(this.options[eventName]) {
				var returnValue = this.options[eventName](); // Executing callback
				this.options[eventName] = null; // Removing callback after execution
				if(returnValue != undefined)
					return returnValue;
				else
					return true;
			}
			return true;
		}
	}

	Object.extend(Modalbox, Modalbox.Methods);

	if(Modalbox.overrideAlert) window.alert = Modalbox.alert;

	Effect.ScaleBy = Class.create();
	Object.extend(Object.extend(Effect.ScaleBy.prototype, Effect.Base.prototype), {
	  initialize: function(element, byWidth, byHeight, options) {
		this.element = $(element)
		var options = Object.extend({
		  scaleFromTop: true,
		  scaleMode: 'box',        // 'box' or 'contents' or {} with provided values
		  scaleByWidth: byWidth,
		  scaleByHeight: byHeight
		}, arguments[3] || {});
		this.start(options);
	  },
	  setup: function() {
		this.elementPositioning = this.element.getStyle('position');

		this.originalTop  = this.element.offsetTop;
		this.originalLeft = this.element.offsetLeft;

		this.dims = null;
		if(this.options.scaleMode=='box')
		  this.dims = [this.element.offsetHeight, this.element.offsetWidth];
		 if(/^content/.test(this.options.scaleMode))
		  this.dims = [this.element.scrollHeight, this.element.scrollWidth];
		if(!this.dims)
		  this.dims = [this.options.scaleMode.originalHeight,
					   this.options.scaleMode.originalWidth];

		this.deltaY = this.options.scaleByHeight;
		this.deltaX = this.options.scaleByWidth;
	  },
	  update: function(position) {
		var currentHeight = this.dims[0] + (this.deltaY * position);
		var currentWidth = this.dims[1] + (this.deltaX * position);

		currentHeight = (currentHeight > 0) ? currentHeight : 0;
		currentWidth = (currentWidth > 0) ? currentWidth : 0;

		this.setDimensions(currentHeight, currentWidth);
	  },

	  setDimensions: function(height, width) {
		var d = {};
		d.width = width + 'px';
		d.height = height + 'px';

		var topd  = Math.round((height - this.dims[0])/2);
		var leftd = Math.round((width  - this.dims[1])/2);
		if(this.elementPositioning == 'absolute' || this.elementPositioning == 'fixed') {
			if(!this.options.scaleFromTop) d.top = this.originalTop-topd + 'px';
			d.left = this.originalLeft-leftd + 'px';
		} else {
			if(!this.options.scaleFromTop) d.top = -topd + 'px';
			d.left = -leftd + 'px';
		}
		this.element.setStyle(d);
	  }
	});
	</script>
    <?php
}
function subscribe_by_email_output_modalbox_css() {
	?>
    <style type="text/css" media="screen">
	#MB_overlay {
		position: absolute;
		margin: auto;
		top: 0;	left: 0;
		width: 100%; height: 100%;
		z-index: 9999;
		background-color: #000!important;
	}
	#MB_overlay[id] { position: fixed; }

	#MB_window {
		position:absolute;
		top: 0;
		border: 0 solid;
		text-align:left;
		z-index:10000;
	}
	#MB_window[id] { position: fixed!important; }

	#MB_frame {
		position:relative;
		background-color: #EFEFEF;
		height:100%;
	}

	#MB_header {
		margin:0;
		height: 28px;
	}

	#MB_content {
		padding: 6px .75em;
		overflow:auto;
	}

	#MB_caption {
		font: bold 85% "Lucida Grande", Arial, sans-serif;
		text-shadow: #FFF 0 1px 0;
		padding: .5em 2em 0 .75em;
		margin: 0;
		text-align: left;
	}

	#MB_close {
		display:block;
		position:absolute;
		right:5px; top:4px;
		padding:2px 3px;
		font-weight:bold;
		text-decoration:none;
		font-size:13px;
	}
	#MB_close:hover {
		background:transparent;
	}

	#MB_loading {
		padding: 1.5em;
		text-indent: -10000px;
		background: transparent url(spinner.gif) 50% 0 no-repeat;
	}

	/* Color scheme */
	#MB_window {
		background-color:#EFEFEF;
		color:#000;
	}
	#MB_content { border-top: 1px solid #F9F9F9; }
	#MB_header {
	  background-color:#DDD;
	  border-bottom: 1px solid #CCC;
	}
	#MB_caption { color:#000 }
	#MB_close { color:#777 }
	#MB_close:hover { color:#000 }


	/* Alert message */
	.MB_alert {
		margin: 10px 0;
		text-align: center;
	}
	</style>
    <?php
}
//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function subscribe_by_email_manage_output() {
	global $wpdb;

	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...', 'subscribe-by-email') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e( urldecode($_GET['updatedmsg']), 'subscribe-by-email') ?></p></div><?php
	}

	echo '<div class="wrap" style="position:relative;">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$apage = isset( $_GET['apage'] ) ? intval( $_GET['apage'] ) : 1;
			$num = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : 15;
			$s = wp_specialchars( trim( $_GET[ 's' ] ) );

			$query = "SELECT * FROM " . $wpdb->prefix . "subscriptions ";

			if( isset($_GET['s']) ) {
				$query .= " WHERE subscription_email LIKE '%" . $s . "%' ";
			}

			if( isset( $_GET['sortby'] ) == false ) {
				$_GET['sortby'] = 'id';
			}

			if( $_GET['sortby'] == 'created' ) {
				$query .= ' ORDER BY subscription_created ';
			} elseif( $_GET['sortby'] == 'id' ) {
				$query .= ' ORDER BY subscription_ID ';
			}

			$query .= ( $_GET['order'] == 'DESC' ) ? 'ASC' : 'DESC';

			if( !empty($s) ) {
				$total = $wpdb->get_var( str_replace('SELECT *', 'SELECT COUNT(*)', $query) );
			} else {
				$total = $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "subscriptions ");
			}

			$query .= " LIMIT " . intval( ( $apage - 1 ) * $num) . ", " . intval( $num );
			$subscriptions = $wpdb->get_results( $query, ARRAY_A );

			// Pagination
			$url2 = "&amp;order=" . $_GET['order'] . "&amp;sortby=" . $_GET['sortby'] . "&amp;s=";
			if( isset($_GET['s']) ) {
				$url2 .= $s;
			}
			$navigation = paginate_links( array(
				'base' => add_query_arg( 'apage', '%#%' ).$url2,
				'format' => '',
				'total' => ceil($total / $num),
				'current' => $apage
			));
			?>
			<h2><?php _e('Subscriptions', 'subscribe-by-email') ?></h2>
			<form id="form-subscriptions-list" action="admin.php?page=subscription&action=cancel_subscriptions" method="post">

			<div class="tablenav">
				<?php if ( $navigation ) echo "<div class='tablenav-pages'>" . $navigation . "</div>"; ?>

				<div class="alignleft">
					<input type="submit" value="<?php _e('Cancel Subscription(s)', 'subscribe-by-email') ?>" name="cancel" class="button-secondary delete" />
					<br class="clear" />
				</div>
			</div>

			<br class="clear" />

			<?php
			// define the columns to display, the syntax is 'internal name' => 'display name'
			$list_columns = array(
				'email'    => __('Email', 'subscribe-by-email'),
				'created' => __('Created', 'subscribe-by-email'),
				'note'   => __('Note', 'subscribe-by-email')
			);

			$sortby_url = "s=";
			if( $_GET[ 'blog_ip' ] ) {
				$sortby_url .= "&ip_address=" . urlencode( $s );
			} else {
				$sortby_url .= urlencode( $s ) . "&ip_address=" . urlencode( $s );
			}
			?>

			<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
				<thead>
					<tr>
					<th scope="col" class="check-column"></th>
					<?php foreach($list_columns as $column_id => $column_display_name) {
						?>
						<th scope="col"><?php echo $column_display_name ?></th>
					<?php } ?>
					</tr>
				</thead>
				<tbody id='the-list'>
				<?php
				if ($subscriptions) {
					$bgcolor = $class = '';
					foreach ($subscriptions as $subscription) {
						$class = ('alternate' == $class) ? '' : 'alternate';

						echo "<tr class='$class'>";

						foreach( $list_columns as $column_name=>$column_display_name ) {
							switch($column_name) {
								case 'email': ?>
									<th scope="row">
										<input type='checkbox' id='subscription_<?php echo $subscription['subscription_ID'] ?>' name='subscriptions[]' value='<?php echo $subscription['subscription_ID'] ?>' />
									</th>
									<th scope="row">
										<?php echo $subscription['subscription_email'] ?>
									</th>
								<?php
								break;

								case 'created': ?>
									<th scope="row">
										<?php echo date(get_option('date_format'),$subscription['subscription_created']); ?>
									</th>
								<?php
								break;

								case 'note': ?>
									<th scope="row">
										<?php echo __($subscription['subscription_note'], 'subscribe-by-email'); ?>
									</th>
								<?php
								break;
							}
						}
						?>
						</tr>
						<?php
					}
				} else { ?>
					<tr>
						<td colspan="8"><?php _e('No subscriptions found.', 'subscribe-by-email') ?></td>
					</tr>
				<?php
				}
				?>
				</tbody>
			</table>
			</form>
			</div>

			<div class="wrap">
				<h2><?php _e('Search Subscriptions', 'subscribe-by-email') ?></h2>
				<form method="get" action="admin.php">
					<input type="hidden" name="page" value="subscription" />
					<input type="hidden" name="action" value="search_subscription" />
					<table class="form-table">
						<tr class="form-field form-required">
							<th style="text-align:center;" scope='row'><?php _e('Email', 'subscribe-by-email') ?></th>
							<td><input name="s" type="text" value="<?php if (isset($_GET['s'])) echo stripslashes( wp_specialchars( $s, 1 ) ); ?>" size="20" title="<?php _e('Email', 'subscribe-by-email') ?>"/></td>
						</tr>
					</table>
					<p class="submit">
						<input class="button" type="submit" name="go" value="<?php _e('Search Subscriptions', 'subscribe-by-email') ?>" /></p>
				</form>
			</div>

			<div class="wrap">
				<h2><?php _e('Add Subscription', 'subscribe-by-email') ?></h2>
				<form method="post" action="admin.php?page=subscription&action=create_subscription">
					<table class="form-table">
						<tr class="form-field form-required">
							<th style="text-align:center;" scope='row'><?php _e('Email', 'subscribe-by-email') ?></th>
							<td><input name="email" type="text" size="20" title="<?php _e('Email', 'subscribe-by-email') ?>"/></td>

						</tr>
						<tr class="form-field">
							<td colspan='2'><?php _e('A new subscription will be created for the above email address.', 'subscribe-by-email') ?></td>
						</tr>
					</table>
					<p class="submit">
						<input class="button" type="submit" name="go" value="<?php _e('Create Subscription', 'subscribe-by-email') ?>" /></p>
				</form>
			<?php
		break;
		//---------------------------------------------------//
		case "create_subscription":
			if  ( !empty( $_POST['email'] ) ) {
				if (subscribe_by_email_create_subscription($_POST['email'],"Manual Subscription")) {
					$url_message = urlencode(__('Subscription created.', 'subscribe-by-email'));
				} else {
					$url_message = urlencode(__('Subscription not created due to possible duplicate subscription.', 'subscribe-by-email'));
				}
				echo "
				<script type='text/javascript'>
				window.location='admin.php?page=subscription&updated=true&updatedmsg=" . $url_message . "';
				</script>
				";
			} else {
				echo "
				<script type='text/javascript'>
				window.location='admin.php?page=subscription';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "cancel_subscriptions":
			if  ( !empty( $_POST['subscriptions'] ) ) {
				$subscriptions = $_POST['subscriptions'];
				if ( is_array( $subscriptions ) ) {
					if ( count ( $subscriptions ) > 0 ) {
						foreach ( $subscriptions as $subscription ) {
							subscribe_by_email_cancel_subscription($subscription);
						}
					}
				}
				echo "
				<script type='text/javascript'>
				window.location='admin.php?page=subscription&updated=true&updatedmsg=" . urlencode(__('Subscription(s) canceled.', 'subscribe-by-email')) . "';
				</script>
				";
			} else {
				echo "
				<script type='text/javascript'>
				window.location='admin.php?page=subscription';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "1":
		break;
	}
	echo '</div>';
}

function subscribe_by_email_settings_output() {
	global $wpdb, $wp_roles, $current_user, $subscribe_by_email_instant_notification_content;

	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...', 'subscribe-by-email') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e( urldecode($_GET['updatedmsg']) , 'subscribe-by-email') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$subscribe_by_email_auto_subscribe = get_option('subscribe_by_email_auto_subscribe', 'no');
			$subscribe_by_email_excerpts = get_option('subscribe_by_email_excerpts', 'no');
			?>
			<h2><?php _e('Settings', 'subscribe-by-email') ?></h2>
            <form method="post" action="admin.php?page=subscription_settings&action=process">
            <table class="form-table">
		<tr valign="top">
		    <th scope="row"><?php _e('Auto-subscribe Enabled', 'subscribe-by-email') ?></th>
		    <td>
		    <input name="subscribe_by_email_auto_subscribe" id="subscribe_by_email_auto_subscribe" value="yes" <?php if ( $subscribe_by_email_auto_subscribe == 'yes' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Yes', 'subscribe-by-email'); ?><br />
		    <input name="subscribe_by_email_auto_subscribe" id="subscribe_by_email_auto_subscribe" value="no" <?php if ( $subscribe_by_email_auto_subscribe == 'no' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('No', 'subscribe-by-email'); ?>
		    <br /><?php _e('Automatically subscribe all users on this blog.', 'subscribe-by-email') ?></td>
		</tr>
		<tr valign="top">
		    <th scope="row"><?php _e('Excerpt Enabled', 'subscribe-by-email') ?></th>
		    <td>
		    <input name="subscribe_by_email_excerpts" id="subscribe_by_email_excerpts" value="yes" <?php if ( $subscribe_by_email_excerpts == 'yes' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('Yes', 'subscribe-by-email'); ?><br />
		    <input name="subscribe_by_email_excerpts" id="subscribe_by_email_excerpts" value="no" <?php if ( $subscribe_by_email_excerpts == 'no' ) { echo 'checked="checked"'; } ?> type="radio"> <?php _e('No', 'subscribe-by-email'); ?>
		    <br /><?php _e('Include post excerpts in notification emails.', 'subscribe-by-email') ?></td>
		</tr>
		<tr valign="top">
		    <th scope="row"><?php _e('Notification Content', 'subscribe-by-email') ?></th>
		    <td>
			<textarea name="subscribe_by_email_instant_notification_content"
				id="subscribe_by_email_instant_notification_content"
				rows="12" cols="35"><?php print $subscribe_by_email_instant_notification_content; ?></textarea>
			<br /><?php _e('You can use following variables BLOGNAME, POST_TITLE, POST_URL, EXCERPT, BLOGNAME and CANCEL_URL', 'subscribe-by-email') ?>
		    </td>
		</tr>
            </table>
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Save Changes', 'subscribe-by-email') ?>" />
            </p>
            </form>
			<?php
		break;
		//---------------------------------------------------//
		case "process":
			update_option( "subscribe_by_email_auto_subscribe", $_POST[ 'subscribe_by_email_auto_subscribe' ] );
			update_option( "subscribe_by_email_excerpts", $_POST[ 'subscribe_by_email_excerpts' ] );
			update_option( "subscribe_by_email_instant_notification_content", $_POST['subscribe_by_email_instant_notification_content']);
			echo "
			<script type='text/javascript'>
			window.location='admin.php?page=subscription_settings&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'subscribe-by-email')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
