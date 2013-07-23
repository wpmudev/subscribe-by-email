<?php

class Incsub_Subscribe_By_Email_Manage_Subscription {

	private $settings;

	public function __construct() {
		$this->settings = Incsub_Subscribe_By_Email::$settings;

		add_filter( 'the_content', array( &$this, 'set_the_content' ), 80 );
	}


	public function set_the_content( $content ) {
		$new_content = $content;
		
		if ( ! empty( $this->settings['manage_subs_page'] ) && is_page( $this->settings['manage_subs_page'] ) ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();

			if ( ! isset( $_REQUEST['sub_key'] ) )
				return $new_content;

			$key = $_REQUEST['sub_key'];

			if ( ! $model->is_subscriber( $key ) )
				return $new_content;

			$updated = false;
			if ( ! empty( $_POST['sub_submit'] ) ) {
				$user_settings = $model->get_subscriber_settings( $key );

				if ( isset( $_POST['sub_post_types'] ) && is_array( $_POST['sub_post_types'] ) ) {
					$user_settings['post_types'] = $_POST['sub_post_types'];
				}
				else {
					$user_settings['post_types'] = array();
				}

				$model->update_subscriber_settings( $key, $user_settings );
				$updated = true;
			}

			
			$post_types = $this->get_sbe_post_types();
			$user_post_types = $this->get_user_post_types( $key );

			ob_start();
			?>
				<div id="manage_subscription_wrap">

					<?php if ( $updated ): ?>
						<p><?php _e( 'Settings saved', INCSUB_SBE_LANG_DOMAIN ); ?></p>
					<?php endif; ?>

					<form action="" method="POST">

						<input type="hidden" name="sub_key" value="<?php echo $key; ?>">

						<?php if ( ! empty( $post_types ) ): ?>

							<h3><?php _e( 'Check those post types from what you want to be notified', INCSUB_SBE_LANG_DOMAIN ); ?></h3>

							<?php foreach ( $post_types as $post_type ): ?>
								<label class="sub_post_type_label" for="sub_post_type-<?php echo $post_type['slug']; ?>">
									<input type="checkbox" class="sub_post_types" <?php checked( in_array( $post_type['slug'], $user_post_types ) ); ?> id="sub_post_type-<?php echo $post_type['slug']; ?>" name="sub_post_types[]" value="<?php echo $post_type['slug']; ?>"> 
									<?php echo $post_type['name']; ?>
								</label><br/>
							<?php endforeach; ?>

						<?php endif; ?>

						<p><input type="submit" name="sub_submit" value="<?php _e( 'Submit settings', INCSUB_SBE_LANG_DOMAIN ); ?>"></p>

					</form>
				</div>
			<?php
			$new_content .= ob_get_clean();
		}
		return $new_content;
	}

	public function get_sbe_post_types() {

		$post_types = $this->settings['post_types'];

		$result = array();
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$object = get_post_type_object( $post_type );
				if ( ! empty( $object->labels->name ) )
					$result[] = array( 'slug' => $post_type, 'name' => $object->labels->name );				
			}
		}

		return $result;
	}

	private function get_user_post_types( $key ) {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$user_settings = $model->get_subscriber_settings( $key );

		// If the user has not selected any post type we'll return every of them
		if ( ! $user_settings )
			return $this->settings['post_types'];
		else
			return $user_settings['post_types'];

	}

}

