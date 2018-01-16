<?php

class Incsub_Subscribe_By_Email_Recaptcha {

	private static $_instance = null;
	private static $type = null;
	private static $keys = array( 
		'public' => null ,
		'secret' => null ,
	);

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

		if( is_admin() && ! wp_doing_ajax() ){
			return;
		}

		add_action( 'sbe_widget_form_fields', array( $this, 'load_recaptcha' ), 10 );
		add_action( 'sbe_shortcode_form_fields', array( $this, 'load_recaptcha' ), 10 );
		
		add_action( 'sbe_widget_validate_form', array( $this, 'validate_recaptcha' ), 10 );
		add_action( 'sbe_shortcode_validate_form', array( $this, 'validate_recaptcha' ), 10 );

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
				<script src="https://www.google.com/recaptcha/api.js"></script>
				<?php
				$return = ob_get_clean();
				break;
			
			case 'invisible':

				?>
				<div class="sbe-recaptcha-holder"></div>

				<script type="text/javascript">
					var sbe_render_invisible_recaptcha = function() {

						for (var i = 0; i < document.forms.length; ++i) {

							var form = document.forms[i];
							var holder = form.querySelector('.sbe-recaptcha-holder');

							if (null === holder){
								continue;
							}

							(function(frm){

								var input = frm.elements['subscription-email'];
								var holderId = grecaptcha.render(holder,{
									'sitekey': '<?php echo self::$keys['public']; ?>',
									'size': 'invisible',
									'badge' : 'bottomright', // possible values: bottomright, bottomleft, inline
									'callback' : function (recaptchaToken) {
										//HTMLFormElement.prototype.submit.call(frm);
									}
								});

								input.addEventListener("focus", function(){
									let executed = this.getAttribute( 'recaptcha_executed' );
									if( executed == 'executed' ){
										return;
									}
									this.setAttribute( 'recaptcha_executed', 'executed' );
									grecaptcha.execute(holderId); 
								});

							})(form);
							
						}

					};
				</script>				

				<script src="https://www.google.com/recaptcha/api.js?onload=sbe_render_invisible_recaptcha&render=explicit" async defer></script>
				<?php
				$return = ob_get_clean();

				break;
		}

		echo $return;

	}


}
