<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TVWP_Rest_Api {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Endpoint for Table of Contents (page count)
        register_rest_route( 'tvwp/v1', '/document/(?P<post_id>\d+)/toc', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_toc' ],
            'args'     => [
                'post_id' => [
                    // --- THIS IS THE FIX ---
                    // 'validate_callback' => 'is_numeric', // This line was causing the crash
                    // --- END FIX ---
                    'required'          => true,
                ],
            ],
            'permission_callback' => '__return_true', // Public
        ] );

        // Endpoint for individual page data
        register_rest_route( 'tvwp/v1', '/document/(?P<post_id>\d+)/page/(?P<page_num>\d+)', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_page_data' ],
            'args'     => [
                'post_id' => [
                    'required' => true,
                ],
                'page_num' => [
                    // --- THIS IS THE FIX ---
                    // 'validate_callback' => 'is_numeric', // This line was also broken
                    // --- END FIX ---
                    'required' => true,
                ],
            ],
            'permission_callback' => '__return_true', // Public
        ] );
    }

    public function get_toc( $request ) {
        $post_id = (int) $request['post_id'];

        if ( ! $this->is_publicly_readable( $post_id ) ) {
            return new WP_Error( 'tvwp_no_toc', 'Document data not found.', [ 'status' => 404 ] );
        }

        $page_count = get_post_meta( $post_id, '_page_count', true );

        if ( ! $page_count ) {
            return new WP_Error( 'tvwp_no_toc', 'Document data not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( [ 'page_count' => (int) $page_count ], 200 );
    }

    public function get_page_data( $request ) {
        $post_id = (int) $request['post_id'];
        $page_num = (int) $request['page_num'];

        if ( ! $this->is_publicly_readable( $post_id ) ) {
            return new WP_Error( 'tvwp_no_page', 'Page data not found.', [ 'status' => 404 ] );
        }

        $page_data = get_post_meta( $post_id, '_page_data_' . $page_num, true );

        if ( ! $page_data ) {
            return new WP_Error( 'tvwp_no_page', 'Page data not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $page_data, 200 );
    }

    /**
     * Only publicly published documents are readable via these unauthenticated endpoints;
     * drafts/processing/failed documents must not leak content before the owner publishes them.
     */
    private function is_publicly_readable( $post_id ) {
        if ( get_post_type( $post_id ) !== 'transkribus_document' ) {
            return false;
        }
        return get_post_status( $post_id ) === 'publish';
    }
}