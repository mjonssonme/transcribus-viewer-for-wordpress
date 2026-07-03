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

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'admin_menu', [ $this, 'remove_add_new_submenu' ], 999 );
    }

    /**
     * The ZIP upload flow is the only supported way to create a Transkribus
     * document - a manually created blank post via "Add New" has no page data
     * attached and would just be confusing, so hide that default submenu item.
     */
    public function remove_add_new_submenu() {
        remove_submenu_page( 'edit.php?post_type=transkribus_document', 'post-new.php?post_type=transkribus_document' );
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