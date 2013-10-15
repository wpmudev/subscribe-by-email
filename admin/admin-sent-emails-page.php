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

						$emails_list = maybe_unserialize( $log->mails_list );
				        if ( is_array( $emails_list ) && ! empty( $emails_list ) ) {
				            $emails_sent = 0;
				            foreach ( $emails_list as $email ) {
				                if ( $email['status'] != false )
				                    $emails_sent++;
				            }
				        }
				        $emails_pending = count( $emails_list ) - $emails_sent;
				        $total = count( $emails_list );

					?>
					<h3><?php _e( 'Digest details', INCSUB_SBE_LANG_DOMAIN ); ?></h3>

					<table class="form-table">
						<?php ob_start(); ?>
						<?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->mail_date ); ?>
						<?php $this->render_row( __( 'Date', INCSUB_SBE_LANG_DOMAIN ), ob_get_clean() ); ?>

						<?php $this->render_row( __( 'Status', INCSUB_SBE_LANG_DOMAIN ), empty( $log->mail_settings ) ? __( 'Finished', INCSUB_SBE_LANG_DOMAIN ) : __( 'Pending', INCSUB_SBE_LANG_DOMAIN ) ); ?>

						<?php $this->render_row( __( 'Tried to send', INCSUB_SBE_LANG_DOMAIN ), $emails_sent ); ?>

						<?php $this->render_row( __( 'Still pending', INCSUB_SBE_LANG_DOMAIN ), $emails_pending ); ?>

						<?php $this->render_row( __( 'Total', INCSUB_SBE_LANG_DOMAIN ), $total ); ?>
					</table>

					<table class="form-table">
						<h3><?php _e( 'Emails details', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
						<?php foreach ( $emails_list as $email ): ?>
							<?php
								if ( $email['status'] === true )
									$status = '<span style="color:green">' . __( 'Sent', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
								elseif ( $email['status'] === false )
									$status = '<span style="color:red">' . __( 'Still pending', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
								else
									$status = '<span style="color:red">' . $email['status'] . '</span>';
							?>
							<?php $this->render_row( $email['email'], $status ); ?>
								
						<?php endforeach; ?> 
					</table>

				<?php else: ?>
					<?php 
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