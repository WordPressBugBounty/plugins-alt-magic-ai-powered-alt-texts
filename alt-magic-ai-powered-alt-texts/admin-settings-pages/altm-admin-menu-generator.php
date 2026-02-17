<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// Add plugin settings page
function altm_add_admin_menu() {
    $icon_url = plugin_dir_url(__FILE__) . '../assets/alt-magic-logo-2.svg';
    
    add_menu_page(
        'Alt Magic Settings',  // Page title
        'Alt Magic',       // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic',           // Menu slug
        'alt_magic_render_settings_page',  // Callback function to render the page content
        $icon_url,             // Icon URL
        80                     // Position in the menu order
    );

    add_submenu_page(
        'alt-magic',           // Parent menu slug
        'Account Settings',    // Page title
        'Account Settings',    // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic',           // Menu slug
        'alt_magic_render_settings_page'  // Callback function to render the page content
    );

    add_submenu_page(
        'alt-magic',           // Parent menu slug
        'AI Settings',         // Page title
        'AI Settings',         // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic-ai-settings', // Menu slug
        'alt_magic_render_ai_settings_page'  // Callback function to render the page content
    );

    add_submenu_page(
        'alt-magic',           // Parent menu slug
        'Generate Alt Text',   // Page title
        'Bulk Alt Generation',   // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic-bulk-generation',  // Menu slug - keeping original permalink
        'altm_render_image_processing_page'  // Callback function to render the page content
    );

    add_submenu_page(
        'alt-magic',           // Parent menu slug
        'Rename Images',       // Page title
        'Rename Images',       // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic-image-renaming',  // Menu slug
        'altm_render_image_renaming_page'  // Callback function to render the page content
    );

    add_submenu_page(
        'alt-magic',           // Parent menu slug
        'Processed Images',    // Page title
        'Processed Images',    // Menu title
        'manage_options',      // Capability required to access the page
        'alt-magic-processed-images',  // Menu slug
        'altm_render_processed_images_page'  // Callback function to render the page content
    );

    add_submenu_page(
        'alt-magic',
        'Alt Magic Help',
        'Alt Magic Help',
        'manage_options',
        'alt-magic-help',
        'alt_magic_render_help_page'
    );
}
add_action( 'admin_menu', 'altm_add_admin_menu' );
