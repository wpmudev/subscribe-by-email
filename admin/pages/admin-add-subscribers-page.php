<?php


class Incsub_Subscribe_By_Email_Admin_Add_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {	

	private static $errors;

	public function __construct() {

		$subscribers_page = Incsub_Subscribe_By_Email::$admin_subscribers_page;
		
		$args = array(
			'slug' => 'sbe-add-subscribers',
			'page_title' => __( 'Add New', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Add Subscribers', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options',
			'parent' => $subscribers_page->get_menu_slug()
		);
		parent::__construct( $args );

		add_action( 'admin_init', array( &$this, 'validate_form' ) );

	}


	public function render_content() {

		?>

				<?php 
					$errors = get_settings_errors( 'subscribe' ); 
					if ( ! empty( $errors ) ) {
						?>	
							<div class="error">
								<ul>
									<?php
									foreach ( $errors as $error ) {
										?>
											<li><?php echo $error['message']; ?></li>
										<?php
									}
									?>
								</ul>
							</div>
						<?php
					}
					elseif ( isset( $_GET['user-subscribed'] ) && ! isset( $_GET['autopt'] ) ) {
						?>
							<div class="updated"><p><?php printf( __( 'Subscription added. User has %d days to confirm his subscription or he will be removed from the list.', INCSUB_SBE_LANG_DOMAIN ), Incsub_Subscribe_By_Email::$max_confirmation_time / ( 60 * 60 * 24 ) ); ?></p></div>
						<?php
					}
					elseif ( isset( $_GET['user-subscribed'] ) && isset( $_GET['autopt'] ) ) {
						?>
							<div class="updated"><p><?php _e( 'Subscription added', INCSUB_SBE_LANG_DOMAIN ); ?></p></div>
						<?php
					}
					elseif ( isset( $_GET['users-subscribed'] ) && ! isset( $_GET['autopt'] ) ) {
						?>
							<div class="updated"><p><?php printf( __( '%d subscriptions created out of %d e-mail addresses. Users have %d days to confirm their subscriptions or they will be removed from the list.', INCSUB_SBE_LANG_DOMAIN ), $_GET['subscribed'], $_GET['total'], Incsub_Subscribe_By_Email::$max_confirmation_time / ( 60 * 60 * 24 ) ); ?></p></div>
						<?php
					}
					elseif ( isset( $_GET['users-subscribed'] ) && isset( $_GET['autopt'] ) ) {
						?>
							<div class="updated"><p><?php printf( __( '%d subscriptions created out of %d e-mail addresses.', INCSUB_SBE_LANG_DOMAIN ), $_GET['subscribed'], $_GET['total'] ); ?></p></div>
						<?php
					}
				?>

				<form action="" id="add-single-subscriber" method="post">
					<?php
						$settings = incsub_sbe_get_settings();
						$extra_fields = $settings['extra_fields'];
					?>
					<h3><?php _e( 'Subscribe a single user', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
					<?php wp_nonce_field( 'subscribe', '_wpnonce' ); ?>
					<table class="form-table">
						<tbody>
							<?php foreach ( $extra_fields as $field_id => $extra_field ): ?>
								<?php 
									$atts = array(
										'placeholder' => '',
										'show_label' => false
									);
								?>
								<tr valign="top">
									<th scope="row"><?php echo $extra_field['title']; ?></th>
									<td>
										<?php incsub_sbe_render_extra_field( $extra_field['type'], $extra_field['slug'], $extra_field['title'], '', $atts ); ?> 
										<?php echo $extra_field['required'] ? '(*)' : ''; ?><br/>
									</td>
								</tr>
							<?php endforeach; ?>
							<tr valign="top">
								<th scope="row"><?php _e( 'Email', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<input type="text" class="regular-text" name="subscribe-email"> (*)<br/>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Auto-opt in', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<label for="autopt-single">
										<input type="checkbox" name="autopt" id="autopt-single" value="1"> 
										<?php _e( 'Do not send a confirmation email', INCSUB_SBE_LANG_DOMAIN ); ?>
									</label>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Subscribe', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit-single' ); ?>
				</form>

				<form action="" id="import-subscribers" method="post" enctype="multipart/form-data">
					<h3><?php _e( 'Import subscribers', INCSUB_SBE_LANG_DOMAIN ); ?></h3>
					<?php wp_nonce_field( 'subscribe', '_wpnonce' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row"><?php _e( 'Sample CSV', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<?php submit_button( 'Download a sample', 'secondary', 'download_sample_csv', false ); ?>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'CSV file', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<input type="file" class="regular-text" name="subscribe-file">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Auto-opt in', INCSUB_SBE_LANG_DOMAIN ); ?></th>
								<td>
									<label for="autopt-bulk">
										<input type="checkbox" name="autopt" id="autopt-bulk" value="1"> 
										<?php _e( 'Do not send confirmation emails', INCSUB_SBE_LANG_DOMAIN ); ?>
									</label>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Import', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit-bulk' ); ?>
				</form>

			

		<?php
	}

	public function validate_form() {
		
		if ( isset( $_GET['page'] ) && $this->get_menu_slug() == $_GET['page'] ) {

			$input = $_POST;

			$autopt = ! empty( $_POST['autopt'] ) ? true : false;

			if ( isset( $input['download_sample_csv'] ) ) {
				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;
				incsub_sbe_download_csv( ',', 5 );
			}
			if ( isset( $input['submit-single'] ) ) {
				
				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;

				// We are submitting a single user
				$email = sanitize_email( $input['subscribe-email'] );
				if ( is_email( $email ) ) {
					$model = incsub_sbe_get_model();

					$settings = incsub_sbe_get_settings();
					$extra_fields = $settings['extra_fields'];
					$extra_fields_error = false;
					$meta = array();
					foreach ( $extra_fields as $field_id => $value ) {
						if ( empty( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) && $value['required'] ) {
							add_settings_error( 'subscribe', 'extra-fields', sprintf( __( '%s field is required', INCSUB_SBE_LANG_DOMAIN ), $value['title'] ) );
							break;
						}
						elseif ( ! empty( $_POST[ 'sbe_extra_field_' . $value['slug'] ] ) ) {
							$meta[ $value['slug'] ] = incsub_sbe_validate_extra_field( $value['type'], $_POST[ 'sbe_extra_field_' . $value['slug'] ] );
						}
					}

					if ( ! $extra_fields_error )
						$result = Incsub_Subscribe_By_Email::subscribe_user( $email, __( 'Manual Subscription', INCSUB_SBE_LANG_DOMAIN ), __( 'Instant', INCSUB_SBE_LANG_DOMAIN ), $autopt, $meta );
				}
				else {
					// Email not valid
					add_settings_error( 'subscribe', 'email', __( 'The email is not a valid one', INCSUB_SBE_LANG_DOMAIN ) );
				}

				

				$errors = get_settings_errors( 'subscribe' ); 
				if ( empty( $errors ) ) {

					$query_args = array(
						'page' => $this->get_menu_slug(),
						'user-subscribed' => 'true'
					);

					if ( $autopt )
						$query_args['autopt'] = 'true';

					wp_redirect( add_query_arg( 
						$query_args,
						admin_url( 'admin.php' ) )
					);
				}
			}

			if ( isset( $input['submit-bulk'] ) ) {

				if ( ! wp_verify_nonce( $input['_wpnonce'], 'subscribe' ) )
					return false;
				
				if ( ! isset( $_FILES['subscribe-file'] ) ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}
				if ( $_FILES['subscribe-file']['error'] ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}

				$types = array( 'application/vnd.ms-excel','text/plain','text/csv','text/tsv', 'application/octet-stream' );

				if ( ! in_array( $_FILES['subscribe-file']['type'], $types ) ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}

				if ( preg_match( '/\.csv$/', $_FILES['subscribe-file']['name'] ) == 0 ) {
					add_settings_error( 'subscribe', 'email', __( 'Empty or incorrect type of file', INCSUB_SBE_LANG_DOMAIN ) );
					return false;
				}


				if ( ( $handle = fopen( $_FILES['subscribe-file']['tmp_name'], 'r' ) ) !== false ) {

					// Extra fields slugs
					$extra_fields_slugs = incsub_sbe_get_extra_fields_slugs();

					$first_row = fgetcsv( $handle, null, ',', '"' );

					if ( ! $first_row ) {
						add_settings_error( 'subscribe', 'email', __( 'The file does not include a valid header', INCSUB_SBE_LANG_DOMAIN ) );
						return;
					}

					// Check columns
					$columns = array();
					foreach ( $first_row as $column ) {
						$columns[] = stripslashes_deep( $column );
					}

					// Is the mail header in the sheet?
					$is_valid = in_array( 'email', $columns );
					
					if ( ! $is_valid ) {
						add_settings_error( 'subscribe', 'email', __( 'The file does not include an email in the header', INCSUB_SBE_LANG_DOMAIN ) );
						return;
					}

					$subscribed_c = 0;
					$row_c = 0;

					if ( $is_valid ) {
						$email_col = array_search( 'email', $columns );

						// Checking for extra fields columns
						$extra_fields_cols = array();
						foreach ( $extra_fields_slugs as $extra_field_slug ) {
							$extra_fields_cols[ $extra_field_slug ] = array_search( $extra_field_slug, $columns );
						}

						$model = incsub_sbe_get_model();
						while ( ( $row = fgetcsv( $handle, null, ',', '"' ) ) !== false ) {

							$row_c++;

							if ( 
								is_email( sanitize_email( $row[$email_col] ) ) 
								&& $sid = Incsub_Subscribe_By_Email::subscribe_user( sanitize_email( $row[$email_col] ), __( 'Manual Subscription', INCSUB_SBE_LANG_DOMAIN ), __( 'Import', INCSUB_SBE_LANG_DOMAIN ), $autopt ) 
							) {
								$subscribed_c++;
								foreach ( $extra_fields_cols as $extra_field_slug => $extra_field_col ) {
									if ( ! empty( $row[ $extra_field_col ] ) ) {
										$model->add_subscriber_meta( $sid, $extra_field_slug, $row[ $extra_field_col ] );
									}
								}
							}
						}

						fclose( $handle );

						$query_args = array(
							'page' => $this->get_menu_slug(),
							'users-subscribed' => 'true',
							'total' => $row_c,
							'subscribed' => $subscribed_c,
						);

						if ( $autopt )
							$query_args['autopt'] = 'true';

						wp_redirect( add_query_arg( 
							$query_args,
							admin_url( 'admin.php' ) )
						);

						exit();
					}

				} 
				else {
					add_settings_error( 'subscribe', 'email', __( 'Failed to open file', INCSUB_SBE_LANG_DOMAIN ) );
				}
			}

		}
		
	}
}

