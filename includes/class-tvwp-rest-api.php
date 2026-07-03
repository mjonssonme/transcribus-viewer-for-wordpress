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

        // Admin-only endpoint for the upload page to poll background-processing status.
        register_rest_route( 'tvwp/v1', '/document/(?P<post_id>\d+)/status', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_processing_status' ],
            'args'     => [
                'post_id' => [
                    'required' => true,
                ],
            ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function get_toc( $request ) {
        $post_id = (int) $request['post_id'];

        if ( ! $this->is_readable( $post_id ) ) {
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

        if ( ! $this->is_readable( $post_id ) ) {
            return new WP_Error( 'tvwp_no_page', 'Page data not found.', [ 'status' => 404 ] );
        }

        $page_data = get_post_meta( $post_id, '_page_data_' . $page_num, true );

        if ( ! $page_data ) {
            return new WP_Error( 'tvwp_no_page', 'Page data not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $page_data, 200 );
    }

    public function get_processing_status( $request ) {
        $post_id = (int) $request['post_id'];

        if ( get_post_type( $post_id ) !== 'transkribus_document' ) {
            return new WP_Error( 'tvwp_not_found', 'Document not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( [ 'status' => get_post_status( $post_id ) ], 200 );
    }

    /**
     * Published documents are readable by anyone (matches the public frontend/shortcode).
     * Unpublished documents (draft/processing/failed) are only readable by users who could
     * also edit the CPT (e.g. previewing before publishing) - never by anonymous requests,
     * which is what closed the original information-disclosure issue.
     */
    private function is_readable( $post_id ) {
        if ( get_post_type( $post_id ) !== 'transkribus_document' ) {
            return false;
        }
        if ( is_post_publicly_viewable( $post_id ) ) {
            return true;
        }
        return current_user_can( 'read_post', $post_id );
    }
}