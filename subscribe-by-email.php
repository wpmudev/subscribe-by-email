<?php
/*
Plugin Name: Subscribe by Email
Plugin URI: http://premium.wpmudev.org/project/subscribe-by-email
Description: This plugin allows you and your users to offer subscriptions to email notification of new posts
Author: S H Mohanjith (Incsub), Philip John (Incsub) 
Version: 1.1.3
Author URI: http://premium.wpmudev.org
WDP ID: 127
Text Domain: subscribe-by-email
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

$subscribe_by_email_current_version = '1.1.2';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('admin_head', 'subscribe_by_email_make_current');
add_action('admin_menu', 'subscribe_by_email_plug_pages');
add_action('wp_enqueue_scripts', 'subscribe_by_email_enqueue_js');
add_action('wp_print_styles', 'subscribe_by_email_enqueue_css');
add_action('widgets_init', 'subscribe_by_email_widget_init');
add_action('transition_post_status', 'subscribe_by_email_process_instant_subscriptions', $priority = 2, $accepted_args = 3);
add_action('add_user_to_blog', 'subscribe_by_email_add_user_to_blog', 3, 3);
add_action('admin_footer', 'subscribe_by_email_check_blog_users');
add_action('init', 'subscribe_by_email_init');
add_action('init', 'subscribe_by_email_external_create_subscription');
add_action('wp_footer', 'subscribe_by_email_cancel_process');
add_action('sbe_send_scheduled_notifications','subscribe_by_email_send_scheduled_notifications');

add_action('wp_ajax_sbe_create_subscription', 'subscribe_by_email_internal_create_subscription');
add_action('wp_ajax_nopriv_sbe_create_subscription', 'subscribe_by_email_internal_create_subscription');
add_action('wp_ajax_sbe_cancel_subscription', 'subscribe_by_email_internal_cancel_subscription');
add_action('wp_ajax_nopriv_sbe_cancel_subscription', 'subscribe_by_email_internal_cancel_subscription');

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function subscribe_by_email_init() {
	global $subscribe_by_email_instant_notification_subject, $subscribe_by_email_instant_notification_content;

	load_plugin_textdomain('subscribe-by-email', false, 'subscribe-by-email/languages');
	
	add_option('subscribe_by_email_instant_notification_subject', "BLOGNAME: New Post");
	add_option('subscribe_by_email_instant_notification_content',
"Dear Subscriber,

BLOGNAME has posted a new item: POST_TITLE

You can read the post in full here: POST_URL

EXCERPT

Thanks,
BLOGNAME

Cancel subscription: CANCEL_URL");
	
	$subscribe_by_email_instant_notification_subject = get_option('subscribe_by_email_instant_notification_subject');
	$subscribe_by_email_instant_notification_content = get_option('subscribe_by_email_instant_notification_content');
	
	if ( isset($_POST['page']) && isset($_POST['action']) && $_POST['page'] == "subscription" && $_POST['action'] == "export_subscription") {
		global $wpdb;
		$query = "SELECT * FROM " . $wpdb->prefix . "subscriptions ";
		$query .= ' ORDER BY subscription_ID ASC';
		
		$subscriptions = $wpdb->get_results( $query, ARRAY_A );
		
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment;filename='.date('YmdHi').'.csv');
		
		echo '"ID","E-mail","Type","Created","Note"'."\n";
		
		foreach ($subscriptions as $subscription) {
			echo '"'.$subscription['subscription_ID'].'","'.$subscription['subscription_email'].'","'.$subscription['subscription_type'].'","'.$subscription['subscription_created'].'","'.$subscription['subscription_note'].'"'."\n";
		}
	}
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
	global $sbe_button_text;
	$content = '';
	$content .= '<form method="post" id="subscribe-by-email-form">';
	$content .= '<div id="subscribe-by-email-msg"></div>';
        $content .= '<input id="subscription_email" name="subscription_email" style="width:97%;" maxlength="50" value="' . __('ex: john@hotmail.com', 'subscribe-by-email') . '" onfocus="this.value=\'\';" type="text">';
	$content .= '<center>';
	$content .= '<input type="button" class="button" name="create_subscription" value="'.$sbe_button_text.'" style="width:99%;" onclick="SubscribeByEmailCreate();" />';
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
		$this->WP_Widget( 'subscribe-by-email', 'Subscribe by Email', $widget_ops, $control_ops );
	}

	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		global $sbe_button_text;
		extract( $args );

		$title = apply_filters('widget_title', $instance['title'] );
		$text = stripslashes($instance['text']);
		$sbe_button_text = stripslashes($instance['button_text']);

		echo $before_widget;
		echo $before_title . __($title, 'subscribe-by-email') . $after_title;
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
		$instance['button_text'] = strip_tags( $new_instance['button_text'] );

		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('Subscribe by Email', 'subscribe-by-email'), 'text' => __('Completely spam free, opt out any time.', 'subscribe-by-email'), 'button_text' => __('Create Subscription', 'subscribe-by-email'));
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'hybrid', 'subscribe-by-email'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:75%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'text' ); ?>"><?php _e('Text:', 'example', 'subscribe-by-email'); ?></label>
			<input id="<?php echo $this->get_field_id( 'text' ); ?>" name="<?php echo $this->get_field_name( 'text' ); ?>" value="<?php echo $instance['text']; ?>" style="width:75%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'button_text' ); ?>"><?php _e('Subscribe button text:', 'example', 'subscribe-by-email'); ?></label>
			<input id="<?php echo $this->get_field_id( 'button_text' ); ?>" name="<?php echo $this->get_field_name( 'button_text' ); ?>" value="<?php echo $instance['button_text']; ?>" style="width:75%;" />
		</p>
	<?php
	}
}

function subscribe_by_email_widget_init() {
	global $wpdb;
	register_widget( 'subscribe_by_email' );
}

function subscribe_by_email_enqueue_js() {
	wp_register_script('sbe_modal_box', plugins_url('subscribe-by-email/js/modalbox.js'), array('scriptaculous', 'prototype'), '1.1.1', true);
	wp_register_script('sbe_frontend', plugins_url('subscribe-by-email/js/sbe.js'), array('scriptaculous', 'prototype'), '1.1.1', true);
	
	$sbe_localized = array(
		'site_url' => get_option('siteurl').'/wp-admin/admin-ajax.php',
		'subscription_created' => __('Your subscription has been successfully created!', 'subscribe-by-email'),
		'already_subscribed' => __('You are already subscribed!', 'subscribe-by-email'),
		'subscription_cancelled' => __('Your subscription has been successfully canceled!', 'subscribe-by-email'),
		'failed_to_cancel_subscription' => __('Failed to cancel your subscription!', 'subscribe-by-email'),
		'invalid_email' => __('Invalid e-mail address!', 'subscribe-by-email'),
		'default_email' => __('ex: john@hotmail.com', 'subscribe-by-email'),
	);
	//wp_enqueue_script('sbe_modal_box');
	wp_enqueue_script('sbe_frontend');
	
	wp_localize_script('sbe_frontend', 'sbe_localized', $sbe_localized);
}

function subscribe_by_email_enqueue_css() {
	wp_register_style('sbe_modal_box', plugins_url('subscribe-by-email/css/modalbox.css'), null, '1.1.1', 'all');
	
	//wp_enqueue_style('sbe_modal_box');
}

function subscribe_by_email_create_subscription($email,$note) {
	global $wpdb;
	
	if (!is_email($_POST['email'])) {
		return false;
	}

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
		jQuery(document).ready(function() {
			jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong><?php echo _e('Your subscription has been successfully canceled!', 'subscribe-by-email'); ?></strong></ceneter></p></div>');
			window.location += '#subscribe-by-email-msg';
		});
	</script>
	<?php
	}
}

function subscribe_by_email_internal_create_subscription() {
	if ( isset($_POST['action']) && $_POST['action'] == 'sbe_create_subscription' ) {
		if (!is_email($_POST['email'])) {
			header("HTTP/1.1 405 Method Not Allowed");
			echo "Not an e-mail";
			exit();
		}
		if (!subscribe_by_email_create_subscription(str_replace("PLUS", "+", $_POST['email']),"Visitor Subscription")) {
			header("HTTP/1.1 405 Method Not Allowed");
			echo "Not allowed";
			exit();
		}
	}
}

function subscribe_by_email_external_create_subscription() {
	if ( isset($_POST['action']) && $_POST['action'] == 'external-create-subscription') {
		return subscribe_by_email_create_subscription($_POST['subscription_email'],"Visitor Subscription");
	}
}

function subscribe_by_email_internal_cancel_subscription() {
	if ( isset($_POST['action']) && $_POST['action'] == 'sbe_cancel_subscription' ) {
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
	if (!$post_id || empty($post_id))
		return;
	$post = get_post($post_id);
	if ($post && $post->ID == $post_id) {
		subscribe_by_email_send_instant_notifications($post);
	}
}

function subscribe_by_email_send_instant_notifications($post) {
	global $wpdb, $subscribe_by_email_instant_notification_subject, $subscribe_by_email_instant_notification_content;
	
	if (!$post || empty($post->ID)) {
		return;
	}
	
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
	$subscribe_by_email_instant_notification_subject = str_replace("BLOGNAME",$blog_name,$subscribe_by_email_instant_notification_subject);
	$subscribe_by_email_instant_notification_subject = str_replace("POST_TITLE",$post_title,$subscribe_by_email_instant_notification_subject);
	if ( $subscribe_by_email_excerpts == 'yes' ) {
		$subscribe_by_email_instant_notification_subject = str_replace("EXCERPT",$post_excerpt,$subscribe_by_email_instant_notification_subject);
	} else {
		$subscribe_by_email_instant_notification_subject = str_replace("EXCERPT","",$subscribe_by_email_instant_notification_subject);
	}
	
	$subscribe_by_email_instant_notification_content = str_replace("BLOGNAME",$blog_name,$subscribe_by_email_instant_notification_content);
	$subscribe_by_email_instant_notification_content = str_replace("POST_TITLE",$post_title,$subscribe_by_email_instant_notification_content);
	if ( $subscribe_by_email_excerpts == 'yes' ) {
		$subscribe_by_email_instant_notification_content = str_replace("EXCERPT",$post_excerpt,$subscribe_by_email_instant_notification_content);
	} else {
		$subscribe_by_email_instant_notification_content = str_replace("EXCERPT","",$subscribe_by_email_instant_notification_content);
	}
	$subscribe_by_email_instant_notification_content = str_replace("POST_CONTENT",$post_content,$subscribe_by_email_instant_notification_content);
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
			$loop_notification_subject = $subscribe_by_email_instant_notification_subject;
			$loop_notification_content = $subscribe_by_email_instant_notification_content;
			//format notification text
			$loop_notification_content = str_replace("CANCEL_URL",$cancel_url . $subscription_email['subscription_ID'],$loop_notification_content);
			
			$from_email = $admin_email;
			$message_headers = "MIME-Version: 1.0\n" . "From: " . $blog_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
			wp_mail($subscription_email['subscription_email'], $loop_notification_subject, $loop_notification_content, $message_headers);
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
	$action = isset($_GET['action'])?$_GET['action']:'sbe';
	switch( $action ) {
		//---------------------------------------------------//
		default:
			$apage = isset( $_GET['apage'] ) ? intval( $_GET['apage'] ) : 1;
			$num = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : 15;
			$s = isset($_GET[ 's' ])?wp_specialchars( trim( $_GET[ 's' ] ) ):0;

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
			
			$order = isset($_GET['order'])?$_GET['order']:'ASC';
			$query .= ( $order == 'DESC' ) ? 'ASC' : 'DESC';

			if( !empty($s) ) {
				$total = $wpdb->get_var( str_replace('SELECT *', 'SELECT COUNT(*)', $query) );
			} else {
				$total = $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "subscriptions ");
			}

			$query .= " LIMIT " . intval( ( $apage - 1 ) * $num) . ", " . intval( $num );
			$subscriptions = $wpdb->get_results( $query, ARRAY_A );

			// Pagination
			$url2 = "&amp;order=" . $order . "&amp;sortby=" . $_GET['sortby'] . "&amp;s=";
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
			if( isset($_GET[ 'blog_ip' ]) ) {
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
				<h2><?php _e('Export Subscriptions', 'subscribe-by-email') ?></h2>
				<form method="post" action="admin.php">
					<input type="hidden" name="page" value="subscription" />
					<input type="hidden" name="action" value="export_subscription" />
					<p class="submit">
						<input class="button" type="submit" name="go" value="<?php _e('Export Subscriptions', 'subscribe-by-email') ?>" /></p>
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
	global $wpdb, $wp_roles, $current_user, $subscribe_by_email_instant_notification_subject, $subscribe_by_email_instant_notification_content;

	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...', 'subscribe-by-email') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e( urldecode($_GET['updatedmsg']) , 'subscribe-by-email') ?></p></div><?php
	}
	echo '<div class="wrap">';
	$action = isset($_GET['action'])?$_GET['action']:'sbe';
	switch( $action ) {
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
		    <th scope="row"><?php _e('Notification Subject', 'subscribe-by-email') ?></th>
		    <td>
			<input name="subscribe_by_email_instant_notification_subject" size="40"
				id="subscribe_by_email_instant_notification_subject" value="<?php print $subscribe_by_email_instant_notification_subject; ?>" type="text" />
			<br /><?php _e('You can use following variables BLOGNAME, POST_TITLE, and EXCERPT', 'subscribe-by-email') ?>
		    </td>
		</tr>
		<tr valign="top">
		    <th scope="row"><?php _e('Notification Content', 'subscribe-by-email') ?></th>
		    <td>
			<textarea name="subscribe_by_email_instant_notification_content"
				id="subscribe_by_email_instant_notification_content"
				rows="12" cols="42"><?php print $subscribe_by_email_instant_notification_content; ?></textarea>
			<br /><?php _e('You can use following variables BLOGNAME, POST_TITLE, POST_URL, POST_CONTENT, EXCERPT and CANCEL_URL', 'subscribe-by-email') ?>
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
			update_option( "subscribe_by_email_instant_notification_subject", $_POST['subscribe_by_email_instant_notification_subject']);
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
