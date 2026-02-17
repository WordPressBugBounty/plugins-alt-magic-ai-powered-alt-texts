<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alt Magic Redirection Handler
 * 
 * This file handles redirections using the Redirection plugin
 * when images are renamed to maintain SEO and prevent broken links
 */

// Define the Redirection plugin constant
define("ALTM_PLUGIN_REDIRECTION", "redirection/redirection.php");

/**
 * Check if the Redirection plugin is active and available
 * 
 * @return bool True if Redirection plugin is available, false otherwise
 */
function altm_is_redirection_enabled() {
    // Check if Redirection plugin is active
    if (!is_plugin_active(ALTM_PLUGIN_REDIRECTION)) {
        return false;
    }
    
    // Check if Red_Item class exists (Redirection plugin loaded)
    if (!class_exists('Red_Item')) {
        return false;
    }
    
    return true;
}

/**
 * Add a redirection from old image URL to new image URL
 * 
 * @param string $old_filename The old filename without extension
 * @param string $new_filename The new filename without extension  
 * @param string $extension The file extension
 * @param string $file_subfolder The upload subfolder (e.g., '2024/01/')
 * @param bool $option_create_redirection Whether to create the redirection
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function altm_add_redirection($old_filename, $new_filename, $extension, $file_subfolder, $option_create_redirection) {
    // Check if redirection should be created
    if (!$option_create_redirection) {
        altm_log("Redirection creation disabled - skipping");
        return true;
    }
    
    // Check if Redirection plugin is available
    if (!altm_is_redirection_enabled()) {
        altm_log("Redirection plugin not available - skipping redirection creation");
        return new WP_Error('redirection_plugin_not_found', 'Redirection plugin is not active or available');
    }
    
    // Build the URLs
    $upload_dir = wp_upload_dir();
    $base_url = rtrim($upload_dir['baseurl'], '/'); // Remove trailing slash
    
    // Ensure file_subfolder has proper format
    $file_subfolder = trim($file_subfolder, '/');
    if (!empty($file_subfolder)) {
        $file_subfolder = '/' . $file_subfolder . '/';
    } else {
        $file_subfolder = '/';
    }
    
    // Build old and new URLs
    $old_url = $base_url . $file_subfolder . $old_filename . '.' . $extension;
    $new_url = $base_url . $file_subfolder . $new_filename . '.' . $extension;
    
    altm_log("URL Debug - Base URL: '$base_url', Subfolder: '$file_subfolder'");
    altm_log("Creating redirection - Old: '$old_url' → New: '$new_url'");
    
    // Prepare redirection details
    $details = array(
        'url'            => $old_url,                    // OLD URL
        'action_data'    => array('url' => $new_url),   // NEW URL
        'action_type'    => 'url',
        'title'          => 'Alt Magic Image Rename',
        'status'         => 'enabled',
        'regex'          => false,
        'group_id'       => 2,                          // Default group ID
        'match_type'     => 'url',
    );
    
    try {
        // Create the redirection using Redirection plugin
        $result = Red_Item::create($details);
        
        if (is_wp_error($result)) {
            altm_log("Failed to create redirection - Error: " . $result->get_error_message());
            return $result;
        } elseif ($result) {
            // Check if result has get_id method (Red_Item object)
            if (is_object($result) && method_exists($result, 'get_id')) {
                altm_log("Redirection created successfully - ID: " . $result->get_id());
            } else {
                altm_log("Redirection created successfully - Result: " . print_r($result, true));
            }
            return true;
        } else {
            altm_log("Failed to create redirection - Red_Item::create returned false");
            return new WP_Error('redirection_creation_failed', 'Failed to create redirection');
        }
    } catch (Exception $e) {
        altm_log("Exception while creating redirection: " . $e->getMessage());
        return new WP_Error('redirection_creation_exception', 'Exception while creating redirection: ' . $e->getMessage());
    }
}

/**
 * Add redirection for GUID changes (when GUID is updated)
 * 
 * @param string $old_guid The old GUID URL
 * @param string $new_guid The new GUID URL
 * @param bool $option_create_redirection Whether to create the redirection
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function altm_add_guid_redirection($old_guid, $new_guid, $option_create_redirection) {
    // Check if redirection should be created
    if (!$option_create_redirection) {
        altm_log("GUID redirection creation disabled - skipping");
        return true;
    }
    
    // Check if Redirection plugin is available
    if (!altm_is_redirection_enabled()) {
        altm_log("Redirection plugin not available - skipping GUID redirection creation");
        return new WP_Error('redirection_plugin_not_found', 'Redirection plugin is not active or available');
    }
    
    altm_log("Creating GUID redirection - Old: '$old_guid' → New: '$new_guid'");
    
    // Prepare redirection details for GUID
    $details = array(
        'url'            => $old_guid,                    // OLD GUID URL
        'action_data'    => array('url' => $new_guid),   // NEW GUID URL
        'action_type'    => 'url',
        'title'          => 'Alt Magic GUID Update',
        'status'         => 'enabled',
        'regex'          => false,
        'group_id'       => 2,                          // Default group ID
        'match_type'     => 'url',
    );
    
    try {
        // Create the redirection using Redirection plugin
        $result = Red_Item::create($details);
        
        if (is_wp_error($result)) {
            altm_log("Failed to create GUID redirection - Error: " . $result->get_error_message());
            return $result;
        } elseif ($result) {
            // Check if result has get_id method (Red_Item object)
            if (is_object($result) && method_exists($result, 'get_id')) {
                altm_log("GUID redirection created successfully - ID: " . $result->get_id());
            } else {
                altm_log("GUID redirection created successfully - Result: " . print_r($result, true));
            }
            return true;
        } else {
            altm_log("Failed to create GUID redirection - Red_Item::create returned false");
            return new WP_Error('guid_redirection_creation_failed', 'Failed to create GUID redirection');
        }
    } catch (Exception $e) {
        altm_log("Exception while creating GUID redirection: " . $e->getMessage());
        return new WP_Error('guid_redirection_creation_exception', 'Exception while creating GUID redirection: ' . $e->getMessage());
    }
}

/**
 * Get redirection group ID for Alt Magic redirections
 * Creates a group if it doesn't exist
 * 
 * @return int The group ID for Alt Magic redirections
 */
function altm_get_redirection_group_id() {
    // Check if Redirection plugin is available
    if (!altm_is_redirection_enabled()) {
        return 2; // Default group ID
    }
    
    // Try to find existing Alt Magic group
    $groups = Red_Group::get_all();
    foreach ($groups as $group) {
        if ($group->name === 'Alt Magic Image Rename') {
            return $group->get_id();
        }
    }
    
    // Create new group if not found
    try {
        $group = Red_Group::create('Alt Magic Image Rename', 1); // 1 = enabled
        if ($group) {
            altm_log("Created new redirection group: Alt Magic Image Rename (ID: " . $group->get_id() . ")");
            return $group->get_id();
        }
    } catch (Exception $e) {
        altm_log("Failed to create redirection group: " . $e->getMessage());
    }
    
    // Fallback to default group ID
    return 2;
}


?>
