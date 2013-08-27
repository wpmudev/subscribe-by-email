<?php

class Incsub_Subscribe_By_Email_Manage_Subscription {

	private $settings;

	public function __construct() {
		$this->settings = incsub_sbe_get_settings();

		add_filter( 'the_content', array( &$this, 'set_the_content' ), 80 );

		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		if ( ! empty( $this->settings['manage_subs_page'] ) && is_page( $this->settings['manage_subs_page'] ) ) {
			wp_enqueue_style( 'manage-subscriptions-css', INCSUB_SBE_ASSETS_URL . '/css/manage-subscriptions.css' );
		}
	}

	public function set_the_content( $content ) {
		$new_content = $content;
		
		if ( ! empty( $this->settings['manage_subs_page'] ) && is_page( $this->settings['manage_subs_page'] ) ) {
			$model = Incsub_Subscribe_By_Email_Model::get_instance();
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();

			if ( ! isset( $_REQUEST['sub_key'] ) ) {
				ob_start();
				?>
					<div id="manage_subscription_wrap">
						<h4><?php _e( 'This page will show details of your email subscriptions. To see the options available to you, click the link in any newsletter email you have received from us', INCSUB_SBE_LANG_DOMAIN ); ?></h4>
					</div>
				<?php
				return $new_content . ob_get_clean();
			}

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

				if ( isset( $_POST['tax_input'] ) && is_array( $_POST['tax_input'] ) ) {
					$taxonomies_list = $settings_handler->get_taxonomies();
					$user_settings['taxonomies'] = array();
					foreach( $taxonomies_list as $post_type_slug => $taxonomies ) {
						foreach( $taxonomies as $tax_slug => $taxonomy ) {
							if ( ! in_array( $post_type_slug, $user_settings['post_types'] ) ) {
								$user_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array();
							}
							elseif ( ! isset( $_POST['tax_input'][ $post_type_slug ][ $tax_slug ] ) ) {
								$user_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = array();
							}
							else {
								$user_settings['taxonomies'][ $post_type_slug ][ $tax_slug ] = $_POST['tax_input'][ $post_type_slug ][ $tax_slug ];
							}
						}
					}
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

							<h3><?php 
								if ( ! $this->settings['allow_categories'] )
									_e( 'Please select which post types you wish to be notified about.', INCSUB_SBE_LANG_DOMAIN ); 
								else
									_e( 'Please select which post types and categories you wish to be notified about.', INCSUB_SBE_LANG_DOMAIN ); 
							?></h3>

							<?php foreach ( $post_types as $post_type ): ?>
								<div class="post-type-box">
									<label class="sub_post_type_label" for="sub_post_type-<?php echo $post_type['slug']; ?>">
										<input type="checkbox" class="sub_post_types" <?php checked( in_array( $post_type['slug'], $user_post_types ) ); ?> id="sub_post_type-<?php echo $post_type['slug']; ?>" name="sub_post_types[]" value="<?php echo $post_type['slug']; ?>"> 
										<?php echo $post_type['name']; ?>
									</label><br/>
									<?php 
										
										if ( $this->settings['allow_categories'] ) {
											$taxonomies = $settings_handler->get_taxonomies_by_post_type( $post_type['slug'] );

											$walker = new Walker_SBE_Terms_Checklist;

											foreach( $taxonomies as $taxonomy_slug => $taxonomy ) {

												if ( ! isset( $user_taxonomies[ $post_type['slug'] ][ $taxonomy_slug ] ) )
													$selected_cats = 'select-all';
												else
													$selected_cats = $user_taxonomies[ $post_type['slug'] ][ $taxonomy_slug ];

												echo '<div class="taxonomies-box"><ul class="taxonomies-list">';
												$tax_in = in_array( 'all', $this->settings['taxonomies'][ $post_type['slug'] ][ $taxonomy_slug ] ) ? 'all' : $this->settings['taxonomies'][ $post_type['slug'] ][ $taxonomy_slug ];

												sbe_terms_checklist( 
													0,
													array( 
														'taxonomy' => $taxonomy_slug,
														'walker' => $walker,
														'disabled' => false,
														'taxonomy_slug' => $taxonomy_slug,
														'post_type_slug' => $post_type['slug'],
														'selected_cats' => $selected_cats,
														'indent' => false,
														'checked_ontop' => false,
														'tax_in' => $tax_in
													) 
												); 
												echo '</ul><div style="clear:both;"></div></div>';
											}
										}
									?>
								</div>
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

