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

		if ( ! empty( $this->post_ids ) && ! $this->dummy ) {
			$content = get_posts(  
				array(
					'numberposts'		=>	count( $this->post_ids ),
					'offset'			=>	0,
					'orderby'			=>	'post_date',
					'order'				=>	'DESC',
					'include'			=>	$this->post_ids,
					'post_type'			=>	$this->post_types,
					'post_status'		=>	'publish',
					'ignore_sticky_posts' => 1
				)
			);
		}
		elseif ( empty( $this->post_ids ) && ! $this->dummy) {
			add_filter( 'posts_where', array( &$this, 'set_wp_query_filter' ) );
			$query = new WP_Query(
				array(
					'post_type' => $this->post_types,
					'nopaging ' => true,
					'posts_per_page' => -1,
					'post_status' => array( 'publish' )
				)
			);

			$content = $query->posts;
			remove_filter( 'posts_where', array( &$this, 'set_wp_query_filter' ) );
		}
		else {
			$content = $this->get_dummy_content();
		}

		$this->content = $content;

		if ( ! $this->dummy )
			$this->filter_content_by_taxonomies();

		return $this->content;
	}

	private function filter_content_by_taxonomies() {

		if ( ! empty( $this->content ) ) {
			// Filtering by taxonomies
			$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
			$settings = incsub_sbe_get_settings();

			$is_content = true;
			foreach( $this->content as $post_key => $the_post ) {
				$post_type_taxonomies = $settings_handler->get_taxonomies_by_post_type( $the_post->post_type );
				
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
		$user_settings = $model->get_subscriber_settings( $key );

		// These are the post types that the user wants to get
		$user_post_types = ! $user_settings ? $this->post_types : $user_settings['post_types'];

		$user_content = array();

		// Removing content based on post types
		foreach ( $this->content as $post ) {
			if ( ! in_array( $post->post_type, $user_post_types ) )
				continue;

			$user_content[] = $post;
		}

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

	/**
	 * Sets the filter for WP_Query depending on the frequency
	 * 
	 * @param String $where Current Where sentence
	 * 
	 * @return String new WHERE sentence
	 */
	public function set_wp_query_filter( $where = '' ) {

		$days = 1;
		if ( 'daily' == $this->digest_type )
			$days = $this->get_last_x_days_time( 1 );

		if ( 'weekly' == $this->digest_type )
			$days = $this->get_last_x_days_time( 7 );

		$where .= " AND post_date > '" . date( 'Y-m-d H:i:s', $days ) . "'";

		return $where;
	}

	private function get_last_x_days_time( $days ) {
		return strtotime( '-' . $days . ' days', current_time( 'timestamp' ) );
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

?>





