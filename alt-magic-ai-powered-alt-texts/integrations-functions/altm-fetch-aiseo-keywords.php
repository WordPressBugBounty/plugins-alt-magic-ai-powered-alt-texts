<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress file to use is_plugin_active()
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

/**
 * Check if AIOSEO plugin is active
 *
 * @return bool True if AIOSEO plugin is active, false otherwise
 */
function altm_is_aiseo_active() {
    return is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || 
           is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php');
}

/**
 * Get keywords from AIOSEO plugin for a content
 *
 * @param int $content_id The ID of the content
 * @return string Comma-separated list of keywords
 */
function altm_get_aiseo_keywords($content_id = 0) {
    // Exit if AIOSEO is not active
    if (!altm_is_aiseo_active()) {
        return array();
    }
    
    global $wpdb;
    $content_id = intval($content_id);
   
    // Exit if no content ID is found
    if (!$content_id) {
        return array();
    }

    // Keywords to return
    $keywords = '';
    
    // According to user information, AIOSEO stores keywords in the aioseo_posts table
    $aioseo_table = $wpdb->prefix . 'aioseo_posts';
    
    // Check if the table exists - escape table name for security
    $table_exists = $wpdb->get_var($wpdb->prepare(
        'SHOW TABLES LIKE %s',
        $wpdb->esc_like($aioseo_table)
    ));
    
    if ($table_exists) {
        // Try to fetch keywords from AIOSEO's custom table
        // Table name must be escaped as it cannot be parameterized
        $keywords_data = $wpdb->get_var($wpdb->prepare(
            "SELECT keyphrases FROM " . esc_sql($aioseo_table) . " WHERE post_id = %d",
            $content_id
        ));
        
        if ($keywords_data) {
            // AIOSEO stores focus keywords in JSON format
            $json_data = json_decode($keywords_data, true);
            
            if (is_array($json_data)) {
                $focus_keywords = [];
                
                // Extract the main focus keyphrase
                if (isset($json_data['focus']) && isset($json_data['focus']['keyphrase'])) {
                    $focus_keywords[] = $json_data['focus']['keyphrase'];
                }
                
                // Extract any additional keyphrases
                if (isset($json_data['additional']) && is_array($json_data['additional'])) {
                    foreach ($json_data['additional'] as $additional) {
                        if (isset($additional['keyphrase'])) {
                            $focus_keywords[] = $additional['keyphrase'];
                        }
                    }
                }
                
                // Combine all found keywords
                if (!empty($focus_keywords)) {
                    $keywords = implode(', ', $focus_keywords);
                }
            }
        }
    }
    
    return $keywords;
} 