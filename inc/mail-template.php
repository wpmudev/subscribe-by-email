<?php


class Incsub_Subscribe_By_Email_Template {

	private $settings;
	private $dummy;

	// Results of the content to be templated
	private $content;

	// The user can send a list of posts to be send
	private $post_ids;

	public function __construct( $settings, $dummy = false ) {
		$this->settings = $settings;
		$this->dummy = $dummy;
		$this->posts_ids = array();
		$this->content = array();
		$this->subject = $this->settings['subject'];
	}

	/**
	 * Render the content
	 */
	public function the_content( $user_content ) {

		$text_float = ( $this->settings['featured_image'] ) ? 'style="float:left;width: 394px;"' : '';
		$meta_float = ( $this->settings['featured_image'] ) ? 'float:right;' : 'float:none;';

		$title_style = 'style="font-weight: 500; font-size: 21px;line-height: 30px; margin-top:25px; margin-bottom: 10px;"';
		$text_style = 'style="margin:1em 0;font-size: 13px;color:#000 !important;line-height: 23px;"';
		$link_style = 'style="font-size: 15px;color:#21759B !important"';
		$meta_style = 'style="margin:0em 0 2.2em 0;font-size: 13px;color:#9E9E9E !important;' . $meta_float . '"';
		$featured_image_style = 'max-width:150px;box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);float:left;background:#FFFFFF;border:1px solid #DEDEDE;padding:4px;margin:0 10px 10px 0;';
		$featured_image_style_dummy = 'background:#DEDEDE !important;width:150px;height:100px;box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);float:left;border:1px solid #DEDEDE;padding:4px;margin:0 10px 10px 0;';

		$date_format = get_option( 'date_format', get_site_option( 'date_format', 'Y-m-d' ) );
		$date_format = ( empty( $date_format ) ) ? 'Y-m-d' : $date_format;

		if ( $this->dummy ) {
			if ( 'inmediately' == $this->settings['frequency'] ) {
				$this->subject = 'Lorem Ipsum';
				$post_count = 1;
			}
			else {
				$this->subject = 'Lorem Ipsum; Lorem Ipsum; Lorem Ipsum;';
				$post_count = 3;
			}



			for ( $i = 1; $i <= $post_count; $i++ ) {
				$rand = rand( 200, 300 );
				?>
					<?php if ( $this->settings['featured_image'] ): ?>
						<div style="<?php echo $featured_image_style_dummy; ?>"></div>
					<?php endif; ?>
					<div <?php echo $text_float; ?>>
						<h3 style="margin-top:0;"><a <?php echo $title_style; ?> href="#" target="_blank">Lorem Ipsum</a></h3>
						<p <?php echo $text_style; ?>>
							<?php echo $this->generate_lorem(); ?> <a <?php echo $link_style; ?> href="#">Continue reading...</a>
						</p>
					</div>
					<div style="clear:both;"></div>
					<div <?php echo $meta_style; ?>>
						<?php printf( __( 'by %s on %s' ), 'author', date_i18n( $date_format ) ); ?>
					</div>
					<div style="clear:both;"></div>
				<?php
			}
		}
		else {

			if ( ! empty( $user_content ) ) {

				// We need this global or the_title(), the_excerpt... willl not work properly
				global $post;

				add_filter( 'excerpt_more', array( &$this, 'set_excerpt_more' ), 80 );
				add_filter( 'excerpt_length', array( &$this, 'set_excerpt_length' ), 80 );

				foreach ( $user_content as $content_post ):

					$post = $content_post;

					// Setup a post data as if we were inside the Loop
					setup_postdata( $post );
					?>
						<?php if ( $this->settings['featured_image'] && has_post_thumbnail() ): ?>
							<?php the_post_thumbnail( 'thumbnail', $attr = array( 'style' => $featured_image_style ) ); ?>
						<?php endif; ?>
						<div <?php echo $text_float; ?>>
							<h3 style="margin-top:0;"><a <?php echo $title_style; ?> href="<?php the_permalink(); ?>" target="_blank"><?php the_title(); ?></a></h3>
							<div <?php echo $text_style; ?>>
								<?php the_excerpt(); ?>
							</div>
						</div>
						<div style="clear:both;"></div>
						<div <?php echo $meta_style; ?>>
							<?php printf( __( 'by %s on %s' ), get_the_author(), get_the_date( $date_format ) ); ?>
						</div>
						<div style="clear:both;"></div>
					<?php
				endforeach;
				remove_filter( 'excerpt_more', array( &$this, 'set_excerpt_more' ), 80 );
				remove_filter( 'excerpt_length', array( &$this, 'set_excerpt_length' ), 80 );
			}

			// Just in case...
			wp_reset_postdata();

		}
	}

	/**
	 * Sets the excerpt more link
	 *
	 * @param String $more Current more
	 *
	 * @return String new more
	 */
	public function set_excerpt_length( $length ) {
		return 25;
	}

	/**
	 * Sets the excerpt length
	 *
	 * @param Integer $length Current length
	 *
	 * @return Integer new length
	 */
	public function set_excerpt_more( $more ) {
		return ' <a href="'. get_permalink( get_the_ID() ) . '">' . __( 'Read more...', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
	}

	public function set_subject( $user_content ) {

		if ( $this->dummy && strpos( $this->subject, '%title%' ) > -1 ) {
			if ( 'inmediately' == $this->settings['frequency'] )
				$this->subject = str_replace( '%title%', 'Lorem Ipsum', $this->subject );
			else
				$this->subject = str_replace( '%title%', 'Lorem Ipsum; Lorem Ipsum; Lorem Ipsum', $this->subject );
		}
		elseif ( ! $this->dummy && ! empty( $user_content ) ) {

			$titles = array();
			foreach ( $user_content as $content_post ) {
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
	 * The user can set the content manually
	 *
	 * @param Integer/Array $posts_ids List of posts IDs or just an integer
	 */
	public function set_posts( $posts_ids ) {

		if ( is_integer( $posts_ids ) ) {
			$this->posts_ids = array( $posts_ids );
		}
		elseif ( is_array( $posts_ids ) ) {
			$this->posts_ids = $posts_ids;
		}

		$this->content = get_posts(
			array(
				'numberposts'		=>	count( $this->posts_ids ),
				'offset'			=>	0,
				'orderby'			=>	'post_date',
				'order'				=>	'DESC',
				'include'			=>	$this->posts_ids,
				'post_type'			=>	$this->settings['post_types'],
				'post_status'		=>	'publish'
			)
		);

	}

	/**
	 * Set the contents depending on the frequency
	 *
	 * @return WP_Query Object
	 */
	private function set_content() {
		add_filter( 'posts_where', array( &$this, 'set_wp_query_filter' ) );
		$query = new WP_Query(
			array(
				'post_type' => $this->settings['post_types'],
				'nopaging ' => true,
				'posts_per_page' => -1,
				'post_status' => array( 'publish' )
			)
		);
		$this->content = $query->posts;
		remove_filter( 'posts_where', array( &$this, 'set_wp_query_filter' ) );

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
		if ( 'daily' == $this->settings['frequency'] )
			$days = $this->get_last_x_days_time( 1 );

		if ( 'weekly' == $this->settings['frequency'] )
			$days = $this->get_last_x_days_time( 7 );

		$where .= " AND post_date > '" . date( 'Y-m-d', $days ) . "'";

		return $where;
	}

	private function get_last_x_days_time( $days ) {
		return strtotime( '-' . $days . ' days' );
	}


	/**
	 * Render the mail template
	 *
	 * @param Boolean if the content must be returned or echoed
	 *
	 * @return String
	 */

	public function render_mail_template( $user_content = array(), $echo = true, $key = '' ) {

		$this->set_subject( $user_content );

		$font_style = "style=\"font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif !important;\"";
		$table_style = 'style="width: 100%;"';
		$column_style = 'style="display: block!important; max-width: 600px!important; margin: 0 auto!important; clear: both!important;"';
		$column_wrap_style = 'style="padding: 15px; max-width: 600px; margin: 0 auto; display: block;"';
		$blogname_style = 'style="text-decoration:none !important; margin: 0!important; padding:0;font-weight: 900; font-size: 14px; text-transform: uppercase; color: ' . $this->settings['header_text_color'] . ' !important;"';	 	    	  	    			
		$subject_style = 'style="font-weight: 500; font-size: 27px;line-height: 1.1; margin-bottom: 15px; color: #000 !important;"';
		$lead_style = 'style="font-size: 17px;margin-bottom: 10px; font-weight: normal; font-size: 14px; line-height: 1.6;"';
		$footer_style = 'style="font-size:11px;color:#666 !important;"';

		if ( ! $echo )
			ob_start();

		?>
		<div <?php echo $font_style; ?>>
				<table <?php echo $table_style; ?> bgcolor="<?php echo $this->settings['header_color']; ?>">
					<tbody>
						<tr>
							<td></td>
							<td <?php echo $column_style; ?>>
								<div <?php echo $column_wrap_style; ?>>
									<table <?php echo $table_style; ?> bgcolor="<?php echo $this->settings['header_color']; ?>">
										<tbody>
											<tr>
												<td><a href="<?php echo get_home_url(); ?>"><img style="max-width:200px;" src="<?php echo $this->settings['logo']; ?>"></a></td>
												<td align="right">
													<h6><a <?php echo $blogname_style; ?> href="<?php echo get_home_url(); ?>"><?php echo $this->settings['from_sender']; ?></a></h6>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</td>
							<td></td>
						</tr>
					</tbody>
				</table><!-- /HEADER -->
				<table <?php echo $table_style; ?>>
					<tbody>
						<tr>
							<td></td>
							<td <?php echo $column_style; ?> bgcolor="#FFFFFF">
								<div <?php echo $column_wrap_style; ?>>
									<table <?php echo $table_style; ?>>
										<tbody>
											<tr>
												<td>
													<h2 <?php echo $subject_style; ?>><?php echo $this->subject; ?></h2>
													<p <?php echo $lead_style; ?>><?php echo wpautop( $this->settings['header_text'] ); ?></p>
													<hr/>
													<?php $this->the_content( $user_content ); ?>
												</td>
											</tr>
										</tbody>
									</table>
								</div><!-- /content -->
							</td>
							<td></td>
						</tr>
					</tbody>
				</table>
				<table <?php echo $table_style; ?>>
					<tbody>
						<tr>
							<td></td>
							<td <?php echo $column_style; ?> bgcolor="#EFEFEF">
								<div <?php echo $column_wrap_style; ?>>
									<table <?php echo $table_style; ?>>
										<tbody>
											<tr>
												<td <?php echo $footer_style; ?>>
													<p>
														<?php printf( __( 'You are subscribed to email updates from <a href="%s">%s</a>', INCSUB_SBE_LANG_DOMAIN ), get_home_url(), get_bloginfo( 'name' ) ); ?>  <br/>
														<?php if ( $this->settings['manage_subs_page'] ): ?>
															<?php printf( __( 'To manage your subscriptions, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( add_query_arg( 'sub_key', $key, get_permalink( $this->settings['manage_subs_page'] ) ) ) ); ?> <br/>
														<?php endif; ?>
														<?php printf( __( 'To stop receiving these emails, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( add_query_arg( 'sbe_unsubscribe', $key, get_home_url() ) ) ); ?>
													</p>
													<p><?php echo wpautop( $this->settings['footer_text'] ); ?></p>
												</td>
											</tr>
										</tbody>
									</table>
								</div><!-- /content -->
							</td>
							<td></td>
						</tr>
					</tbody>
				</table>
			</div>

		<?php

		if ( ! $echo )
			return ob_get_clean();
	}


	/**
	 * Some emails will be cropped if the content is exactly the same.
	 * Each time we send a test email we'll pouplate it with different content
	 *
	 * @return String
	 */
	private function generate_lorem() {
		$rand = rand( 1, 6 );
		$text = '';
		switch ( $rand ) {
			case 1:
				$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Nulla, adipisci, eius, maxime ullam odio velit eos libero dignissimos a blanditiis nobis ducimus esse ut necessitatibus.';
				break;
			case 2:
				$text = 'Alias, ab, veritatis, impedit non vitae maiores aperiam laboriosam commodi sapiente vel tempora aut debitis neque dolores dolorem. Dolore, dolorum, eos aut quis repellendus repudiandae.';
				break;
			case 3:
				$text = 'Quam, voluptatibus, labore deleniti quas illo perspiciatis modi illum qui ipsam accusantium necessitatibus doloremque praesentium rem quae odio iste reprehenderit! Harum, natus.';
				break;
			case 4:
				$text = 'Eveniet, necessitatibus beatae provident mollitia molestias quos tempore velit quibusdam itaque repellat nihil natus distinctio iure error delectus omnis nisi eligendi accusamus.';
				break;
			case 5:
				$text = 'Vero, cumque, mollitia fuga quia harum maxime ut placeat ipsam ullam repellendus aspernatur odio. Architecto, tempora, voluptas, magni, facere deleniti dolorum nihil laborum.';
				break;
			case 6:
				$text = 'Fuga, error dicta architecto alias corporis aperiam. Id, quidem, ea, laborum veniam numquam alias magni est quaerat a molestias quos voluptas debitis quia beatae sunt et sapiente.';
				break;
		}

		return $text;

	}


	/**
	 * Send the mail based on the template
	 *
	 * @param Array $to List of emails
	 * @param Integer $log_id If we have to continue sending mails from a log that did not finish last time
	 */
	public function send_mail( $to, $log_id = false ) {

		$to = ( ! $to ) ? array() : $to;

		if ( is_string( $to ) )
			$to = array( 0 => array( 'email' => $to ) );

		if ( $log_id )
			$mail_log_id = absint( $log_id );

		add_filter( 'wp_mail_content_type', array( &$this, 'set_html_content_type' ) );
		add_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );

		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$mails_sent = 0;

		if ( ! $this->dummy && empty( $this->posts_ids ) )
			$this->set_content();

		// We are going to try to send the mail to all subscribers
		$sent_to_all_subscribers = true;
		foreach ( $to as $mail ) {

			$key = $model->get_user_key( $mail['email'] );
			if ( empty( $key ) && ! $this->dummy )
				continue;
			elseif ( $this->dummy )
				$key = '';

			if ( ! $this->dummy ) {
				// The user may not want to get some types of posts
				$user_content = $this->remove_user_content( $key );

				if ( empty( $user_content ) )
					continue;
			}
			else {
				$user_content = array();
			}


			$content = $this->render_mail_template( $user_content, false, $key );

			if ( ! $this->dummy ) {
				wp_mail( $mail['email'], $this->subject, $content );

				// Creating a new log or incrementiung an existing one
				if ( $mails_sent == 0 && ! isset( $mail_log_id ) )
					$mail_log_id = $model->add_new_mail_log( $this->subject );
				else
					$model->increment_mail_log( $mail_log_id );

				$mails_sent++;

				if ( $mails_sent == absint( $this->settings['mails_batch_size'] ) ) {

					// We could not send the mail to all subscribers
					$sent_to_all_subscribers = false;

					// Now saving the data to send the rest of the mails later
					$mail_settings = array(
						'email_from' => $mail['id'],
						'posts_ids' => $this->posts_ids
					);

					$mail_settings = maybe_serialize( $mail_settings );

					$model->set_mail_log_settings( $mail_log_id, $mail_settings );

					set_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug, 'next', Incsub_Subscribe_By_Email::$time_between_batches );

					// We'll finish with this later
					break;
				}
			}
			else {
				wp_mail( $mail['email'], $this->subject, $content );
			}
		}

		// If we have sent the mail to all subscribers we won't need the settings in that log for the future
		if ( $sent_to_all_subscribers && isset( $mail_log_id ) ) {
			$model->clear_mail_log_settings( $mail_log_id );
		}


		remove_filter( 'wp_mail_content_type', array( &$this, 'set_html_content_type' ) );
		remove_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );

	}

	private function remove_user_content( $key ) {
		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$user_settings = $model->get_subscriber_settings( $key );

		// These are the post types that the user wants to get
		$user_post_types = ! $user_settings ? $this->settings['post_types'] : $user_settings['post_types'];

		$user_content = array();
		foreach ( $this->content as $post ) {
			if ( ! in_array( $post->post_type, $user_post_types ) )
				continue;

			$user_content[] = $post;
		}

		return $user_content;

	}


	/*************************/
	/*		  HEADERS        */
	/*************************/
	public function set_html_content_type() {
		return 'text/html';
	}


	function set_mail_from( $content_type ) {
	  return $this->settings['from_email'];
	}

	function set_mail_from_name( $name ) {
	  return $this->settings['from_sender'];
	}



}