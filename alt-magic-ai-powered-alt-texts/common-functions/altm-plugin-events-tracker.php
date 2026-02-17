<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Events Tracker
 * Sends ping to server for plugin activation, deactivation, installation, and deletion events
 */

// Define the server endpoint for plugin events
define('ALT_MAGIC_PLUGIN_EVENTS_ENDPOINT', '/wp-plugin-events');

/**
 * Get current WordPress user email
 * 
 * @return string|null User email if available, null otherwise
 */
function altm_get_current_user_email() {
    $current_user = wp_get_current_user();
    
    if ($current_user && $current_user->ID > 0 && !empty($current_user->user_email)) {
        return $current_user->user_email;
    }
    
    // Fallback: try to get admin email from site settings
    $admin_email = get_option('admin_email');
    if (!empty($admin_email)) {
        return $admin_email;
    }
    
    return null;
}

/**
 * Send plugin event ping to server
 * 
 * @param string $event_type The type of event (activated, reactivated, deactivated, installed, deleted)
 * @return bool True if ping was sent successfully, false otherwise
 */
function altm_send_plugin_event_ping($event_type) {
    // Validate event type
    $valid_events = ['activated', 'reactivated', 'deactivated', 'installed', 'deleted'];
    if (!in_array($event_type, $valid_events)) {
        return false;
    }

    // Get site information
    $site_url = get_site_url();
    
    // Get user_id if available
    $user_id = get_option('alt_magic_user_id');
    
    // Get current user email
    $user_email = altm_get_current_user_email();
    
    // Prepare the request data (trimmed to essential fields only)
    $request_data = array(
        'event_type' => $event_type,
        'plugin_version' => ALT_MAGIC_PLUGIN_VERSION,
        'site_url' => $site_url
    );
    
    // Add user_id if available
    if (!empty($user_id)) {
        $request_data['user_id'] = $user_id;
    }
    
    // Add user email if available
    if (!empty($user_email)) {
        $request_data['wp_login_email'] = $user_email;
    }

    // Get the base URL for the ping
    $base_url = ALT_MAGIC_API_BASE_URL;
    $ping_url = $base_url . ALT_MAGIC_PLUGIN_EVENTS_ENDPOINT;

    // Prepare the request arguments
    $args = array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Alt-Magic-WordPress-Plugin/' . ALT_MAGIC_PLUGIN_VERSION
        ),
        'body' => wp_json_encode($request_data),
        'timeout' => 30,
        'blocking' => false, // Non-blocking request to avoid slowing down plugin operations
        'httpversion' => '1.1',
        'sslverify' => true
    );

    // Send the ping
    $response = wp_remote_post($ping_url, $args);

    if (is_wp_error($response)) {
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 200 && $response_code < 300) {
        return true;
    } else {
        return false;
    }
}

/**
 * Check if plugin is being installed for the first time
 * This function checks if the plugin options exist to determine if it's a fresh installation
 * 
 * @return bool True if this is a fresh installation, false otherwise
 */
function altm_is_fresh_installation() {
    // Check if any of the main plugin options exist
    $main_options = [
        'alt_magic_account_active',
        'alt_magic_api_key',
        'alt_magic_user_id',
        'alt_magic_language'
    ];

    foreach ($main_options as $option) {
        if (get_option($option) !== false) {
            return false; // At least one option exists, not a fresh installation
        }
    }

    return true; // No options exist, this is a fresh installation
}

/**
 * Handle plugin activation event
 */
function altm_handle_plugin_activation() {
    // Check if this is a fresh installation
    if (altm_is_fresh_installation()) {
        altm_send_plugin_event_ping('installed');
    } else {
        altm_send_plugin_event_ping('reactivated');
    }
}

/**
 * Handle plugin deactivation event
 */
function altm_handle_plugin_deactivation() {
    altm_send_plugin_event_ping('deactivated');
}

/**
 * Handle plugin deletion event
 * This function is called when the plugin is deleted (uninstalled)
 */
function altm_handle_plugin_deletion() {
    altm_send_plugin_event_ping('deleted');
}

/**
 * Add plugin event tracking hooks
 */
function altm_setup_plugin_event_tracking() {
    // Hook into plugin activation
    add_action('alt_magic_plugin_activated', 'altm_handle_plugin_activation');
    
    // Hook into plugin deactivation
    add_action('alt_magic_plugin_deactivated', 'altm_handle_plugin_deactivation');
    
    // Hook into plugin deletion (uninstall)
    register_uninstall_hook(ALT_MAGIC_PLUGIN_FILE, 'altm_handle_plugin_deletion');
}

// Initialize plugin event tracking immediately (not on init)
altm_setup_plugin_event_tracking();


?>
