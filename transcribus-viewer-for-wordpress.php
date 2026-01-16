<?php
/**
 * Plugin Name:       Transcribus Viewer for WordPress
 * Plugin URI:        https://github.com/mjonssonme/transcribus-viewer-for-wordpress
 * Description:       Uploads Transkribus exports (ZIP) and displays them in an interactive viewer.
 * Version:           1.3.0
 * Author:            Mattias Johnsson
 * Author URI:        https://mjonsson.me
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tvwp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define Plugin Constants
define( 'TVWP_VERSION', '1.3.0' );
define( 'TVWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TVWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The main plugin class.
 */
final class TVWP_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->add_hooks();
    }

    private function load_dependencies() {
        require_once TVWP_PLUGIN_DIR . 'includes/lib/action-scheduler/action-scheduler.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-cpt.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-admin-upload.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-async-processor.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-rest-api.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-frontend.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-admin-guide.php';
        require_once TVWP_PLUGIN_DIR . 'includes/class-tvwp-gutenberg.php';
    }

    private function add_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        add_action( 'init', [ $this, 'register_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_plugin_action_links' ] );
    }

    /**
     * Register scripts and styles centrally on 'init'.
     */
    public function register_assets() {
        wp_register_style(
            'tvwp-viewer-css',
            TVWP_PLUGIN_URL . 'assets/css/tvwp-viewer.css',
            [],
            TVWP_VERSION
        );
        
        wp_register_script(
            'tvwp-viewer-js',
            TVWP_PLUGIN_URL . 'assets/js/tvwp-viewer.js',
            [], // dependencies
            TVWP_VERSION,
            true // <-- THIS IS THE FIX. Set back to TRUE (footer)
        );

        // This global data is needed by all viewer instances, on admin and front-end
        wp_localize_script( 'tvwp-viewer-js', 'tvwp_data', [
            'rest_url' => esc_url_raw( rest_url() ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'i18n'     => [
                'loading' => __( 'Loading...', 'tvwp' ),
                'page'    => __( 'Page', 'tvwp' ),
                'of'      => __( 'of', 'tvwp' ),
                'loadingError' => __( 'Error loading document.', 'tvwp' ),
            ]
        ] );
    }
    
    /**
     * Enqueue assets on the public-facing frontend.
     */
    public function enqueue_frontend_assets() {
        // This is for the CPT's own page
        if ( is_singular( 'transkribus_document' ) ) {
            wp_enqueue_style( 'tvwp-viewer-css' );
            wp_enqueue_script( 'tvwp-viewer-js' );
        }
    }

    public function init_plugin() {
        TVWP_CPT::get_instance();
        TVWP_Admin_Upload::get_instance();
        TVWP_Async_Processor::get_instance();
        TVWP_Rest_Api::get_instance();
        TVWP_Frontend::get_instance();
        TVWP_Admin_Guide::get_instance();
        TVWP_Gutenberg::get_instance();
    }

    public function add_plugin_action_links( $links ) {
        $guide_link = '<a href="' . admin_url( 'edit.php?post_type=transkribus_document&page=tvwp-guide' ) . '">' . __( 'Guide', 'tvwp' ) . '</a>';
        array_unshift( $links, $guide_link );
        return $links;
    }
    
    public function activate() {
        TVWP_CPT::get_instance()->register_cpt();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Kick it off
TVWP_Plugin::get_instance();