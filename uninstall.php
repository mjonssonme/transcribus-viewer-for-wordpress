<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// We will NOT delete the CPT posts or data by default.
// This is a safeguard. If you want to delete all data,
// you would add a "Delete Data on Uninstall" option in the plugin.
// For V1.0, we do nothing to protect the user's data.

// Example: How you *would* delete posts if you wanted to:
/*
global $wpdb;

$posts = get_posts( [
    'post_type'   => 'transkribus_document',
    'numberposts' => -1,
    'post_status' => 'any',
] );

foreach ( $posts as $post ) {
    // True = bypass trash and delete permanently
    wp_delete_post( $post->ID, true );
}

// Flush rewrite rules
flush_rewrite_rules();
*/