<?php

class Incsub_Subscribe_By_Email_Sent_Emails_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;

		$args = array(
			'slug' => 'sbe-sent-mails',
			'page_title' => __( 'Emails Log', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Emails Log', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		$this->tabs = array(
			'sent-emails' => __( 'Sent Emails', INCSUB_SBE_LANG_DOMAIN ),
			'pending-queue' => __( 'Emails in queue', INCSUB_SBE_LANG_DOMAIN )
		);

		add_action( 'load-subscriptions_page_sbe-sent-mails', array( &$this, 'set_screen_options' ) );		
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );

	}

	public function save_screen_options( $status, $option, $value ) {
		if ( 'sbe_queue_items_per_page' == $option ) 
			return $value;
	}

	public function set_screen_options() {
		add_screen_option( 'per_page', array( 'label' => __( 'Queue items per page', INCSUB_SBE_LANG_DOMAIN ), 'default' => 20, 'option' => 'sbe_queue_items_per_page' ) );
	}

	private function get_current_tab() {
		if ( isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->tabs ) )
			return $_GET['tab'];
		else
			return key( $this->tabs );
	}


	/**
	 * render the settings page
	 */
	public function render_content() {
		$settings = incsub_sbe_get_settings();

		$current_tab = $this->get_current_tab();

		include_once( 'views/emails-log-tabs.php' );

		$view_file = 'views/emails-log-' . $current_tab . '.php';
		if ( 'sent-emails' == $current_tab ) {
			$log_id = isset( $_GET['log_id'] ) ? $_GET['log_id'] : false;

			if ( $log_id ) {
				$log_items = incsub_sbe_get_queue_items(
					array(
						'per_page' => -1,
						'campaign_id' => $log_id,
						'status' => 'pending'
					)
				);

				$model = incsub_sbe_get_model();
				$log = $model->get_single_log( $_GET['log_id'] );

				$users_processed = $log->mail_recipients;
				$max_email_id = $log->max_email_ID;

				$total = incsub_sbe_get_subscribers_count( $max_email_id );
				$pending = absint( $total - $users_processed );

				$log_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->mail_date );

			}
			else {
				require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/tables/log-table.php' );
				$the_table = new Incsub_Subscribe_By_Email_Log_Table();

				$the_table->prepare_items();
			}
		}
		elseif ( 'pending-queue' == $current_tab ) {
			require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/tables/pending-queue-table.php' );
			$the_table = new Incsub_Subscribe_By_Email_Pending_Queue_Table();

			$the_table->prepare_items();

			$next_scheduled = Incsub_Subscribe_By_Email::get_next_scheduled_batch_date();
			$model = incsub_sbe_get_model();
			$remaining_batch = $model->get_remaining_batch_mail();
		}

		include_once( $view_file );

	}

	protected function render_row( $title, $content, $args = array() ) {
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