<?php


class Incsub_Subscribe_By_Email_Template {

	private $settings;
	private $dummy;

	// Results of the content to be templated
	private $content;

	// The user can send a list of posts to be send
	private $post_ids;

	private $content_generator;

	private $logger = null;

	public function __construct( $settings, $dummy = false ) {
		$this->settings = $settings;
		$this->dummy = $dummy;
		$this->subject = $this->settings['subject'];

		require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/mail-templates/content-generator.php' );
		$this->content_generator = new Incsub_Subscribe_By_Email_Content_Generator( $this->settings['frequency'], $this->settings['post_types'], $this->dummy );
		if ( ! empty( $settings['post_ids'] ) ) {
			$this->content_generator->set_posts_ids( $settings['post_ids'] );
		}

		$this->set_content();
	}

	/**
	 * Render the content
	 */
	public function the_content( $content ) {

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

		if ( ! empty( $content ) ) {

			// We need this global or the_title(), the_excerpt... willl not work properly
			global $post;

			add_filter( 'excerpt_more', array( &$this, 'set_excerpt_more' ), 80 );
			add_filter( 'excerpt_length', array( &$this, 'set_excerpt_length' ), 80 );
			remove_all_filters( 'the_content' );
			add_filter( 'the_content', 'wptexturize'        );
			add_filter( 'the_content', 'convert_smilies'    );
			add_filter( 'the_content', 'convert_chars'      );
			add_filter( 'the_content', 'wpautop'            );
			add_filter( 'the_content', 'shortcode_unautop'  );
			add_filter( 'the_content', 'prepend_attachment' );

			foreach ( $content as $content_post ):
				
				$post = $content_post;
				
				// Setup a post data as if we were inside the Loop
				setup_postdata( $post );

				$permalink = ! $this->dummy ? get_permalink() : '#';
				$title = ! $this->dummy ? get_the_title() : 'Lorem Ipsum';
				?>
					<?php if ( ! $this->dummy && $this->settings['featured_image'] && has_post_thumbnail() ): ?>
						<?php the_post_thumbnail( 'thumbnail', $attr = array( 'style' => $featured_image_style ) ); ?>
					<?php elseif ( $this->dummy && $this->settings['featured_image'] ): ?>
						<div style="<?php echo $featured_image_style_dummy; ?>"></div>
					<?php endif; ?>
					<div <?php echo $text_float; ?>>
						<h3 style="margin-top:0;"><a <?php echo $title_style; ?> href="<?php echo $permalink; ?>" target="_blank"><?php echo $title; ?></a> </h3>
						<div <?php echo $text_style; ?>>
							<?php if ( $this->settings['send_full_post'] ): ?>
								<?php the_content(); ?>
							<?php else: ?>
								<?php the_excerpt(); ?>
							<?php endif; ?>
						</div>
					</div>
					<div style="clear:both;"></div>
					<div <?php echo $meta_style; ?>>
						<?php printf( __( 'by %s on %s', INCSUB_SBE_LANG_DOMAIN ), get_the_author(), get_the_date( $date_format ) ); ?>
					</div>
					<div style="clear:both;"></div>
				<?php
			endforeach;
			remove_filter( 'excerpt_more', array( &$this, 'set_excerpt_more' ), 80 );
			remove_filter( 'excerpt_length', array( &$this, 'set_excerpt_length' ), 80 );
			add_filter( 'the_content', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
		}

		// Just in case...
		wp_reset_postdata();

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
	 * Render the mail template
	 *
	 * @param Boolean if the content must be returned or echoed 
	 * 
	 * @return String
	 */
	 
	public function render_mail_contents( $user_content = array(), $echo = true, $key = '' ) {
		
		if ( $this->dummy )
			$user_content = $this->content;

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
													<td><a href="<?php echo get_home_url(); ?>"><img style="max-width:<?php echo $this->settings['logo_width']; ?>px;" src="<?php echo $this->settings['logo']; ?>"></a></td>
													<td align="right">
														<?php if ( $this->settings['show_blog_name'] ): ?>
															<h6><a <?php echo $blogname_style; ?> href="<?php echo get_home_url(); ?>"><?php echo $this->settings['from_sender']; ?></a></h6>
														<?php endif; ?>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</td>
								<td></td>
							</tr>
						</tbody>
					</table>
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
									</div>
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
															<?php if ( absint( $this->settings['manage_subs_page'] ) ): ?>
																<?php printf( __( 'To manage your subscriptions, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( add_query_arg( 'sub_key', $key, get_permalink( $this->settings['manage_subs_page'] ) ) ) ); ?> <br/>	
															<?php endif; ?>
															<?php printf( __( 'To stop receiving these emails, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( $this->get_unsubscribe_url( $key ) ) ); ?> 
														</p>
														<p><?php echo wpautop( $this->settings['footer_text'] ); ?></p>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
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


	public function set_content( $log_id = false ) {
		$content = $this->content_generator->get_content( $log_id );
		$this->content = apply_filters( 'sbe_mail_content', $content, $log_id );
	}

	/**
	 * Send the mail based on the template
	 * 
	 * @param Integer $log_id log ID
	 */
	public function send_mail( $log_id ) {

		$mail_log_id = false;
		if ( is_integer( $log_id ) )
			$mail_log_id = absint( $log_id );
		elseif ( is_string( $log_id ) && $this->dummy )
			$emails_list = array( $log_id );
		else
			return false;

		$model = Incsub_Subscribe_By_Email_Model::get_instance();

		if ( $mail_log_id ) {
			$emails_list = $model->get_log_emails_list( $mail_log_id, absint( $this->settings['mails_batch_size'] ) );
			if ( $emails_list === false )
				return false;
		}


		add_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );
		add_filter( 'wp_mail_charset', array( &$this, 'set_mail_charset' ) );
		add_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' ), 99 );
		add_action( 'phpmailer_init', array( &$this, 'set_phpmailer_atts' )  );


		do_action( 'sbe_pre_send_emails' );
		
		$mails_sent = 0;

		// We are going to try to send the mail to all subscribers
		$sent_to_all_subscribers = true;
		$emails_list = array_unique( $emails_list );
		foreach ( $emails_list as $mail ) {

			$subscriber = incsub_sbe_get_subscriber( $mail );
			if ( ! $subscriber )
				continue;

			$jump_user = false;
			$status = true;

			$key = $subscriber->subscription_key;
			if ( empty( $key ) && ! $this->dummy ) {
				$status = 2;
				$jump_user = true;
				$key = false;
			}
			elseif ( $this->dummy ) {
				$key = '';
			}

			if ( ! $this->dummy && $key ) {
				// The user may not want to get some types of posts
				$user_content = $this->content_generator->filter_user_content( $subscriber );

				if ( empty( $user_content ) ) {
					$status = 3;
					$jump_user = true;
				}
					
			}
			else {
				$user_content = array();
			}

			if ( $key !== false ) {
				$content = $this->render_mail_contents( $user_content, false, $key );
			}
			
			if ( ! $this->dummy ) {

				if ( ! $jump_user ) {

					$unsubscribe_url = $this->get_unsubscribe_url( $key );
					$headers = array(
						'x-mailer-php' => "X-Mailer:PHP/".phpversion(),
						'reply-to' => "Reply-To: <$mail>",
						'list-unsubscribe' => "List-Unsubscribe: <$unsubscribe_url>"
					);

					$subscriber_id = $subscriber->ID;
					
					$headers = apply_filters( 'sbe_template_mail_headers', $headers, $mail, $subscriber_id, $mail_log_id );

					$headers = array_values( $headers );

					
					$is_digest_sent = $model->is_digest_sent( $subscriber_id, $mail_log_id );
					if ( ! $is_digest_sent ) {
						do_action( 'sbe_before_send_single_email', $user_content, $mail );
						wp_mail( $mail, $this->subject, $content, $headers );
						$model->set_digest_sent( $subscriber_id, $mail_log_id );
						do_action( 'sbe_after_send_single_email', $user_content, $mail );
					}
				}
				
				if ( $status === true )
					$status = 1;

				// Creating a new log or incrementing an existing one
				if ( $mails_sent == 0 ) {
					$model->update_mail_log_subject( $mail_log_id, $this->subject );
				}

				if ( $this->logger == null )
					$this->logger = new Subscribe_By_Email_Logger( $mail_log_id );

				$model->increment_mail_log( $mail_log_id );
				$this->logger->write( $mail, $status );
				
				$mails_sent++;

				if ( $mails_sent == absint( $this->settings['mails_batch_size'] ) ) {

					// We could not send the mail to all subscribers
					$sent_to_all_subscribers = false;

					// Now saving the data to send the rest of the mails later
					$mail_settings = array(
						'posts_ids' => $this->content_generator->get_posts_ids()
					);

					$mail_settings = maybe_serialize( $mail_settings );

					$model->set_mail_log_settings( $mail_log_id, $mail_settings );

					set_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug, 'next', Incsub_Subscribe_By_Email::$time_between_batches );

					// We'll finish with this later
					break;
				}
			}
			else {
				wp_mail( $mail, $this->subject, $content );
			}
		}

		// If we have sent the mail to all subscribers we won't need the settings in that log for the future
		if ( $sent_to_all_subscribers && ! $this->dummy ) {
			$model->clear_mail_log_settings( $mail_log_id );
			$posts_ids = $this->content_generator->get_posts_ids();
			if ( ! empty( $posts_ids ) && is_array( $posts_ids ) ) {
				foreach ( $posts_ids as $post_id ) {
					$result_meta = update_post_meta( $post_id, 'sbe_sent', true );
				}
			}
		}


		do_action( 'sbe_after_send_emails' );

		remove_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );
		remove_filter( 'wp_mail_charset', array( &$this, 'set_mail_charset' ) );
		remove_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' ), 99 );
		remove_action( 'phpmailer_init', array( &$this, 'set_phpmailer_atts' )  );

	}

	public function set_mail_charset( $charset ) {
		return get_bloginfo( 'charset' );
	}


	private function get_unsubscribe_url( $key ) {
		return add_query_arg( 'sbe_unsubscribe', $key, trailingslashit( get_home_url() ) );
	}

	


	/*************************/
	/*		  HEADERS        */
	/*************************/
	
	function set_mail_from( $content_type ) {
	  return apply_filters( 'incsub_sbe_from_email', $this->settings['from_email'] );
	}

	function set_phpmailer_atts( $phpmailer ) {

		// Text template
		$phpmailer->AltBody = wp_specialchars_decode( $phpmailer->Body, ENT_QUOTES );

		$text_template = $this->get_text_template();
		$phpmailer->AltBody = str_replace( '%sbe_content%', $phpmailer->AltBody, $text_template );

		$phpmailer->AltBody = wp_kses( $phpmailer->AltBody, array() );

		$phpmailer->AltBody = preg_replace('/^[ \t]*[\r\n]+/m', '', $phpmailer->AltBody);
		$phpmailer->AltBody = preg_replace('/\t+/', '', $phpmailer->AltBody);
		$phpmailer->AltBody = trim( $phpmailer->AltBody );
		
		// HTML template
		$links_pattern = '#<(https?://[^*]+)>#';
		$phpmailer->Body = preg_replace( $links_pattern, '$1', $phpmailer->Body );

		$phpmailer->Body = make_clickable( $phpmailer->Body );

		$html_template = $this->get_html_template();
		$phpmailer->Body = str_replace( '%sbe_content%', $phpmailer->Body, $html_template );
	}

	function set_mail_from_name( $name ) {
	  return $this->settings['from_sender'];
	}

	function set_content_type( $content_type ) {
		return 'text/html';
	}

	function get_html_template() {
		ob_start();
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		    <head>
		        <meta http-equiv="Content-Type" content="text/html;<?php echo get_option('blog_charset'); ?>">
		        <title><?php echo esc_html( $this->subject ); ?></title>
		        
		    </head>
		    <body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin: 0;padding: 0;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;height: 100% !important;width: 100% !important;">
				%sbe_content%
		    </body>
		</html>
		<?php
		return ob_get_clean();
	}

	function get_text_template() {
		ob_start();
		?>
			%sbe_content%
		<?php
		return ob_get_clean();
	}

	

}