<?php

class SBE_Do_Not_Send_Meta_Box {
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
	}

	public function add_meta_box() {
		$settings = incsub_sbe_get_settings();
		$screens = $settings['post_types'];

		foreach ( $screens as $screen ) {
			add_meta_box(
				'sbe-do-not-send',
				__( 'Subscribe By Email', INCSUB_SBE_LANG_DOMAIN ),
				array( $this, 'render' ),
				$screen,
				'side'
			);

		}
	}

	public function render( $post ) {
		wp_nonce_field( 'sbe_do_not_send_save_data', 'sbe_do_not_send_nonce' );

		$value = get_post_meta( $post->ID, '_sbe_do_not_send', true );
		$disabled = disabled( get_post_status( $post->ID ) === 'publish' || get_post_meta( $post->ID, 'sbe_sent', true ), true, false );
		?>
			<input type="checkbox" id="sbe-do-not-send-checkbox" name="sbe-do-not-send" <?php checked( $value ); ?> <?php echo $disabled; ?>>
			<label for="sbe-do-not-send-checkbox"><?php _e( 'Do not send this post', INCSUB_SBE_LANG_DOMAIN ); ?></label><br/>
			<p class="description"><?php _e( 'Check this box if you don\'t want to send this post (once the post is published, you cannot change this option)', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['sbe_do_not_send_nonce'] ) )
			return $post_id;

		$nonce = $_POST['sbe_do_not_send_nonce'];

		if ( ! wp_verify_nonce( $nonce, 'sbe_do_not_send_save_data' ) )
			return $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		$settings = incsub_sbe_get_settings();
		$screens = $settings['post_types'];
		if ( ! in_array( $_POST['post_type'], $screens ) )
			return $post_id;

		if ( ! isset( $_POST['sbe-do-not-send'] ) )
			return $post_id;

		update_post_meta( $post_id, '_sbe_do_not_send', true );
	}
}

new SBE_Do_Not_Send_Meta_Box();

