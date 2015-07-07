<?php


class Incsub_Subscribe_By_Email_Admin_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	private $subscriber;
	private $errors;

	public function __construct() {

		$args = array(
			'slug' => 'sbe-subscribers',
			'page_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_subscribe_by_email',
			'menu_icon' => 'dashicons-email-alt'
		);
		parent::__construct( $args );

		add_action( 'admin_init', array( &$this, 'validate_form' ) );
		add_action( 'admin_init', array( &$this, 'maybe_download_csv' ) );
		add_action( 'load-toplevel_page_sbe-subscribers', array( &$this, 'set_screen_options' ) );		
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
		add_action( 'admin_head', array( $this, 'render_icon_styles' ) );

	}

	public function render_icon_styles() {
		global $current_screen;
		if ( $current_screen->id == $this->page_id ) {
			?>
				<style>
					.sbe-icon-confirmed-yes:before {
						color:green;
					}
					.sbe-icon-confirmed-no:before {
						color:#D55252;
					}
				</style>
			<?php
		}
	}

	public function save_screen_options( $status, $option, $value ) {

		if ( 'subscribers_per_page' == $option ) {
			return $value;
		}

		return $status;
	}

	public function set_screen_options() {
		add_screen_option( 'per_page', array( 'label' => __( 'Subscribers per page', INCSUB_SBE_LANG_DOMAIN ), 'default' => 20, 'option' => 'subscribers_per_page' ) );
	}



	public function render_page() {
		$current_screen = get_current_screen();

		$add_new_link = add_query_arg(
			'page',
			'sbe-add-subscribers',
			admin_url( 'admin.php' )
		);

		$title_link = '<a href="' . $add_new_link . '" class="add-new-h2">' . __( 'Add New', INCSUB_SBE_LANG_DOMAIN ) . '</a>';

		?>
			<div class="wrap">
				
				<?php screen_icon( 'sbe' ); ?>
				
				<?php if ( isset( $_GET['upgraded'] ) ): ?>
					<div class="updated"><p><?php _e( 'Subscribe By Email has been updated', INCSUB_SBE_LANG_DOMAIN ); ?></p></div>
				<?php endif; ?>
				
				<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['sid'] ) ): ?>
					<?php 

						$subscriber = incsub_sbe_get_subscriber( $_GET['sid'] );

						if ( empty( $subscriber ) )
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

						$settings = incsub_sbe_get_settings();
						$extra_fields = $settings['extra_fields'];
					?>
					<h2><?php _e( 'Edit subscriber', INCSUB_SBE_LANG_DOMAIN ); ?></h2>

					<form id="form-subscriptions-edit" action="" method="post">
						<?php wp_nonce_field( 'edit_subscriber' ); ?>
						<input type="hidden" name="sid" value="<?php echo $subscriber->ID; ?>">
						<table class="form-table">
							<?php $this->render_row( __( 'Email', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_email_row' ), $subscriber ); ?>
							<?php 

								foreach ( $extra_fields as $extra_field ) {
									$callback = 'render_extra_field';
									$args = array(
										'subscriber' => $subscriber,
										'atts' => $extra_field
									);
									$this->render_row( $extra_field['title'], array( &$this, $callback ), $args );
								}
							?>
							
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
							require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/tables/subscribers-table.php' );
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

	public function render_email_row( $subscriber ) {
		?>
			<input type="text" class="regular-text" disabled="true" name="subscribe-email" value="<?php echo esc_attr( $subscriber->subscription_email ); ?>" />
		<?php
	}

	public function render_extra_field( $args ) {
		$subscriber = $args['subscriber'];
		$extra_field = $args['atts'];

		$meta_value = isset( $_POST['subscribe-meta'][ $extra_field['slug'] ] ) ? 
			incsub_sbe_validate_extra_field( $extra_field['type'], $_POST['subscribe-meta'][ $extra_field['slug'] ] ) : 
			$subscriber->get_meta( $extra_field['slug'], '' );

		$atts = array(
			'name' => 'subscribe-meta[' . $extra_field['slug'] . ']'
		);

		incsub_sbe_render_extra_field(  $extra_field['type'], $extra_field['slug'], '', $meta_value, $atts );
		echo $extra_field['required'] ? '(*)' : '';

	}


	public function validate_form() {
		if ( isset( $_POST['submit_edit_subscriber'] ) ) {
			check_admin_referer( 'edit_subscriber' );

			$model = incsub_sbe_get_model();

			$sid = $_POST['sid'];
			$error = false;

			$subscriber = incsub_sbe_get_subscriber( $sid );
			if ( ! $subscriber )
				return;

			$settings = incsub_sbe_get_settings();
			$extra_fields = $settings['extra_fields'];

			$extra_fields_values = array();
			foreach ( $extra_fields as $extra_field ) {
				$meta_value = isset( $_POST['subscribe-meta'][ $extra_field['slug'] ] ) ? $_POST['subscribe-meta'][ $extra_field['slug'] ] : '';
				$meta_value = incsub_sbe_validate_extra_field( $extra_field['type'], $meta_value );

				$extra_fields_values[ $extra_field['slug'] ] = $meta_value;
				if ( empty( $meta_value ) && $extra_field['required'] ) {
					add_settings_error( 'subscribe', 'required-extra-field', sprintf( __( '%s is a required field', INCSUB_SBE_LANG_DOMAIN ), $extra_field['title'] ) );
					$_POST['subscribe-meta'][ $extra_field['slug'] ] = $subscriber->get_meta( $extra_field['slug'] );
					$error = true;
				}

				if ( $error )
					break;

			}

			if ( ! $error ) {
				foreach ( $extra_fields_values as $slug => $value ) {
					update_post_meta( $sid, $slug, $value );	
				}
				
				wp_redirect( add_query_arg( 'updated', 'true' ) );
			}


		}
	}

	public function maybe_download_csv() {
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] && isset( $_POST['sbe-download-csv'] ) ) {

			check_admin_referer( 'bulk-subscriptors' );

			$sep = ',';
			incsub_sbe_download_csv( $sep );
        }
	}

}

