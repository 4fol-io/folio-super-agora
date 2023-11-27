<?php
/**
 * Plugin Name:     Folio SuperAgora
 * Plugin URI: 		https://folio.uoc.edu/
 * Description:     Folio SuperAgora Management
 * Author:          tresipunt
 * Author URI:      https://tresipunt.com/
 * Text Domain:     folio-super-agora
 * Domain Path:     /languages
 * Version:         1.0.2
 * Tested up to: 	6.1.1
 * License: 		GNU General Public License v3.0
 * License URI: 	http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package         Folio
 * @subpackage 		Super
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'FOLIO_SUPER_AGORA_VERSION' ) ) {
	define( 'FOLIO_SUPER_AGORA_VERSION', '1.0.2' );
}

if ( ! defined( 'FOLIO_SUPER_AGORA_URL' ) ) {
	define( 'FOLIO_SUPER_AGORA_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'FOLIO_SUPER_AGORA_PATH' ) ) {
	define( 'FOLIO_SUPER_AGORA_PATH', plugin_dir_path( __FILE__ ) );
}

// Disable SuperTags functionality
if ( ! defined( 'FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS' ) ) {
	define( 'FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS', false );
}

final class Folio_Super_Agora {

	/**
	 * Singleton class instance
	 *
	 * @since    1.0.0
	 * @access   private static
	 * @var      object    $instance    Class instance
	 */
	private static $instance = null;


	/**
	 * Return an instance of the class
	 *
	 * @return 	Folio_Super_Agora class instance.
	 * @since 	1.0.0
	 * @access 	public static
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
     * Fired during plugin activation
	 * 
	 * @since 	1.0.0
	 * @access 	public static
     */
	public static function activate() {

		// Check dependencies
		$dependencies = array(
			'porfatolis-create-site' => array(
				'check' => 'portafolis-create-site/create_site.php',
				'notice'     => sprintf(__('%sFolio Super Agora%s requires %sPortafolis Create Site%s to be installed & activated!', 'folio-super-agora'), '<strong>', '</strong>', '<strong>', '</strong>'),
			),
		);
		$dependency_error = array();
		$dependency_check = true;

        foreach ($dependencies as $dependency) {
            if ( ! is_plugin_active( $dependency['check'] ) ) {
                $dependency_error[] = $dependency['notice'];
                $dependency_check = false;
            }
        }

        if ($dependency_check === false) {
			wp_die( '<div class="error"><p>' . implode( '<br>', $dependency_error ) . '</p><p><a href="javascript:history.back()" class="button">&laquo; '.__('Back to previos page', 'folio-super-agora') .'</a></p></div>');
        }

		// Flush rewrite rules
		/*
		global $folio_super_agora;
		if (!$folio_super_agora){
			$folio_super_agora = self::get_instance();
		}
        flush_rewrite_rules();
		*/

	}


	/**
     * Fired during plugin deactivation
	 * 
	 * @since 	1.0.0
	 * @access 	public static
     */
	public static function deactivate() {
		//flush_rewrite_rules();
	}


	/**
	 * Init function to register plugin text domain, actions and filters
	 */
	public function __construct() {
		$this->includes();
		$this->hooks();
		$this->init_classes();
	}

	
	/**
     * Include plugin files
     *
     * @access      private
     * @since       1.0.0
     * @return      void
     */
    private function includes() {

		// Custom checkbox list walker
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-check-walker.php';

		// Plubin main classes
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-base.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-data.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-settings.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-network.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-network-list.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-supertags.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-posts.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-redirect.php';

		// Plugin widgets
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-widget-activities.php';
        require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-widget-users.php';
		require_once FOLIO_SUPER_AGORA_PATH . 'classes/class-folio-super-agora-widget-supertags.php';
    }


	/**
     * Setup plugin hooks
     *
     * @access      private
     * @since       1.0.0
     * @return      void
     */
    private function hooks() {

        // Localization
        add_action( 'init',			[$this, 'localization'] );

		// Register widgets
		add_action( 'widgets_init', [$this, 'register_widgets'] );

    }


	/**
     * Init all classes
	 * 
	 * @since       1.0.0
     * @return      void
     */
    public function init_classes() {

		if ( is_super_admin() ){
			new Folio_Super_Agora_Data();
		}

		if( is_admin() && ! ucs_is_classroom_blog() && ! uoc_create_site_is_student_blog() ){
			new Folio_Super_Agora_Settings();
		}

		if( is_admin() && is_multisite() ){
			new Folio_Super_Agora_Network();
		}

		if (FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS){
			new Folio_Super_Agora_Super_Tags();
		}

		new Folio_Super_Agora_Posts();
		new Folio_Super_Agora_Redirect();
	
	}

	/**
	 * Register widgets
	 * 
	 * @since       1.0.0
     * @return      void
     */
	public function register_widgets () {
		$settings = get_option( 'folio_superagora_settings', false );

		// Is SuperAgora
		if ( $settings ) {
			register_widget('Folio_Super_Agora_Widget_Activities');
			register_widget('Folio_Super_Agora_Widget_Users');
		}

		// Is SuperAgora or Ágora
		if ( FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS && ( $settings || ucs_is_classroom_blog() ) ) {
			register_widget('Folio_Super_Agora_Widget_Super_Tags');
		}
	}


    /**
     * Initialize plugin for localization
     *
     * @since 1.0.0
     * 
     * @uses load_plugin_textdomain()
     */
    public function localization() {
		load_plugin_textdomain( 'folio-super-agora', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

}

register_activation_hook( __FILE__, array( 'Folio_Super_Agora', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Folio_Super_Agora', 'deactivate' ) );

add_action( 'plugins_loaded', 'folio_super_agora_instantiate' );

$folio_super_agora_instance = null;

/**
 * Instantiation aux method
 */
function folio_super_agora_instantiate() {
	global $folio_super_agora_instance;
	$folio_super_agora_instance = Folio_Super_Agora::get_instance();
}
