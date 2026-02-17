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
 * Check if Rank Math plugin is active
 *
 * @return bool True if Rank Math plugin is active, false otherwise
 */
function altm_is_rankmath_active() {
    return is_plugin_active('seo-by-rank-math/rank-math.php') || is_plugin_active('seo-by-rank-math-pro/rank-math-pro.php');
}

/**
 * Get keywords from Rank Math for a content
 *
 * @param int $content_id The ID of the content
 * @return string Comma-separated list of keywords
 */
function altm_get_rankmath_keywords($content_id = 0) {
    // Exit if Rank Math is not active
    if (!altm_is_rankmath_active()) {
        return array();
    }
    
    global $wpdb;
    $content_id = intval($content_id);
   
    // Exit if no content ID is found
    if (!$content_id) {
        return array();
    }

    // Fetch keywords from Rank Math
    // Rank Math stores focus keywords in postmeta with key 'rank_math_focus_keyword'
    $focus_keyword = get_post_meta($content_id, 'rank_math_focus_keyword', true);
    
    if (empty($focus_keyword)) {
        return array();
    }
    
    // Return keywords in the same comma-separated format as other SEO plugins
    return $focus_keyword;
} 