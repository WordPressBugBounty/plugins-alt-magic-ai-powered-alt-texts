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
 * Check if SEO Press plugin is active
 *
 * @return bool True if SEO Press plugin is active, false otherwise
 */
function altm_is_seopress_active() {
    return is_plugin_active('wp-seopress/seopress.php') || is_plugin_active('wp-seopress-pro/seopress-pro.php');
}

/**
 * Get keywords from SEO Press for a content
 *
 * @param int $content_id The ID of the content
 * @return string Comma-separated list of keywords
 */
function altm_get_seopress_keywords($content_id = 0) {
    // Exit if SEO Press is not active
    if (!altm_is_seopress_active()) {
        return array();
    }
    
    global $wpdb;
    $content_id = intval($content_id);
   
    // Exit if no content ID is found
    if (!$content_id) {
        return array();
    }

    // Fetch keywords from SEO Press
    // SEO Press stores keywords in postmeta with key '_seopress_analysis_target_kw'
    $keywords_meta = get_post_meta($content_id, '_seopress_analysis_target_kw', true);
    
    if (empty($keywords_meta)) {
        return array();
    }
    
    // Return keywords in the same comma-separated format as other SEO plugins
    return $keywords_meta;
} 