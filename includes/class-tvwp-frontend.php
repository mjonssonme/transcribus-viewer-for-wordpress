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
        // --- FIX: Add the frontend enqueue hook BACK ---
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // --- END FIX ---

        add_filter( 'template_include', [ $this, 'template_takeover' ] );
        $this->register_shortcode();
    }

    /**
     * Enqueue assets for the public frontend.
     */
    public function enqueue_assets() {
        // This is for the CPT's own page
        if ( is_singular( 'transkribus_document' ) ) {
            wp_enqueue_style( 'tvwp-viewer-css' );
            wp_enqueue_script( 'tvwp-viewer-js' );
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

    public function register_shortcode() {
        add_shortcode( 'transkribus_viewer', [ $this, 'render_shortcode' ] );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
        ], $atts, 'transkribus_viewer' );

        $post_id = (int) $atts['id'];
        if ( ! $post_id || get_post_type( $post_id ) !== 'transkribus_document' ) {
            return '<p><em>Transcribus Viewer: Invalid document ID.</em></p>';
        }

        // Manually load assets (this is what makes it work in shortcodes)
        wp_enqueue_style( 'tvwp-viewer-css' );
        wp_enqueue_script( 'tvwp-viewer-js' );

        $doc = get_post( $post_id );
        $description = apply_filters( 'the_content', $doc->post_content );

        return '<div class="tvwp-shortcode-wrapper">' . $description . self::render_viewer_markup( $post_id ) . '</div>';
    }

    /**
     * Shared viewer markup, used both by the shortcode/block renderer and the CPT's own
     * single-document template so the two can't drift out of sync.
     */
    public static function render_viewer_markup( $post_id ) {
        return sprintf(
            '<div id="tvwp-viewer-%1$d" class="tvwp-viewer" data-post-id="%1$d">
                <div class="tvwp-controls">
                    <button class="tvwp-nav" data-nav-skip="-5" title="5 pages back">-5</button>
                    <button class="tvwp-nav" data-nav-step="-1" title="Previous page">Previous</button>
                    <span class="tvwp-page-display">
                        Page
                        <select class="tvwp-nav-jump" title="Jump to page"></select>
                        of
                        <span class="tvwp-total-pages">...</span>
                    </span>
                    <button class="tvwp-nav" data-nav-step="1" title="Next page">Next</button>
                    <button class="tvwp-nav" data-nav-skip="5" title="5 pages forward">+5</button>
                </div>
                <div class="tvwp-main-content">
                    <div class="tvwp-image-pane">
                        <div class="tvwp-image-wrapper">
                            <img class="tvwp-image" src="" alt="Transcribed page image" />
                            <svg class="tvwp-overlay" xmlns="http://www.w3.org/2000/svg"></svg>
                        </div>
                    </div>
                    <div class="tvwp-text-pane">
                        </div>
                </div>
            </div>',
            esc_attr( $post_id )
        );
    }
}