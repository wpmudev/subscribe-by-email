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
				<?php 
					$the_table = new Incsub_Subscribe_By_Email_Log_Table();

					$the_table->prepare_items();
					$the_table->search_box( __( 'Search by subject', INCSUB_SBE_LANG_DOMAIN ), 'search-subject' );
					echo '<br/><br/>';
					$the_table->display();
				?>
			</form>
				
		<?php
	}



}