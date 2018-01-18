<?php

class Incsub_Subscribe_By_Email_Recaptcha {

	private static $_instance = null;
	private static $type = null;
	private static $keys = array( 
		'public' => null ,
		'secret' => null ,
	);
	private static $can_enqueue_scripts = false;

	public static function get_instance() {

		if( is_null( self::$_instance ) ){
			self::$_instance = new Incsub_Subscribe_By_Email_Recaptcha();
		}
		return self::$_instance;
   }


	/**
	 * Recaptcha setup.
	 */
	private function __construct() {

		$this->init();
		$this->front_hooks();

	}


	public function init(){

		$sbe_settings = incsub_sbe_get_settings();
		self::$type = isset( $sbe_settings['recaptcha_type'] ) ? $sbe_settings['recaptcha_type'] : '';
		self::$keys['public'] = isset( $sbe_settings['recaptcha_public_key'] ) ? $sbe_settings['recaptcha_public_key'] : '';
		self::$keys['secret'] = isset( $sbe_settings['recaptcha_secret_key'] ) ? $sbe_settings['recaptcha_secret_key'] : '';

	}

	public function front_hooks(){

		if( ( is_admin() && ! wp_doing_ajax() ) || 'no' == self::$type ){
			return;
		}

		add_action( 'sbe_widget_form_fields', array( $this, 'load_recaptcha' ), 10 );
		add_action( 'sbe_shortcode_form_fields', array( $this, 'load_recaptcha' ), 10 );
		
		add_action( 'sbe_widget_validate_form', array( $this, 'validate_recaptcha' ), 10 );
		add_action( 'sbe_shortcode_validate_form', array( $this, 'validate_recaptcha' ), 10 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_filter('script_loader_tag', array( $this, 'script_attributes' ), 10, 2);

		add_action('wp', array( $this, 'can_enqueue' ), 10 );
		add_action( 'sbe_widget_loaded', array( $this, 'allow_widget_script' ), 10 );

	}

	

	public function validate_recaptcha( $errors ){

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/classes/recaptcha-lib.php' );

		$public = $_POST['g-recaptcha-response'];
		$secret = self::$keys['secret'];
		$lang = "en";
		$resp = null;
		
		// The error code from reCAPTCHA, if any
		$error = null;
		$reCaptcha = new SBE_ReCaptcha($secret);
		
		$resp = $reCaptcha->verifyResponse(
	        $_SERVER["REMOTE_ADDR"],
	        $public
	    );

	    if( ! $resp->success ){
	    	$errors[] = __( 'You need to verify you are human', INCSUB_SBE_LANG_DOMAIN );
	    }

	    return $errors;
	}

	public function load_recaptcha( $echo = true ){

		$return = '';

		switch ( self::$type ) {
			case 'v2':
				ob_start();
				?>
				<div class="g-recaptcha" data-sitekey="<?php echo self::$keys['public']; ?>"></div>				
				<?php
				$return = ob_get_clean();
				break;
			
			case 'invisible':
				ob_start();
				?>
				<div class="sbe-recaptcha-holder"></div>
				<?php
				$return = ob_get_clean();
				break;
		}

		echo $return;

	}

	public function enqueue_scripts(){

		if( ! self::$can_enqueue_scripts ){
			return;
		}

		$script_url = 'https://www.google.com/recaptcha/api.js';

		if( 'invisible' == self::$type ){
			
			$script_url .= '?onload=sbe_render_invisible_recaptcha&render=explicit';

			wp_register_script('sbe-invisible-recaptcha', INCSUB_SBE_ASSETS_URL . 'js/recaptcha.js', array( 'jquery' ), INCSUB_SBE_VERSION );
			$invisible_i8 = array(
				'public_key' => self::$keys['public']
			);
			wp_localize_script( 'sbe-invisible-recaptcha', 'sbe_invisible_recaptcha_i8', $invisible_i8 );
			wp_enqueue_script( 'sbe-invisible-recaptcha' );

		}
		
		wp_enqueue_script('google-recaptcha', $script_url, array(), INCSUB_SBE_VERSION );
	}

	public function script_attributes( $tag, $handle ){

		if( 'google-recaptcha' !== $handle || 'invisible' != self::$type ){
			return $tag;
		}

		return str_replace( ' src', ' async defer src', $tag );

	}

	public function can_enqueue(){
		
		global $post;

		if( has_shortcode( $post->post_content, 'subscribe-by-email-form' ) ){
			self::$can_enqueue_scripts = true;
		}

	}

	public function allow_widget_script(){

		if( self::$can_enqueue_scripts ){
			// Return, as scripts already enqueued
			return;
		}
		self::$can_enqueue_scripts = true;
		$this->enqueue_scripts();
	}

}
