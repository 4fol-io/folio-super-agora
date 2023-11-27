<?php

/**
 * Folio Super Agora Network
 * 
 * @since      1.0.2
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Network extends Folio_Super_Agora_Base {


	/**
	 * Initialize the class
	 *
	 * @since 1.0.2
	 */
	public function __construct() {

		// Network admin menu
		add_action( 'network_admin_menu', 	[ $this, 'add_network_menu' ] );

		// Network admin bar
		add_action('admin_bar_menu', [ $this, 'add_network_bar_submenu' ], 25 );

		// Screen options
		add_filter('set-screen-option', [$this, 'set_screen_options'], 10, 3);

	}


	/**
	 * Creates a new item in the network admin menu.
	 *
	 * @since 1.0.2
	 * 
	 * @access public
	 * @uses add_submenu_page()
	 */
	public function add_network_menu() {

		$page = add_menu_page(
			__('SuperAgoras', 'folio-super-agora'),
			__('SuperAgoras', 'folio-super-agora'),
			'manage_network_options',
			'folio-superagoras',
			[$this, 'network_list_page'],
			'dashicons-networking',
			5
		);

		// Add screen options for Delivery Table
		add_action('load-' . $page, [$this, 'screen_option']);
	}


	/**
	 * Creates a new item in the network admin bar.
	 *
	 * @since 1.0.2
	 * 
	 * @access public
	 * @uses network_admin_url()
	 * @uses add_node()
	 */
	public function add_network_bar_submenu ($wp_admin_bar ) {
		$args = array (
			'id'        => 'superagoras',
			'title'     => __('SuperAgoras', 'folio-super-agora'),
			'href'   	=> network_admin_url( 'admin.php?page=folio-superagoras' ),
			'parent'    => 'network-admin'
		);
		
		$wp_admin_bar->add_node( $args );
	}


	/**
	 * Screen options
	 * 
	 * @since 1.0.2
	 */
	public function screen_option() {
		$option = 'per_page';
		$args   = [
			'label'   => __('Superagoras per page', 'folio-super-agora'),
			'default' => 20,
			'option'  => 'superagoras_per_page'
		];
		add_screen_option($option, $args);
	}


	/**
	 * Set screen options
	 * 
	 * @since 1.0.2
	 */
	public function set_screen_options($status, $option, $value) {
		return $value;
	}


	/**
	 * Adds SuperAgoras List Page
	 *
	 * @since 1.0.2
	 * 
	 * @access public
	 * @uses settings_fields()
	 * @uses do_settings_sections()
	 */
	public function network_list_page() {
		$list = new Folio_Super_Agora_Network_List();
		$list->prepare_items();
		?>

		<div class="wrap folio-superagoras">

			<h2><?php echo esc_html(get_admin_page_title()); ?></h2>

			<form id="folio-superagoras-list" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php $list->search_box(__('Search SuperAgoras', 'folio-super-agora'), 'superagoras-find'); ?>		
				<?php $list->display(); ?>	
			</form>

		</div>
		<?php
	}


}
