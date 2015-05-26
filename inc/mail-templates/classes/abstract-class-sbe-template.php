<?php

/**
 * The Abstract class for any Email Template
 */
abstract class Abstract_SBE_Mail_Template {

	/**
	 * The posts list
	 * @var array
	 */
	protected $posts = array();

	/**
	 * When iterating through content
	 * this will save the current index in the posts list
	 * @var Numeric
	 */
	protected $current_post_index = -1;

	/**
	 * When iterating through content
	 * this will save the current post
	 * @var WP_Post/false
	 */
	protected $current_post = false;

	/**
	 * The subscriber that this template is going to be sent to
	 * @var SBE_Subscriber/false
	 */
	protected $subscriber = false;

	/**
	 * The Digest subject
	 * @var String
	 */
	protected $subject = '';
	
	/**
	 * Class Constructor
	 * 
	 * Accept an array of posts and a subscriber. If the subscirber is false,
	 * then the digest is not going to be sent to anyone
	 * 
	 * @param array   $posts      List of posts that is going to be rendered
	 * @param boolean $subscriber The subscriber that this template is going to be sent to
	 */
	public function __construct( $posts = array(), $subscriber = false ) {
		$this->posts = $posts;
		$this->subscriber = $subscriber;
		$this->generate_subject();
	}

	/**
	 * The main class function. It renders everything
	 */
	public function content() {
		add_filter( 'excerpt_more', array( $this, 'excerpt_more' ), 80 );
		add_filter( 'excerpt_length', array( $this, 'excerpt_length' ), 80 );
		remove_all_filters( 'the_content' );
		add_filter( 'the_content', 'wptexturize'        );
		add_filter( 'the_content', 'convert_smilies'    );
		add_filter( 'the_content', 'convert_chars'      );
		add_filter( 'the_content', 'wpautop'            );
		add_filter( 'the_content', 'shortcode_unautop'  );
		add_filter( 'the_content', 'prepend_attachment' );
		add_filter( 'the_content', 'do_shortcode', 11 );

		add_filter( 'the_title', array( $this, 'get_the_title' ) );
		add_filter( 'the_content', array( $this, 'get_the_content' ) );
		add_filter( 'the_excerpt', array( $this, 'get_the_excerpt' ) );
		

		do_action( 'sbe_mail_template_before_content', $this->posts );

		$this->header();
		$this->body();
		$this->footer();

		do_action( 'sbe_mail_template_after_content', $this->posts );

		remove_filter( 'the_title', array( $this, 'get_the_title' ) );
		remove_filter( 'the_content', array( $this, 'get_the_content' ) );
		remove_filter( 'the_excerpt', array( $this, 'get_the_excerpt' ) );
		remove_filter( 'excerpt_more', array( $this, 'excerpt_more' ), 80 );
		remove_filter( 'excerpt_length', array( $this, 'excerpt_length' ), 80 );
		add_filter( 'the_content', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
	}

	/**
	 * Iterate through posts list and render every post in it.
	 */
	public function the_loop() {

		$total_posts = $this->get_total_posts();
		for ( $i = 1; $i <= $total_posts; $i++ ) {
			global $post;

			$this->next_post();
			$post = $this->get_current_post();

			setup_postdata( $post );

			$this->post();
		}

		wp_reset_postdata();
	}

	public function header() {
		sbe_mail_template_load_template_part( 'header' );
	}

	public function footer() {
		sbe_mail_template_load_template_part( 'footer' );
	}

	public function body() {
		sbe_mail_template_load_template_part( 'body' );
	}

	public function post() {
		sbe_mail_template_load_template_part( 'post' );
	}

	public abstract function excerpt_more();

	public abstract function excerpt_length();
		

	public function next_post() {
		$this->current_post_index++;
		$this->current_post = $this->posts[ $this->current_post_index ];
	}

	public function get_total_posts() {
		return count( $this->posts );
	}

	public function get_current_post() {
		return $this->current_post;		
	}

	public function get_subject() {
		return $this->subject;
	}

	public function get_subscriber() {
		return $this->subscriber;
	}

	public function get_the_title( $title ) {
		global $post;

		if ( isset( $post->is_dummy ) && $post->is_dummy ) {
			return $post->post_title;
		}

		return $title;
	}

	public function get_the_content( $content ) {
		global $post;

		if ( isset( $post->is_dummy ) && $post->is_dummy ) {
			return $post->post_content;
		}

		return $title;
	}

	public function get_the_excerpt( $excerpt ) {
		global $post;

		if ( isset( $post->is_dummy ) && $post->is_dummy )
			return wp_trim_words( $post->post_content, $this->excerpt_length(), $this->excerpt_more() );

		return $title;
	}

	public function get_manage_subscriptions_url() {
		if ( is_object( $this->subscriber ) && $manage_subs_page_id = sbe_get_manage_subscriptions_page_id() )
			return add_query_arg( 'sub_key', $this->subscriber->subscription_key, get_permalink( $manage_subs_page_id ) );
		
		return '';

	}

	public function get_unsubscribe_url() {
		if ( is_object( $this->subscriber ) ) {
			$url = trailingslashit( get_home_url() );
			$key = $this->subscriber->subscription_key;
			return add_query_arg( 'sbe_unsubscribe', $key, $url );
		}

		return '';
	}


	public function generate_subject() {

		$settings = incsub_sbe_get_settings();
		$this->subject = $settings['subject'];

		$titles = array();
		foreach ( $this->posts as $content_post ) {
			$titles[] = $content_post->post_title;
		}

		if ( strpos( $this->subject, '%title%' ) > -1 ) {
			$this->subject = trim( $this->subject );

			// Now we count how many characters we have in the subject right now
			// We have to substract the wildcard length (7)
			$subject_length = strlen( $this->subject ) - 7;

			$max_length_surpassed = ( $subject_length >= Incsub_Subscribe_By_Email::$max_subject_length );
			$titles_count = 0;

			if ( $subject_length < Incsub_Subscribe_By_Email::$max_subject_length ) {
				foreach ( $titles as $title ) {

					$subject_length = $subject_length + strlen( $title );
					if ( $subject_length >= Incsub_Subscribe_By_Email::$max_subject_length )
						break;
					
					$titles_count++;
				}
			}

			// Could be that the first title is too long. In that case we will force to show the first title
			if ( 0 == $titles_count )
				$titles_count = 1;

			$tmp_subject = implode( '; ', array_slice( $titles, 0, $titles_count ) );
			$this->subject = str_replace( '%title%', $tmp_subject, $this->subject );
		}
	}
			
}


/**
 * Get an Email Template instance
 * @param  array   $posts      Content to be sent
 * @param  String $subject The digest subject
 * @param  boolean $subscriber Subscriber object/false
 * @return object              SBE_Mail_Template object
 */
function sbe_get_email_template( $posts = array(), $subscriber = false ) {
	include_once( 'class-sbe-template.php' );
	$classname = apply_filters( 'sbe_email_template_classname', 'SBE_Mail_Template' );

	$sbe_template = false;
	$variables = compact( 'posts', 'subject', 'subscriber' );
    if ( class_exists( $classname ) ) {
        $r = new ReflectionClass( $classname );
        $sbe_template = $r->newInstanceArgs( $variables );
    }

    return $sbe_template;
}

/**
 * Renders the Template
 * 
 * Sets a new global template object and renders the content
 * 
 * @param Object $template The templte Object
 */
function sbe_render_email_template( $template, $echo = true ) {	
	global $sbe_template;

	if ( $template ) {
		$sbe_template = $template;

		if ( ! $echo )
			ob_start();

		$sbe_template->content();

		if ( ! $echo )
			return ob_get_clean();
	}
}


/**
 * Wrapper function for Abstract_SBE_Mail_Template::the_loop()
 */
function sbe_email_template_loop() {
	global $sbe_template;

	if ( $sbe_template )
		$sbe_template->the_loop();
}

/**
 * Return the header color setting
 * 
 * @return String Header color string
 */
function sbe_get_header_color() {
	$settings = incsub_sbe_get_settings();
	return $settings['header_color'];
}

/**
 * Return the header text color setting
 * 
 * @return String Header text color string
 */
function sbe_get_header_text_color() {
	$settings = incsub_sbe_get_settings();
	return $settings['header_text_color'];
}

/**
 * Return the header text Setting
 * 
 * @return String  header text Setting
 */
function sbe_get_header_text() {
	$settings = incsub_sbe_get_settings();
	return wpautop( $settings['header_text'] );
}

/**
 * Return the logo width setting
 * 
 * @return Integer Logo width setting
 */
function sbe_get_logo_width() {
	$settings = incsub_sbe_get_settings();
	return $settings['logo_width'];	
}

/**
 * Return if the blog Name should be displayed in the digest
 * 
 * @return Boolean
 */
function sbe_display_blog_name() {
	$settings = incsub_sbe_get_settings();
	return $settings['show_blog_name'];
}

/**
 * Return the logo URL
 * 
 * @return String Logo URL
 */
function sbe_get_logo() {
	$settings = incsub_sbe_get_settings();
	return $settings['logo'];
}

/**
 * Return the From Sender Setting
 * 
 * @return String From Sender Setting
 */
function sbe_get_from_sender() {
	$settings = incsub_sbe_get_settings();
	return $settings['from_sender'];
}

/**
 * Return the Footer Text Setting
 * 
 * @return String Footer Text Setting
 */
function sbe_get_footer_text() {
	$settings = incsub_sbe_get_settings();
	return wpautop( $settings['footer_text'] );
}

/**
 * Return the Digest Subject
 * 
 * @return String Digest subject
 */
function sbe_get_email_template_subject() {
	global $sbe_template;
	return $sbe_template->get_subject();
}

/**
 * Return the User Unsubscribe URL
 * 
 * @return String the User Unsubscribe URL
 */
function sbe_email_template_get_unsubscribe_url() {
	global $sbe_template;

	if ( $sbe_template  ) {
		$url = $sbe_template->get_unsubscribe_url();
		return $url;
	}

	return '';
}

/**
 * Return the User Manage Subscriptions URL
 * 
 * @return String the User Manage Subscriptions URL
 */
function sbe_email_template_get_manage_subscriptions_url() {
	global $sbe_template;
	
	if ( $sbe_template && is_object( $sbe_template->get_subscriber() ) )
		return $sbe_template->get_manage_subscriptions_url();

	return '';
}

/**
 * Return if there's a Manage Subscription Page Selected
 * 
 * @return Boolean
 */
function sbe_get_manage_subscriptions_page_id() {
	$settings = incsub_sbe_get_settings();
	return $settings['manage_subs_page'];
}

/**
 * Return if the posts featured images should be displayed in the Digests
 * 
 * @return Boolean
 */
function sbe_show_featured_image() {
	$settings = incsub_sbe_get_settings();
	return $settings['featured_image'];
}

/**
 * Return if the digest should display only the excerpt or full post
 * 
 * @return Boolean True if Full Post must be displayed
 */
function sbe_mail_template_send_full_post() {
	$settings = incsub_sbe_get_settings();
	return $settings['send_full_post'];
}

/**
 * Check if post has an image attached.
 * 
 * @return bool Whether post has an image attached.
 */
function sbe_mail_template_has_post_thumbnail() {
	global $post;

	// Dummy post, always has a thumbnail
	if ( isset( $post->is_dummy ) )
		return true;

	return has_post_thumbnail();
}

/**
 * Return the permalink for the current post.
 *
 * @return string The post permalink
 */
function sbe_mail_template_the_permalink() {
	// If the permalink is false (in case the  post is a dummy post)
	// Return empty string
	$permalink = get_permalink();
	if ( false === $permalink )
		echo '';

	echo $permalink;
}

/**
 * Load parts of the template
 */
function sbe_mail_template_load_template_part( $name ) {
	$file = 'subscribe-by-email/' . $name . '.php';

	$template = locate_template( array( $file ) );
	if ( ! $template )
		$template = INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/views/' . $name . '.php';

	include( $template );
}

/**
 * Wrapper function for the_content(). It avoids some warnings if the post is a dummy post
 */
function sbe_mail_template_the_content() {
	global $post;

	if ( isset( $post->is_dummy ) && $post->is_dummy ) {
		echo $post->post_content;
		return;
	}

	the_content();
}

/**
 * Display the post thumbnail.
 * 
 * @param string $size Optional. Registered image size to use. Default 'post-thumbnail'.
 */
if ( ! function_exists( 'sbe_mail_template_the_post_thumbnail' ) ) {
	function sbe_mail_template_the_post_thumbnail( $size = 'thumbnail' ) {
		global $post;

		if ( isset( $post->is_dummy ) ) {
			// This is not a real post
			?><div style="background:#DEDEDE !important;width:150px;height:100px;box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);float:left;border:1px solid #DEDEDE;padding:4px;margin:0 10px 10px 0;"></div><?php
		}
		else {
			// A real post
			the_post_thumbnail( $size, $attr = array( 'style' => 'max-width:150px;box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);float:left;background:#FFFFFF;border:1px solid #DEDEDE;padding:4px;margin:0 10px 10px 0;' ) );
		}
	}	
}


