<?php


class Incsub_Subscribe_By_Email_Admin_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	private $subscriber;
	private $errors;

	public function __construct() {

		$args = array(
			'slug' => 'sbe-subscribers',
			'page_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options'
		);
		parent::__construct( $args );

		$this->subscriber = false;

		add_action( 'admin_init', array( &$this, 'validate_form' ) );
		//add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		//add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

	}



	public function render_page() {

		$add_new_link = add_query_arg(
			'page',
			'sbe-add-subscribers',
			admin_url( 'admin.php' )
		);

		$title_link = '<a href="' . $add_new_link . '" class="add-new-h2">' . __( 'Add New', INCSUB_SBE_LANG_DOMAIN ) . '</a>';

		?>
			<div class="wrap">
				
				<?php screen_icon( 'sbe' ); ?>
				
				<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['sid'] ) ): ?>
					<?php 

						$model = incsub_sbe_get_model(); 
						$this->subscriber = incsub_sbe_get_subscriber( 'subscription_ID', $_GET['sid'] );

						if ( empty( $this->subscriber ) )
							wp_die( __( 'The subscriber does not exist', INCSUB_SBE_LANG_DOMAIN ) );

						$errors = get_settings_errors( 'subscribe' );

						if ( ! empty( $errors ) )
							settings_errors( 'subscribe' );

						if ( isset( $_GET['updated'] ) && $_GET['updated'] == 'true' && empty( $errors ) ) {
							?>
								<div class="updated">
									<p><?php _e( 'Subscriber updated', INCSUB_SBE_LANG_DOMAIN ); ?></p>
								</div>
							<?php
						}
					?>
					<h2><?php _e( 'Edit subscriber', INCSUB_SBE_LANG_DOMAIN ); ?></h2>

					<form id="form-subscriptions-edit" action="" method="post">
						<?php wp_nonce_field( 'edit_subscriber' ); ?>
						<input type="hidden" name="sid" value="<?php echo $this->subscriber->get_subscription_ID(); ?>">
						<table class="form-table">
							<?php $this->render_row( __( 'Email', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_email_row' ) ); ?>
							<?php $this->render_row( __( 'First Name', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_first_name_row' ) ); ?>
							<?php $this->render_row( __( 'Last Name', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_last_name_row' ) ); ?>
							<?php $this->render_row( __( 'Address', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_address_row' ) ); ?>

						</table>
						<p class="submit">
							<?php submit_button( __( 'Submit changes', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit_edit_subscriber', false ); ?>
							<a href="<?php echo esc_url( $this->get_permalink() ) ?>" class="button-secondary"><?php _e( 'Cancel', INCSUB_SBE_LANG_DOMAIN ); ?></a>
						</p>
					</form>
				<?php else: ?>
					<h2><?php _e( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ); ?> <?php echo $title_link; ?></h2>
					
					<form id="form-subscriptions-list" action="" method="post">
						<?php

							$the_table = new Incsub_Subscribe_By_Email_Subscribers_Table();

							$the_table->prepare_items();
							$the_table->search_box( __( 'Search by email', INCSUB_SBE_LANG_DOMAIN ), 'search-email' );
							echo '<br/><br/>';
							$the_table->display();

						?>
					</form>
				<?php endif; ?>

			</div>

		<?php
	}

	public function render_content() {

	}

	public function render_email_row() {

		?>
			<input type="text" class="regular-text" name="subscribe-email" value="<?php echo esc_attr( $this->subscriber->get_subscription_email() ); ?>" />
		<?php
	}

	public function render_first_name_row() {
		$model = incsub_sbe_get_model();
		$first_name = isset( $_POST['subscribe-meta']['first_name'] ) ? stripslashes_deep( $_POST['subscribe-meta']['first_name'] ) : $this->subscriber->get_meta( 'first_name', '' );
		?>
			<input type="text" class="regular-text" name="subscribe-meta[first_name]" value="<?php echo esc_attr( $first_name ); ?>" />
		<?php
	}

	public function render_last_name_row() {
		$model = incsub_sbe_get_model();
		$last_name = isset( $_POST['subscribe-meta']['last_name'] ) ? stripslashes_deep( $_POST['subscribe-meta']['last_name'] ) : $this->subscriber->get_meta( 'last_name', '' );
		?>
			<input type="text" class="regular-text" name="subscribe-meta[last_name]" value="<?php echo esc_attr( $last_name ); ?>" />
		<?php
	}

	public function render_address_row() {
		$model = incsub_sbe_get_model();
		$address = isset( $_POST['subscribe-meta']['address'] ) ? stripslashes_deep( $_POST['subscribe-meta']['address'] ) : $this->subscriber->get_meta( 'address', '' );
		?>
			<textarea name="subscribe-meta[address]" cols="30" rows="10"><?php echo esc_textarea( $address ); ?></textarea>
		<?php
	}

	public function validate_form() {
		if ( isset( $_POST['submit_edit_subscriber'] ) ) {
			check_admin_referer( 'edit_subscriber' );

			$model = incsub_sbe_get_model();

			$email = $_POST['subscribe-email'];
			$sid = $_POST['sid'];
			$error = false;

			if ( ! is_email ( $email ) ) {
				add_settings_error( 'subscribe', 'wrong-email', __( 'Please, insert a valid email', INCSUB_SBE_LANG_DOMAIN ) );
				$error = true;
			}
			else {
				$model->update_subscriber_email( $sid, $email );
			}

			if ( ! empty( $_POST['subscribe-meta']['first_name'] ) )
				$model->update_subscriber_meta( $sid, 'first_name', stripslashes_deep( $_POST['subscribe-meta']['first_name'] ) );

			if ( ! empty( $_POST['subscribe-meta']['last_name'] ) )
				$model->update_subscriber_meta( $sid, 'last_name', stripslashes_deep( $_POST['subscribe-meta']['last_name'] ) );

			if ( ! empty( $_POST['subscribe-meta']['address'] ) )
				$model->update_subscriber_meta( $sid, 'address', stripslashes_deep( $_POST['subscribe-meta']['address'] ) );

			if ( ! $error )
				wp_redirect( add_query_arg( 'updated', 'true' ) );


		}
	}
}

