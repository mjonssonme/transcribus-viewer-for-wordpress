<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TVWP_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'template_include', [ $this, 'template_takeover' ] );
    }

    public function enqueue_assets() {
        // Only load assets on our CPT's single page
        if ( is_singular( 'transkribus_document' ) ) {
            
            wp_enqueue_style(
                'tvwp-viewer-css',
                TVWP_PLUGIN_URL . 'assets/css/tvwp-viewer.css',
                [],
                TVWP_VERSION
            );
            
            wp_enqueue_script(
                'tvwp-viewer-js',
                TVWP_PLUGIN_URL . 'assets/js/tvwp-viewer.js',
                [], // dependencies
                TVWP_VERSION,
                true // in footer
            );

            // Pass data to JavaScript
            wp_localize_script( 'tvwp-viewer-js', 'tvwp_data', [
                'rest_url' => esc_url_raw( rest_url() ),
                'post_id'  => get_the_ID(),
                'nonce'    => wp_create_nonce( 'wp_rest' ), // For future use
                'i18n'     => [
                    'loading' => __( 'Loading...', 'tvwp' ),
                    'page'    => __( 'Page', 'tvwp' ),
                    'of'      => __( 'of', 'tvwp' ),
                ]
            ] );
        }
    }

    public function template_takeover( $template ) {
        if ( is_singular( 'transkribus_document' ) ) {
            $new_template = TVWP_PLUGIN_DIR . 'templates/single-transkribus_document.php';
            if ( file_exists( $new_template ) ) {
                return $new_template;
            }
        }
        return $template;
    }
}