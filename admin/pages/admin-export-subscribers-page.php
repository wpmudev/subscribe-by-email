<?php


class Incsub_Subscribe_By_Email_Export_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	private static $errors;

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;
		
		$args = array(
			'slug' => 'sbe-export-subscribers',
			'page_title' => __( 'Export', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Export Subscribers', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		add_action( 'admin_init', array( &$this, 'validate_form' ) );

	}

	public function render_content() {

		?>

			<form action="" id="export-subscribers" method="post" enctype="multipart/form-data">
				<h3><?php _e( 'Export subscribers', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
				<p><?php _e( 'Export all subscribers into a CSV file', INCSUB_SBE_LANG_DOMAIN ); ?></p>
				<?php wp_nonce_field( 'subscribe', '_wpnonce' ); ?>
				<?php submit_button( __( 'Export', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit-export' ); ?>
			</form>

		<?php
	}

	public function validate_form() {
		
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] ) {

			$input = $_POST;

			if ( isset( $input['submit-export'] ) ) {

				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;

				header('Content-Type: text/csv');
				header('Content-Disposition: attachment;filename='.date('YmdHi').'.csv');
				
				echo '"ID","E-mail","Type","Created","Note"'."\n";
				
				$model = Incsub_Subscribe_By_Email_Model::get_instance();
				$subscriptions = $model->get_all_subscribers();

				foreach ($subscriptions as $subscription) {
					echo '"'.$subscription['subscription_ID'].'","'.$subscription['subscription_email'].'","'.$subscription['subscription_type'].'","'. date_i18n( 'Y-m-d', $subscription['subscription_created'] ) . '","'.$subscription['subscription_note'].'"'."\n";
				}
				
				exit();
			}

		}
		
	}
}

