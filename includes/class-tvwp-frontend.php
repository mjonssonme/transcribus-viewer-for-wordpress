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
        
        // V1.1: Register the shortcode
        $this->register_shortcode();
    }

    /**
     * Register our styles and scripts, but do not enqueue them.
     * They will be enqueued on-demand by the template or shortcode.
     */
    public function enqueue_assets() {
        wp_register_style(
            'tvwp-viewer-css',
            TVWP_PLUGIN_URL . 'assets/css/tvwp-viewer.css',
            [],
            TVWP_VERSION
        );
        
        wp_register_script(
            'tvwp-viewer-js',
            TVWP_PLUGIN_URL . 'assets/js/tvwp-viewer.js',
            [],
            TVWP_VERSION,
            true
        );

        // This global data is needed by all viewer instances
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
     * Take over the single CPT page, and load assets for it.
     */
    public function template_takeover( $template ) {
        if ( is_singular( 'transkribus_document' ) ) {
            
            // Manually load assets for this page
            wp_enqueue_style( 'tvwp-viewer-css' );
            wp_enqueue_script( 'tvwp-viewer-js' );

            $new_template = TVWP_PLUGIN_DIR . 'templates/single-transkribus_document.php';
            if ( file_exists( $new_template ) ) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * V1.1: Register the [transkribus_viewer] shortcode
     */
    public function register_shortcode() {
        add_shortcode( 'transkribus_viewer', [ $this, 'render_shortcode' ] );
    }

    /**
     * V1.1: Render the shortcode
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
        ], $atts, 'transkribus_viewer' );

        $post_id = (int) $atts['id'];
        if ( ! $post_id || get_post_type( $post_id ) !== 'transkribus_document' ) {
            return '<p><em>Transcribus Viewer: Invalid document ID.</em></p>';
        }

        // Manually load assets for this shortcode
        wp_enqueue_style( 'tvwp-viewer-css' );
        wp_enqueue_script( 'tvwp-viewer-js' );

        // Get the post content (the description)
        $doc = get_post( $post_id );
        $description = apply_filters( 'the_content', $doc->post_content );

        // Get the HTML for the viewer
        // Note: We use a unique ID for each viewer on the page
        $viewer_html = sprintf(
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

        // Return the full block: Description + Viewer
        return '<div class="tvwp-shortcode-wrapper">' . $description . $viewer_html . '</div>';
    }
}