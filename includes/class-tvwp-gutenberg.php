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
        // enqueue_block_assets (not enqueue_block_editor_assets) specifically:
        // this is the hook the block editor actually mirrors into its iframed
        // canvas reliably - enqueue_block_editor_assets only guarantees the
        // asset loads in the top-level admin page. Confirmed via a live DOM
        // check that our stylesheet's rules weren't matching anything inside
        // the iframe at all (not overridden - simply absent), while
        // tvwp-viewer.js still ran fine (its own <script> tag isn't
        // iframe-scoped the same way a stylesheet's cascade is).
        add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_assets' ] );
    }

    /**
     * V1.2.1: Load our viewer's JS and CSS inside the block editor
     */
    public function enqueue_editor_assets() {
        // enqueue_block_assets also fires on the real frontend, where
        // TVWP_Frontend already enqueues these assets itself, conditionally,
        // only on pages that actually use the block/shortcode - avoid
        // enqueuing them unconditionally on every frontend page view here too.
        if ( ! is_admin() ) {
            return;
        }

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

        $height = ! empty( $attributes['customHeight'] ) ? ( $attributes['viewerHeight'] ?? 0 ) : 0;

        // The block editor's live preview (ServerSideRender) always renders this
        // block via the block-renderer REST endpoint - a real page render never
        // does. Without a custom height, bake a short fixed height directly into
        // the server-rendered markup for that case, rather than trying to detect
        // "am I in the editor" in JS after the fact: tvwp-viewer.js auto-initializes
        // any .tvwp-viewer it sees via its own MutationObserver, which could win a
        // race against a JS-set flag on a fresh block insertion, and its image-fit
        // default calculation (correct for the real page, but far too tall in the
        // editor's narrower canvas) can also retry asynchronously, re-triggering
        // the same race repeatedly. Deciding this here instead means there's no
        // JS timing to race at all - tvwp-viewer.js sees a real configured height
        // from the moment the element exists, before any script runs.
        if ( ! $height && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $height = 500;
        }

        // Re-use our existing shortcode function
        return TVWP_Frontend::get_instance()->render_shortcode( [
            'id'     => $document_id,
            'height' => $height,
        ] );
    }
}