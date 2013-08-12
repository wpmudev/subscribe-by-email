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
			$user_taxonomies = $this->get_user_taxonomies( $key );
			
			$post_ids = array(1, 2, 4, 5, 6, 7, 19, 21, 25, 27, 28, 29, 34, 36, 38, 40, 42, 44, 45, 50, 53, 146, 172, 173, 174, 534, 535, 536, 543, 611, 613, 616, 617, 618, 619, 733, 734, 764, 771, 785, 789, 792, 801, 802, 807, 808, 865, 875, 877, 884, 890, 931, 932, 934, 935, 938, 939, 941, 942, 944, 946, 952, 977, 988, 991, 997, 998, 999, 1040, 1041, 1043, 1047, 1048, 1049, 1050, 1051, 1052, 1053, 1054, 1055, 1056, 1057, 1058, 1059, 1060, 1061, 1062, 1063, 1064, 1065, 1066, 1067, 1068, 800, 11, 13, 767, 15, 16, 18, 20, 8, 9, 10, 12, 14, 17, 24, 763, 30, 31, 33, 35, 48, 37, 39, 41, 43, 49, 52, 874, 46, 51, 924, 925, 54, 55, 803, 804, 805, 806, 876, 891, 155, 156, 501, 22, 765, 26, 766, 869, 870, 47, 786, 787, 790, 796, 797, 798, 799, 887, 888, 889, 788, 793, 794, 795, 809, 810, 811, 812, 813, 814, 815, 816, 817, 818, 819, 820, 821, 822, 823, 824, 825, 826, 827, 828, 829, 830, 831, 832, 833, 834, 835, 838, 839, 840, 841, 842, 843, 844, 845, 846, 847, 848, 849, 850, 851, 852, 853, 854, 855, 856, 857, 858, 859, 860, 861, 862, 863, 866, 867, 871, 872, 878, 879, 880, 881, 882, 883, 914, 915, 916, 917, 885, 886, 912, 913, 918, 919, 920, 921, 922, 923, 926, 927, 929, 930, 1005, 936, 937, 981, 982, 983, 984, 985, 986, 987, 1000, 1001, 1002, 1030, 1031, 940, 980, 1003, 1006, 1007, 1008, 1009, 1010, 1011, 1012, 1013, 1014, 1015, 1016, 1017, 1018, 1019, 1020, 1022, 1023, 1024, 1025, 1026, 1027, 1028, 1029, 1032, 1033, 1034, 1035, 1036, 1037, 1038, 1039, 947, 948, 949, 950, 951, 978, 979, 1004, 966, 967, 968, 969, 970, 971, 972, 973, 974, 975, 976, 989, 990, 992, 993, 994, 995, 1042, 1044, 1045, 1046, 1069);
			$content = get_posts(  
				array(
					'numberposts'		=>	count( $post_ids ),
					'offset'			=>	0,
					'orderby'			=>	'post_date',
					'order'				=>	'DESC',
					'include'			=>	$post_ids,
					'post_type'			=>	$this->settings['post_types'],
					'post_status'		=>	'publish' 
				)
			);

			do_dump(count($content));

			$user_settings = $model->get_subscriber_settings( $key );

			// These are the post types that the user wants to get
			$user_post_types = ! $user_settings ? $this->settings['post_types'] : $user_settings['post_types'];
			$user_taxonomies = ! $user_settings || ! isset( $user_settings['taxonomies'] ) ? $this->settings['taxonomies'] : $user_settings['taxonomies'];

			$user_content = array();
			foreach ( $content as $post ) {
				if ( ! in_array( $post->post_type, $user_post_types ) )
					continue;

				$post_type_taxonomies = $settings_handler->get_taxonomies_by_post_type( $post->post_type );

				$has_term = false;
				foreach ( $post_type_taxonomies as $tax_slug => $taxonomy ) {
					$terms_list = get_the_terms( $post->ID, $tax_slug );	

					if ( empty( $terms_list ) ) {
						break;
					}
					
					foreach ( $terms_list as $term ) {
						if ( empty( $user_taxonomies[ $post->post_type ][$term->slug] ) ) {
							$has_term = true;
							break;
						}
						if ( in_array( $term->term_id, $user_taxonomies[ $post->post_type ][$term->slug] ) ) {
							$has_term = true;
							break;
						}

					}
				}
				
				if ( $has_term )
					$user_content[] = $post;
			}

			do_dump($user_content);

			
			ob_start();
			?>
				<div id="manage_subscription_wrap">

					<?php if ( $updated ): ?>
						<p><?php _e( 'Settings saved', INCSUB_SBE_LANG_DOMAIN ); ?></p>
					<?php endif; ?>

					<form action="" method="POST">

						<input type="hidden" name="sub_key" value="<?php echo $key; ?>">

						<?php if ( ! empty( $post_types ) ): ?>

							<h3><?php _e( 'Please select which post types you wish to be notified about.', INCSUB_SBE_LANG_DOMAIN ); ?></h3>

							<?php foreach ( $post_types as $post_type ): ?>
								<label class="sub_post_type_label" for="sub_post_type-<?php echo $post_type['slug']; ?>">
									<input type="checkbox" class="sub_post_types" <?php checked( in_array( $post_type['slug'], $user_post_types ) ); ?> id="sub_post_type-<?php echo $post_type['slug']; ?>" name="sub_post_types[]" value="<?php echo $post_type['slug']; ?>"> 
									<?php echo $post_type['name']; ?>
								</label>
								<?php 
									
									$taxonomies = $settings_handler->get_taxonomies_by_post_type( $post_type['slug'] );
									$walker = new Walker_SBE_Terms_Checklist;

									foreach( $taxonomies as $taxonomy_slug => $taxonomy ) {

										if ( ! isset( $user_taxonomies[ $post_type['slug'] ][ $taxonomy_slug ] ) )
											$selected_cats = 'select-all';
										else
											$selected_cats = $user_taxonomies[ $post_type['slug'] ][ $taxonomy_slug ];

										echo '<div class="taxonomies-box"><ul class="taxonomies-list">';
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
												'checked_ontop' => false
											) 
										); 
										echo '</ul></div><div style="clear:both;"></div>';
									}
									
									
									
								?>
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

	private function get_user_taxonomies( $key ) {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$user_settings = $model->get_subscriber_settings( $key );

		// If the user has not selected any post type we'll return every of them
		if ( ! isset( $user_settings['taxonomies'] ) )
			return false;
		else
			return $user_settings['taxonomies'];

	}

}

