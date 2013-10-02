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
					<h3><?php echo $log->mail_subject; ?></h3>
					<p><strong>Date:</strong> <?php echo date_i18n( 'Y-m-d H:i:s' ); ?></p>
					<p><strong>Status:</strong> <?php echo empty( $log->mail_settings ) ? __( 'Finished', INCSUB_SBE_LANG_DOMAIN ) : __( 'Pending', INCSUB_SBE_LANG_DOMAIN ); ?></p>
					<p><strong>Emails sent:</strong> <?php echo $emails_sent; ?></p>
					<p><strong>Emails pending:</strong> <?php echo $emails_pending; ?></p>
					<p><strong>Total:</strong> <?php echo $total; ?></p>
					<h3>Emails details:</h3>
						<ul>
						<?php foreach ( $emails_list as $email ): ?>
							<li>
								<strong><?php echo $email['email']; ?></strong>: 
								<?php
									if ( $email['status'] === true ) {
										echo 'Sent';
									}
									elseif ( $email['status'] === false ) {
										echo 'Still pending';
									}
									else {
										echo $email['status'];
									}
								?>

							</li>
						<?php endforeach; ?> 
						</ul>
					</p>

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



}