<?php

abstract class Incsub_Subscribe_By_Email_Admin_Page {
	
	private $menu_slug;
	protected $page_id;

	private $page_title;
	private $menu_title;
	private $capability;
	private $parent = false;

	public function __construct( $args ) {
		extract( $args );
		$this->menu_slug = $slug;

		$this->page_title = $page_title;
		$this->menu_title = $menu_title;
		$this->capability = $capability;
		if ( ! empty( $parent ) )
			$this->parent = $parent;

		add_action( 'admin_menu', array( &$this, 'add_menu' ) );

	}

	/**
	 * Add the menu to the menu
	 */
	public function add_menu() {

		if ( ! empty( $this->parent ) ) {
			$this->page_id = add_submenu_page( 
				$this->parent,
				$this->page_title, 
				$this->menu_title, 
				$this->capability, 
				$this->menu_slug, 
				array( &$this, 'render_page' )
			);	
		}
		else {

			$this->page_id = add_menu_page( 
				$this->page_title, 
				$this->menu_title, 
				$this->capability, 
				$this->menu_slug, 
				array( &$this, 'render_page' ),
				'div'
			);

			// Yes, SBE main menu is a bit different
			add_submenu_page( 
				$this->menu_slug,
				__( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ), 
				__( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ), 
				$this->capability, 
				$this->menu_slug, 
				array( &$this, 'render_page' )
			);
		}
		
	}

	public function render_page() {

		if ( ! current_user_can( $this->capability ) )
			wp_die( __( 'You do not have enough permissions to access to this page', INCSUB_SBE_LANG_DOMAIN ) );

		?>
			<div class="wrap">
				
				<?php screen_icon( 'sbe' ); ?>

				<h2><?php echo $this->page_title; ?></h2>

				<?php $this->render_content(); ?>

			</div>

		<?php

	}

	public abstract function render_content();

	public function get_menu_slug() {
		return $this->menu_slug;
	}

	public function get_page_id() {
		return $this->page_id;
	}

	public function get_permalink() {
		return add_query_arg( 
			'page',
			$this->get_menu_slug(),
			admin_url( 'admin.php' )
		);
	}

}