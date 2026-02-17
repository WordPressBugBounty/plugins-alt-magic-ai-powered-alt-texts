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
 * Check if Squirrly SEO plugin is active
 *
 * @return bool True if Squirrly SEO plugin is active, false otherwise
 */
function altm_is_squirrly_active() {
    return is_plugin_active('squirrly-seo/squirrly.php');
}

/**
 * Get keywords from Squirrly SEO for a content
 *
 * @param int $content_id The ID of the content
 * @return string Comma-separated list of keywords
 */
function altm_get_squirrly_seo_keywords($content_id = 0) {
    // Exit if Squirrly SEO is not active
    if (!altm_is_squirrly_active()) {
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
    
    // According to Squirrly's official documentation, they store SEO data in the wp_qss table
    // This table contains serialized SEO data in the 'seo' column
    $qss_table = $wpdb->prefix . 'qss';
    
    // Check if the table exists - escape table name for security
    $table_exists = $wpdb->get_var($wpdb->prepare(
        'SHOW TABLES LIKE %s',
        $wpdb->esc_like($qss_table)
    ));
    
    if ($table_exists) {
        // Find the record for this post by looking at the 'post' column which contains the post ID
        // Table name must be escaped as it cannot be parameterized
        $seo_data = $wpdb->get_var($wpdb->prepare(
            "SELECT seo FROM " . esc_sql($qss_table) . " WHERE post LIKE %s",
            '%"ID";i:' . $content_id . ';%'
        ));
        
        if ($seo_data) {
            // The seo field contains serialized data, so unserialize it
            $seo_array = maybe_unserialize($seo_data);
            
            // Check for keywords in the unserialized data
            if (is_array($seo_array) && isset($seo_array['keywords'])) {
                $keywords = $seo_array['keywords'];
            } elseif (is_object($seo_array) && isset($seo_array->keywords)) {
                $keywords = $seo_array->keywords;
            }
        }
    }
    
    return $keywords;
} 