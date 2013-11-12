<?php

class Incsub_Subscribe_By_Email_Sent_Emails_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;

		$args = array(
			'slug' => 'sbe-sent-mails',
			'page_title' => __( 'Sent Emails', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Sent Emails', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

	}



	/**
	 * render the settings page
	 */
	public function render_content() {

		?>
			<form action="" method="post">
				<?php if ( isset( $_GET['log_id'] ) ): ?>
					<?php 
						$model = Incsub_Subscribe_By_Email_Model::get_instance();
						$log = $model->get_single_log( $_GET['log_id'] );

						$users_processed = $log->mail_recipients;
						$max_email_id = $log->max_email_ID;

						$model = incsub_sbe_get_model();
						$total = $model->get_subscribers_count( $max_email_id );
						$pending = absint( $total - $users_processed );

						$file = Subscribe_By_Email_Logger::open_log( $_GET['log_id'] );

						?>

 						
						<h3><?php _e( 'Digest details', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
						
						
						<table class="form-table">
							<?php ob_start(); ?>
							<?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->mail_date ); ?>
							<?php $this->render_row( __( 'Date', INCSUB_SBE_LANG_DOMAIN ), ob_get_clean() ); ?>

							<?php $this->render_row( __( 'Status', INCSUB_SBE_LANG_DOMAIN ), empty( $log->mail_settings ) ? __( 'Finished', INCSUB_SBE_LANG_DOMAIN ) : __( 'Pending', INCSUB_SBE_LANG_DOMAIN ) ); ?>

							<?php $this->render_row( __( 'Subscribers processed', INCSUB_SBE_LANG_DOMAIN ), $users_processed ); ?>

							<?php $this->render_row( __( 'Still pending', INCSUB_SBE_LANG_DOMAIN ), $pending ); ?>

							<?php $this->render_row( __( 'Total', INCSUB_SBE_LANG_DOMAIN ), $total ); ?>
						</table>
						
						<h3><?php _e( 'Emails details', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
						<table class="form-table">
							<?php 
								if ( is_resource( $file ) ) {
									while ( $buffer = Subscribe_By_Email_Logger::read_line( $file ) ) {
										$line = explode( '|', $buffer );

										if ( absint( $line[2] ) !== 0 ) {
											switch ( absint( $line[2] ) ) {
												case 1: { $status = '<span style="color:green">' . __( 'Sent', INCSUB_SBE_LANG_DOMAIN ) . '</span>'; break; }
												case 2: { $status = '<span style="color:red">' . __( 'User key undefined', INCSUB_SBE_LANG_DOMAIN ) . '</span>'; break; }
												case 3: { $status = '<span style="color:red">' . __( 'User content empty', INCSUB_SBE_LANG_DOMAIN ) . '</span>'; break; }
												default: { $status = $line[2]; break; }
											}
										}
										else {
											$status = $line[2];
										}
										$this->render_row( $line[0], $status );
									}
								}
								else {
									_e( 'No details found', INCSUB_SBE_LANG_DOMAIN );
								}
 							?>
						</table>

				<?php else: ?>
					<?php 
						require_once( INCSUB_SBE_PLUGIN_DIR . 'inc/log-table.php' );
						$the_table = new Incsub_Subscribe_By_Email_Log_Table();

						$the_table->prepare_items();
						$the_table->search_box( __( 'Search by subject', INCSUB_SBE_LANG_DOMAIN ), 'search-subject' );
						echo '<br/><br/>';
						$the_table->display();
					?>
				<?php endif; ?>
			</form>
				
		<?php
	}

	protected function render_row( $title, $content ) {
		?>
			<tr valign="top">
				<th scope="row"><label for="site_name"><?php echo $title; ?></label></th>
				<td>
					<?php 
						echo $content;
					?>
				</td>
			</tr>
		<?php
	}



}