<?php


class Incsub_Subscribe_By_Email_Admin_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	public function __construct() {

		$args = array(
			'slug' => 'sbe-subscribers',
			'page_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options'
		);
		parent::__construct( $args );

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

			</div>

		<?php
	}

	public function render_content() {

	}
}

