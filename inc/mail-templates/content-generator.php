<?php

class Incsub_Subscribe_By_Email_Content_Generator {

	private $post_ids;
	private $post_types;
	private $dummy;

	public function __construct( $digest_type, $post_types = array( 'post' ), $dummy = false ) {
		$this->digest_type = $digest_type;
		$this->post_types = $post_types;
		$this->dummy = $dummy;
		$this->content = array();

	}

	public function get_content() {
		
		do_action( 'sbe_content_generator_before_get_content', $this );

		if ( ! empty( $this->post_ids ) && ! $this->dummy ) {

			$model = incsub_sbe_get_model();
			$args = array(
				'post_type' => $this->post_types,
				'include'	=>	$this->post_ids,
			);
			$_post_ids = $model->get_posts_ids( $args );

			$content = array();
			foreach ( $_post_ids as $post_id ) {
				$content[] = get_post( $post_id );
			}

		}
		elseif ( empty( $this->post_ids ) && ! $this->dummy) {

			$days = 1;
			if ( 'daily' == $this->digest_type )
				$days = self::get_last_x_days_sending_time( 1 );

			if ( 'weekly' == $this->digest_type )
				$days = self::get_last_x_days_sending_time( 7 );

			$today_sending_time = self::get_today_sending_time();

			$model = incsub_sbe_get_model();
			$args = array(
				'post_type' => $this->post_types,
				'after_date' => date( 'Y-m-d H:i:s', $days )
			);
			$this->post_ids = $model->get_posts_ids( $args );

			$content = array();
			foreach ( $this->post_ids as $post_id ) {
				$content[] = get_post( $post_id );
			}

		}
		else {
			$content = $this->get_dummy_content();
		}

		$this->content = $content;


		if ( ! $this->dummy ) {
			$this->filter_sent_posts();
			$this->filter_content_by_taxonomies();
		}

		$this->content = apply_filters( 'sbe_get_email_contents', $this->content );

		do_action( 'sbe_content_generator_after_get_content', $this->content );

		return $this->content;
	}

	private function filter_sent_posts() {
		if ( ! empty( $this->content ) ) {
			$content = $this->content;
			foreach( $content as $post_key => $the_post ) {
				$post_id = $the_post->ID;
				$sbe_sent = get_post_meta( $post_id, 'sbe_sent', true );
				if ( $sbe_sent )
					unset( $this->content[ $post_key ] );
			}
		}
	}

	private function filter_content_by_taxonomies() {

		if ( ! empty( $this->content ) ) {
			// Filtering by taxonomies
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
			$settings = incsub_sbe_get_settings();

			$is_content = true;
			foreach( $this->content as $post_key => $the_post ) {
				$post_type_taxonomies = $settings_handler->get_taxonomies_by_post_type( $the_post->post_type );
				
				if ( empty( $post_type_taxonomies ) ) {
					// If the post type does not have any taxonomy
					// then it will be part of the content
					$is_content = true;
					continue;
				}
				
				if ( ! isset( $settings['taxonomies'][ $the_post->post_type ] ) ) {
					$is_content = false;
					unset( $this->content[ $post_key ] );
					continue;
				}

				foreach ( $post_type_taxonomies as $tax_slug => $taxonomy ) {
					if ( ! isset( $settings['taxonomies'][ $the_post->post_type ][ $tax_slug ] ) ) {
						$is_content = false;
						break;
					}

					if ( in_array( 'all', $settings['taxonomies'][ $the_post->post_type ][ $tax_slug ] ) ) {
						$is_content = true;
						break;
					}

					$terms_list = get_the_terms( $the_post, $tax_slug );

					if ( empty( $terms_list ) ) {
						$is_content = false;
						continue;
					}

					foreach ( $terms_list as $term ) {
						if ( ! in_array( $term->term_id, $settings['taxonomies'][ $the_post->post_type ][ $tax_slug ] ) ) {
							$is_content = false;
							continue;
						}
						else {
							$is_content = true;
							break;
						}
					}
				}

				if ( ! $is_content )
					unset( $this->content[ $post_key ] );

			}
			

		}
	}


	public function filter_user_content( $key ) {

		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$_subscriber = $model->get_subscriber_by_key( $key );
		$subscriber = new Subscribe_By_Email_Subscriber( $_subscriber );

		// These are the post types that the user wants to get
		$user_post_types = $subscriber->get_post_types();
		$user_post_types = empty( $user_post_types ) || ! is_array( $user_post_types ) ? $this->post_types : $user_post_types;
		
		$user_content = array();

		// Removing content based on post types
		foreach ( $this->content as $post ) {
			if ( ! in_array( $post->post_type, $user_post_types ) )
				continue;

			$user_content[] = $post;
		}

		$user_content = apply_filters( 'sbe_get_subscriber_email_contents', $user_content, $subscriber->get_subscription_ID() );
		return $user_content;
		
	}


	public function set_posts_ids( $post_ids ) {

		if ( ! is_array( $post_ids ) )
			$ids = array( $post_ids );
		else
			$ids = $post_ids;

		$this->post_ids = $ids;
	}

	public function get_posts_ids() {
		return $this->post_ids;
	}


	public static function get_last_x_days_sending_time( $days ) {
		$today_sending_time = self::get_today_sending_time();
		return strtotime( '-' . $days . ' days', $today_sending_time );
	}

	public static function get_today_sending_time() {
		$settings = incsub_sbe_get_settings();
		$time = str_pad( $settings['time'], 2, '0', STR_PAD_LEFT );
		$current_date = date( 'Y-m-d', current_time( 'timestamp' ) );
		$sending_time = $current_date . ' ' . $time . ':00:00';
		return strtotime( $sending_time );	
	}

	private function get_dummy_content() {
		if ( 'inmediately' == $this->digest_type ) {
			$post_count = 1;
		}
		else {
			$post_count = 3;
		}

		$posts = array();

		for ( $i = 0; $i < $post_count; $i++ ) {
			$post = new stdClass();

			$post->ID = 0;
			$post->post_author = get_current_user_id();
			$post->post_name = 'lorem-ipsum';
			$post->post_title = 'Lorem Ipsum';
			$post->post_date	= current_time( 'mysql' );
			$post->post_date_gmt = current_time( 'mysql', 1 );
			$post->post_content = $this->generate_lorem_content();
			$post->post_status = 'publish';
			
			$posts[] = $post;
		}

		return $posts;

	}

	/**
	 * Some emails will be cropped if the content is exactly the same.
	 * Each time we send a test email we'll pouplate it with different content
	 * 
	 * @return String
	 */
	private function generate_lorem_content() {
		$rand = rand( 1, 6 );
		$text = '';
		switch ( $rand ) {
			case 1:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Necessitatibus, quisquam delectus minima. Autem, dolore commodi nesciunt nostrum praesentium eius molestiae magnam nemo minus accusamus quis excepturi iure natus maxime deleniti nisi repudiandae odit magni qui numquam omnis facere veritatis aliquam illum optio ipsam tenetur. Est, modi, obcaecati soluta recusandae exercitationem ducimus adipisci nobis ipsum magni mollitia saepe fugiat. Illo, quasi, iure id magni quia quis dignissimos distinctio odio pariatur ducimus consequatur culpa nihil repellat vero debitis voluptate totam amet? Non, excepturi sit dignissimos animi sint aliquam iste harum alias et sequi. Placeat natus odit nobis deserunt distinctio unde sapiente ipsam!';
				break;
			case 2:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aspernatur, quisquam, quod. Quo, architecto, beatae? Sit, sequi sed quibusdam sunt deserunt ipsa rerum. Nulla, sit, similique cumque totam odio sapiente voluptates molestiae minus incidunt nesciunt quos iusto unde culpa amet aliquid voluptas maxime maiores dolorem natus excepturi molestias consequuntur accusamus voluptatum nam minima facilis ducimus architecto recusandae quasi suscipit enim consequatur beatae repellat temporibus tempora quidem ad quaerat cupiditate distinctio eveniet? Numquam, facilis, ex quas a suscipit sapiente ipsum asperiores impedit commodi quidem quasi sed itaque provident molestias vero architecto praesentium repudiandae ipsa sunt ab enim laborum cupiditate unde totam quos!';
				break;
			case 3:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aperiam ex a cum magni quod. Animi, quia, nulla, consequatur modi dicta consectetur rerum vitae et quidem est reiciendis fugit exercitationem facilis totam saepe. Eligendi necessitatibus ipsam doloribus error inventore aspernatur quos dolores quo. Dicta, cum, veniam, ea at id odio aliquid libero inventore odit tenetur dolores molestiae minima officiis adipisci impedit reprehenderit quos debitis velit possimus doloribus est numquam ad repellat modi non nam ut dolore voluptatibus iste saepe obcaecati perspiciatis amet ducimus. Reiciendis, cupiditate ratione sit vero error repudiandae quibusdam illum harum repellendus accusantium iste veritatis cum totam quam dolorem.';
				break;
			case 4:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ipsum, ut, cupiditate, tempore reiciendis ullam illo dolorum minus modi eum suscipit possimus voluptatem quo saepe sed expedita odit repudiandae totam velit quos impedit quia. Dolores, alias consequatur id deleniti enim ipsa qui iusto odit eligendi unde. Facilis, nulla, explicabo, architecto, laborum neque ad expedita ea porro deleniti vel rerum fugiat voluptate asperiores a doloribus est minus adipisci distinctio maiores modi. Laborum, voluptas, aliquid, eveniet consectetur voluptatibus delectus beatae sunt illo distinctio molestiae accusantium ad perferendis voluptates molestias repellat et neque nobis suscipit autem debitis nam! Dignissimos, in odio ipsam corrupti. Perferendis.';
				break;
			case 5:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Rem, atque ipsum consectetur numquam fugiat corporis ipsa ex odit qui. Amet, nihil, omnis, neque, harum eveniet velit expedita qui ut eum aspernatur incidunt tempora illum dicta quidem cumque reiciendis consequuntur. Alias, quae, in inventore fuga eos quasi minima explicabo atque harum blanditiis! Fugiat aliquam ipsum iste eaque dignissimos sunt amet autem veniam. Asperiores, optio suscipit laborum tenetur ea eligendi dolore sit? Dolore, repellat, officiis quam facere error nam dolorem quia quibusdam aperiam odio eveniet aspernatur accusamus commodi numquam impedit sunt facilis quasi nemo nesciunt enim id? Excepturi, eveniet dolores inventore culpa.';
				break;
			case 6:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Fuga, at, ipsa, error, animi velit omnis aspernatur labore suscipit repudiandae maxime accusamus non consequatur iste veniam cum aperiam sapiente? Quaerat, repellat vero adipisci dolorem fuga aut dicta debitis nihil sequi itaque odit veritatis molestiae rerum doloribus impedit reiciendis iure autem voluptatem eaque amet consectetur minus a est sapiente velit. Eos, ipsam, saepe praesentium eligendi architecto dicta soluta dolorum quo ad necessitatibus fugit explicabo in laboriosam excepturi aliquam aut deserunt hic optio eaque placeat modi cum adipisci ex voluptate quos nam cupiditate dolor ut dolores sunt accusamus obcaecati minus inventore commodi officiis.';
				break;
		}

		return $text;
	}

}