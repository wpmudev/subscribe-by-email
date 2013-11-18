<?php

class Incsub_Subscribe_By_Email_Follow_Button {

	private $settings;

	public function __construct( $settings ) {
		add_action( 'wp_footer', array( &$this,'render_follow_button' ) );
		add_action( 'wp_enqueue_scripts', array( &$this,'add_styles' ) );
		add_action( 'template_redirect', array( &$this, 'validate_form' ) );
		$this->settings = $settings;
	}

	public function add_styles() {
		wp_enqueue_style( 'follow-button-styles', INCSUB_SBE_ASSETS_URL . '/css/follow-button.css', array(), '20131118' );
		wp_enqueue_script( 'follow-button-scripts', INCSUB_SBE_ASSETS_URL . '/js/follow-button.js', array( 'jquery' ) );
	}

	public function validate_form() {
		if ( isset( $_POST['action'] ) && 'sbe-subscribe' == $_POST['action'] ) {

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'sbe-subscribe' ) ) 
				return;

			$email = sanitize_email( $_POST['email'] );
			if ( ! is_email( $email ) )
				return;

			$sid = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'User subscribed', INCSUB_SBE_LANG_DOMAIN ), __( 'Follow Button', INCSUB_SBE_LANG_DOMAIN ) );

			if ( ! $sid )
				return;

			$model = incsub_sbe_get_model();
			if ( isset( $_POST['follow_meta']['first_name'] ) )
				$model->add_subscriber_meta( $sid, 'first_name', stripslashes_deep( $_POST['follow_meta']['first_name'] ) );

			if ( isset( $_POST['follow_meta']['last_name'] ) )
				$model->add_subscriber_meta( $sid, 'last_name', stripslashes_deep( $_POST['follow_meta']['last_name'] ) );

			if ( isset( $_POST['follow_meta']['address'] ) )
				$model->add_subscriber_meta( $sid, 'address', stripslashes_deep( $_POST['follow_meta']['address'] ) );

			$link = add_query_arg( 'sbe-followsubs', 'true' );
			wp_redirect( $link );
		}
	}

	public function render_follow_button() {
		$style = isset( $_GET['sbe-followsubs'] ) ? 'style="bottom:0px;"' : 'style="bottom:-1500px"';
		?>
			<div id="sbe-follow" <?php echo $style; ?> class="<?php echo isset( $_GET['sbe-followsubs'] ) ? 'sbe-opened' : ''; ?>">
				<a class="sbe-follow-link" href="#sbe-follow-wrap"> <span><?php _e( 'Follow', INCSUB_SBE_LANG_DOMAIN ); ?></span></a>
				<div id="sbe-follow-wrap">

					<?php if ( isset( $_GET['sbe-followsubs'] ) && 'true' == $_GET['sbe-followsubs'] ): ?>

						<p><?php _e( 'Thank you! A confirmation email is on the way...', INCSUB_SBE_LANG_DOMAIN ); ?></p>

					<?php else: ?>

						<h2><?php _e( 'Follow this blog', INCSUB_SBE_LANG_DOMAIN ); ?></h2>

						<form action="" method="post">
							
							<?php if ( 'inmediately' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get every new post delivered right to your inbox.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php elseif ( 'weekly' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get a weekly email of all new posts.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php elseif ( 'weekly' == $this->settings['frequency'] ): ?>
								<p><?php _e( 'Get a daily email of all new posts.', INCSUB_SBE_LANG_DOMAIN ); ?></p>
							<?php endif; ?>

							<p>
								<input type="email" name="email" placeholder="<?php _e( 'Your email', INCSUB_SBE_LANG_DOMAIN ); ?>">
							</p>
							<?php if ( ! empty( $this->settings['follow_button_meta'] ) ): ?>
								<?php $meta = $this->settings['follow_button_meta']; ?>
								<?php if ( in_array( 'first_name', $meta ) ): ?>
									<p><input type="text" name="follow_meta[first_name]" placeholder="<?php _e( 'Your first name', INCSUB_SBE_LANG_DOMAIN ); ?>"></p>
								<?php endif; ?>	
								<?php if ( in_array( 'last_name', $meta ) ): ?>
									<p><input type="text" name="follow_meta[last_name]" placeholder="<?php _e( 'Your last name', INCSUB_SBE_LANG_DOMAIN ); ?>"></p>
								<?php endif; ?>	
								<?php if ( in_array( 'address', $meta ) ): ?>
									<p><textarea name="follow_meta[address]" cols="30" rows="3" placeholder="<?php _e( 'Your address', INCSUB_SBE_LANG_DOMAIN ); ?>"></textarea></p>
								<?php endif; ?>	
							<?php endif; ?>
							
							<input type="hidden" name="action" value="sbe-subscribe">
							
							<?php wp_nonce_field( 'sbe-subscribe' ); ?>
							<p><input type="submit" value="<?php _e( 'Subscribe me!', INCSUB_SBE_LANG_DOMAIN ); ?>"></p>
						</form>

					<?php endif; ?>

				</div>
			</div>
		<?php
	}
}