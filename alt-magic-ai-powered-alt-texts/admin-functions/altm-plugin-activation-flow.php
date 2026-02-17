<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) exit;


// Define the path to the main plugin file
define('ALT_MAGIC_PLUGIN_FILE', dirname(__DIR__) . '/altm-main-file.php');

// Hook into plugin activation
register_activation_hook(ALT_MAGIC_PLUGIN_FILE, 'alt_magic_activate');

function alt_magic_activate() {
    // Set a transient to trigger the redirect
    if (set_transient('_alt_magic_activation_redirect', true, 30)) {
        //altm_log('Transient set successfully.');
    } 
    // else {
    //     altm_log('Failed to set transient.');
    // }
    
    // Trigger plugin activation event
    do_action('alt_magic_plugin_activated');
}

// Hook into plugin deactivation
register_deactivation_hook(ALT_MAGIC_PLUGIN_FILE, 'alt_magic_deactivate');

function alt_magic_deactivate() {
    // Trigger plugin deactivation event
    do_action('alt_magic_plugin_deactivated');
}

// Hook into admin initialization to handle the redirect
add_action('admin_init', 'alt_magic_redirect_to_account_settings');

function alt_magic_redirect_to_account_settings() {
    // Check if the transient is set
    if (get_transient('_alt_magic_activation_redirect')) {
        //altm_log('Transient found, redirecting...');
        // Delete the transient
        delete_transient('_alt_magic_activation_redirect');

        // Redirect to the account settings page
        wp_safe_redirect(admin_url('admin.php?page=alt-magic'));
        exit;
    } 
    // else {
    //     altm_log('Transient not found.');
    // }
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(ALT_MAGIC_PLUGIN_FILE), 'alt_magic_add_settings_link');

function alt_magic_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=alt-magic-ai-settings') . '">' . __('AI Settings', 'alt-magic-ai-powered-alt-texts') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

?>