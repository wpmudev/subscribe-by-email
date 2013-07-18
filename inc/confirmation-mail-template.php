<?php


class Incsub_Subscribe_By_Email_Confirmation_Template {

	private $settings;
	private $to;
	private $user_key;


	public function __construct( $settings, $email ) {
		$this->settings = $settings;
		$this->to = $email;

		$model = Incsub_Subscribe_By_Email_Model::get_instance();
		$this->user_key = $model->get_user_key( $this->to );
	}

	
	/**
	 * Render the mail template
	 *
	 * @param Boolean if the content must be returned or echoed 
	 * 
	 * @return String
	 */
	public function render_mail_template() {
		$font_style = "style=\"font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif !important;background:#DEDEDE;padding-top:20px;margin-bottom:30px;\"";
		$table_top_style = 'style="padding:10px 20px;border-bottom:1px solid black;margin:0 auto;width:600px;border-top:5px solid ' . $this->settings['header_color'] . '"';
		$table_style = 'style="padding:10px 20px;width:600px;margin:0 auto;"';
		$column_style = 'style="display: block!important;"';
		$column_wrap_style = 'style="margin: 0 auto; display: block;"';
		$blogname_style = 'style="text-decoration:none !important; margin: 0!important; padding:0;font-weight: 900; font-size: 14px; text-transform: uppercase; color: ' . $this->settings['header_text_color'] . ' !important;"';
		$subject_style = 'style="font-weight: 500; font-size: 27px;line-height: 1.1; margin-bottom: 15px; color: #000 !important;"';
		$lead_style = 'style="font-size: 17px;margin-bottom: 10px; font-weight: normal; font-size: 14px; line-height: 1.6;"';
		$footer_style = 'style="font-size:11px;color:#666 !important;"';
		$button_style = 'style="background-color:#278AB6;border-radius:25px;text-decoration:none;color: #FFF;display: inline-block;line-height: 23px;height: 24px;padding: 0 10px 1px;cursor:pointer;box-sizing: border-box;font-size:12px"';
		ob_start();

		?>
		<div <?php echo $font_style; ?>>
				<table <?php echo $table_top_style; ?> bgcolor="#EFEFEF">
					<tbody>
						<tr>
							<td></td>
							<td <?php echo $column_style; ?>>
								<div <?php echo $column_wrap_style; ?>>
									<table <?php echo $table_style; ?> >
										<tbody>
											<tr>
												<td><strong><?php printf( __( 'Subscribe to posts on <a href="%s">%s</a>', INCSUB_SBE_LANG_DOMAIN ), site_url(), get_bloginfo( 'name' ) ); ?></strong></td>
											</tr>
										</tbody>
									</table>
								</div>
							</td>
							<td></td>
						</tr>
					</tbody>
				</table><!-- /HEADER -->
				<table <?php echo $table_style; ?> bgcolor="#FFFFFF">
					<tbody>
						<tr>
							<td></td>
							<td <?php echo $column_style; ?> >
								<div <?php echo $column_wrap_style; ?>>
									<table <?php echo $table_style; ?>>
										<tbody>
											<tr>
												<td>
													<?php 
														echo wpautop( $this->settings['subscribe_email_content'] );
														printf( __( '<p><strong>Blog Name:</strong> %s</p>
<p><strong>Blog URL:</strong> <a href="%s">%s</a></p>
<a %s href="%s">Confirm subscription</a>', INCSUB_SBE_LANG_DOMAIN ),
															get_bloginfo( 'name' ),
															site_url(),
															site_url(),
															$button_style,
															add_query_arg( 'sbe_confirm', $this->user_key, site_url() )
														);
													
													?>
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

		return ob_get_clean();
	}


	
	/**
	 * Send the mail based on the template
	 * 
	 * @param False/String $to False if just sending Bcc
	 * @param Array $bcc Bcc list 
	 */
	public function send_mail() {

		add_filter( 'wp_mail_content_type', array( &$this, 'set_html_content_type' ) );
		add_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );

		$content = $this->render_mail_template();
		wp_mail( $this->to, __( 'Please, confirm subscription', INCSUB_SBE_LANG_DOMAIN ), $content );

		remove_filter( 'wp_mail_content_type', array( &$this, 'set_html_content_type' ) );
		remove_filter( 'wp_mail_from', array( &$this, 'set_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( &$this, 'set_mail_from_name' ) );


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