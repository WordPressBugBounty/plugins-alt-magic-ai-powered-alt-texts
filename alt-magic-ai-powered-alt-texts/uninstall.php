<?php

// Ensure this file is not accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the main plugin file to get constants
require_once dirname(__FILE__) . '/altm-main-file.php';

// Define the server endpoint for plugin events
define('ALT_MAGIC_PLUGIN_EVENTS_ENDPOINT', '/wp-plugin-events');

/**
 * Send plugin deletion event ping to server
 * This function is called when the plugin is deleted (uninstalled)
 */
function altm_send_plugin_deletion_ping() {
    // Get site information
    $site_url = get_site_url();
    
    // Get user_id if available
    $user_id = get_option('alt_magic_user_id');
    
    // Prepare the request data (trimmed to essential fields only)
    $request_data = array(
        'event_type' => 'deleted',
        'plugin_version' => ALT_MAGIC_PLUGIN_VERSION,
        'site_url' => $site_url
    );
    
    // Add user_id if available
    if (!empty($user_id)) {
        $request_data['user_id'] = $user_id;
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
        'blocking' => false, // Non-blocking request to avoid slowing down uninstall
        'httpversion' => '1.1',
        'sslverify' => true
    );

    // Send the ping
    $response = wp_remote_post($ping_url, $args);

    if (is_wp_error($response)) {
        // Log error if possible (though logging might not be available during uninstall)
        altm_log('Alt Magic: Failed to send plugin deletion ping: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 200 && $response_code < 300) {
            altm_log('Alt Magic: Successfully sent plugin deletion ping');
        } else {
            altm_log('Alt Magic: Plugin deletion ping returned status code: ' . $response_code);
        }
    }
}

// Send the deletion ping
altm_send_plugin_deletion_ping();

// Clean up plugin options (optional - uncomment if you want to remove all plugin data)
/*
$options_to_remove = [
    'alt_magic_account_active',
    'alt_magic_api_key',
    'alt_magic_user_id',
    'alt_magic_language',
    'alt_magic_use_for_title',
    'alt_magic_use_for_caption',
    'alt_magic_use_for_description',
    'alt_magic_prepend_string',
    'alt_magic_append_string',
    'alt_magic_auto_generate',
    'alt_magic_auto_rename_on_upload',
    'alt_magic_use_seo_keywords',
    'alt_magic_use_post_title',
    'alt_magic_refresh_alt_text',
    'alt_magic_private_site',
    'alt_magic_woocommerce_use_product_name',
    'alt_magic_rename_use_seo_keywords',
    'alt_magic_rename_use_post_title',
    'alt_magic_rename_use_woocommerce_product_name',
    'alt_magic_max_concurrency'
];

foreach ($options_to_remove as $option) {
    delete_option($option);
}
*/

?>
