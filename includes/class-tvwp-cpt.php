<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class TVWP_CPT
 *
 * Registers the Custom Post Type.
 */
class TVWP_CPT {

    private static $instance = null;

    /**
     * Lazily-built map of document post ID => array of published post IDs that
     * embed it (via block or shortcode), cached for the life of the request so
     * the admin list table only computes it once regardless of row count.
     */
    private $usage_map = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'admin_menu', [ $this, 'remove_add_new_submenu' ], 999 );
        add_action( 'before_delete_post', [ $this, 'cleanup_document_resources' ] );

        add_filter( 'manage_transkribus_document_posts_columns', [ $this, 'add_usage_column' ] );
        add_action( 'manage_transkribus_document_posts_custom_column', [ $this, 'render_usage_column' ], 10, 2 );
    }

    /**
     * The ZIP upload flow is the only supported way to create a Transkribus
     * document - a manually created blank post via "Add New" has no page data
     * attached and would just be confusing, so hide that default submenu item.
     */
    public function remove_add_new_submenu() {
        remove_submenu_page( 'edit.php?post_type=transkribus_document', 'post-new.php?post_type=transkribus_document' );
    }

    /**
     * Deletes attached media (page images) when a Transkribus document is permanently
     * deleted, regardless of its status, so removing a document doesn't leave orphaned
     * attachments behind. Postmeta (_page_data_*, _page_count) is cleaned up automatically
     * by WordPress core when the post row itself is deleted.
     */
    public function cleanup_document_resources( $post_id ) {
        if ( get_post_type( $post_id ) !== 'transkribus_document' ) {
            return;
        }

        $attachment_ids = get_posts( [
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ] );

        foreach ( $attachment_ids as $attachment_id ) {
            wp_delete_attachment( $attachment_id, true );
        }
    }

    /**
     * Adds a "Used In" column to the Transkribus Documents list table, so an admin
     * can see - before deleting a document - whether any post/page (published or
     * not) still embeds it via the block or the shortcode.
     */
    public function add_usage_column( $columns ) {
        $columns['tvwp_usage'] = __( 'Used In', 'tvwp' );
        return $columns;
    }

    public function render_usage_column( $column, $post_id ) {
        if ( $column !== 'tvwp_usage' ) {
            return;
        }

        $using_post_ids = $this->get_usage_map()[ (int) $post_id ] ?? [];

        if ( empty( $using_post_ids ) ) {
            echo '&#8212;';
            return;
        }

        $links = [];
        $has_published_usage = false;
        foreach ( $using_post_ids as $using_post_id ) {
            $status = get_post_status( $using_post_id );
            if ( $status === 'publish' ) {
                $has_published_usage = true;
            }
            $label = esc_html( get_the_title( $using_post_id ) );
            if ( $status !== 'publish' ) {
                $status_obj = get_post_status_object( $status );
                $label .= ' (' . esc_html( $status_obj ? $status_obj->label : $status ) . ')';
            }
            $links[] = '<a href="' . esc_url( get_edit_post_link( $using_post_id ) ) . '">' . $label . '</a>';
        }

        printf(
            '<span style="color:%s;font-weight:600;" title="%s">&#9888; %s</span>',
            $has_published_usage ? '#d63638' : '#996800',
            $has_published_usage
                ? esc_attr__( 'Deleting this document will break these published posts.', 'tvwp' )
                : esc_attr__( 'This document is referenced by unpublished posts - deleting it will break them once published.', 'tvwp' ),
            implode( ', ', $links )
        );
    }

    /**
     * Scans posts (any real, non-trashed status) for the tvwp/document-viewer block
     * or the [transkribus_viewer] shortcode, mapping each referenced document ID to
     * the posts that use it. Computed once per request and cached.
     */
    private function get_usage_map() {
        if ( $this->usage_map !== null ) {
            return $this->usage_map;
        }

        global $wpdb;

        $this->usage_map = [];

        $candidates = $wpdb->get_results(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_status IN ( 'publish', 'draft', 'pending', 'private', 'future' )
             AND ( post_content LIKE '%tvwp/document-viewer%' OR post_content LIKE '%transkribus_viewer%' )"
        );

        foreach ( $candidates as $candidate ) {
            foreach ( $this->extract_referenced_document_ids( $candidate->post_content ) as $document_id ) {
                $this->usage_map[ $document_id ][] = (int) $candidate->ID;
            }
        }

        return $this->usage_map;
    }

    private function extract_referenced_document_ids( $content ) {
        $ids = [];

        if ( has_block( 'tvwp/document-viewer', $content ) ) {
            foreach ( parse_blocks( $content ) as $block ) {
                $this->collect_block_document_ids( $block, $ids );
            }
        }

        if ( has_shortcode( $content, 'transkribus_viewer' ) ) {
            if ( preg_match_all( '/' . get_shortcode_regex( [ 'transkribus_viewer' ] ) . '/s', $content, $matches ) ) {
                foreach ( $matches[3] as $atts_string ) {
                    $atts = shortcode_parse_atts( $atts_string );
                    if ( isset( $atts['id'] ) ) {
                        $ids[] = (int) $atts['id'];
                    }
                }
            }
        }

        return array_unique( $ids );
    }

    private function collect_block_document_ids( $block, array &$ids ) {
        if ( isset( $block['blockName'], $block['attrs']['documentId'] ) && $block['blockName'] === 'tvwp/document-viewer' ) {
            $ids[] = (int) $block['attrs']['documentId'];
        }

        if ( ! empty( $block['innerBlocks'] ) ) {
            foreach ( $block['innerBlocks'] as $inner_block ) {
                $this->collect_block_document_ids( $inner_block, $ids );
            }
        }
    }

    public function register_cpt() {
        $labels = [
            'name'                  => _x( 'Transkribus Documents', 'Post Type General Name', 'tvwp' ),
            'singular_name'         => _x( 'Transkribus Document', 'Post Type Singular Name', 'tvwp' ),
            'menu_name'             => __( 'Transkribus Docs', 'tvwp' ),
            'name_admin_bar'        => __( 'Transkribus Document', 'tvwp' ),
            'archives'              => __( 'Document Archives', 'tvwp' ),
            'all_items'             => __( 'All Documents', 'tvwp' ),
            'add_new_item'          => __( 'Add New Document', 'tvwp' ),
            'add_new'               => __( 'Add New', 'tvwp' ),
            'new_item'              => __( 'New Document', 'tvwp' ),
            'edit_item'             => __( 'Edit Document', 'tvwp' ),
            'update_item'           => __( 'Update Document', 'tvwp' ),
            'view_item'             => __( 'View Document', 'tvwp' ),
            'view_items'            => __( 'View Documents', 'tvwp' ),
            'search_items'          => __( 'Search Document', 'tvwp' ),
            'not_found'             => __( 'Not found', 'tvwp' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'tvwp' ),
            'insert_into_item'      => __( 'Insert into document', 'tvwp' ),
            'uploaded_to_this_item' => __( 'Uploaded to this document', 'tvwp' ),
            'items_list'            => __( 'Documents list', 'tvwp' ),
            'items_list_navigation' => __( 'Documents list navigation', 'tvwp' ),
            'filter_items_list'     => __( 'Filter documents list', 'tvwp' ),
        ];
        
        // Admin-only permissions
        $capabilities = [
            'edit_post'           => 'manage_options',
            'read_post'           => 'manage_options',
            'delete_post'         => 'manage_options',
            'edit_posts'          => 'manage_options',
            'edit_others_posts'   => 'manage_options',
            'publish_posts'       => 'manage_options',
            'read_private_posts'  => 'manage_options',
            'delete_posts'        => 'manage_options',
            'delete_private_posts'=> 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts'  => 'manage_options',
            'edit_published_posts'=> 'manage_options',
        ];

        $args = [
            'label'                 => __( 'Transkribus Document', 'tvwp' ),
            'description'           => __( 'A transcribed document from Transkribus.', 'tvwp' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor' ], // 'editor' will hold the full plain text
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-media-document',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => 'transkribus_documents',
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'capabilities'          => $capabilities,
            'rewrite'               => [ 'slug' => 'transkribus_document' ],
            'show_in_rest'          => true, // Important for REST API
        ];

        register_post_type( 'transkribus_document', $args );
    }
}