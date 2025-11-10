<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class TVWP_Gutenberg
 *
 * Handles all Gutenberg block registration.
 */
class TVWP_Gutenberg {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
    }

    /**
     * V1.2.1: Load our viewer's JS and CSS inside the block editor
     */
    public function enqueue_editor_assets() {
        // --- THIS IS THE FIX ---
        // We only want to load our viewer script on "Pages" or "Posts",
        // NOT on our CPT's own edit screen, as it causes a conflict.
        
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

        // Do not load on our CPT's edit screen
        if ( get_post_type() === 'transkribus_document' ) {
            return;
        }
        // --- END FIX ---

        // Enqueue the assets (which are registered globally on 'init')
        wp_enqueue_style( 'tvwp-viewer-css' );
        wp_enqueue_script( 'tvwp-viewer-js' );
    }

    public function register_blocks() {
        // Register the "Transcribus Document" block
        register_block_type( TVWP_PLUGIN_DIR . 'build', [
            'render_callback' => [ $this, 'render_document_block' ],
        ] );
    }

    /**
     * Render callback for the 'tvwp/document-viewer' block.
     */
    public function render_document_block( $attributes ) {
        $document_id = $attributes['documentId'] ?? 0;

        if ( ! $document_id ) {
            return '<p><em>' . __( 'Please select a document from the block settings.', 'tvwp' ) . '</em></p>';
        }

        // Re-use our existing shortcode function
        return TVWP_Frontend::get_instance()->render_shortcode( [
            'id' => $document_id,
        ] );
    }
}