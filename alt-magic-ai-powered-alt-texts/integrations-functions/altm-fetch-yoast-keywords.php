<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress file to use is_plugin_active()
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

function altm_is_yoast_active() {
    // Consider removing or conditionally using altm_log in production
    //altm_log('is_yoast_active: '. is_plugin_active('wordpress-seo/wp-seo.php'));
    //altm_log('is_yoast_premium_active: '. is_plugin_active('wordpress-seo-premium/wp-seo-premium.php'));
    return is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');
}

function altm_get_yoast_seo_keywords($content_id = 0) {
    // Exit if Yoast SEO is not active
    if (!altm_is_yoast_active()) {
        return array();
    }
    
    global $wpdb;
    $content_id = intval($content_id);
   
    // Exit if no content ID is found
    if (!$content_id) {
        // Consider removing or conditionally using altm_log in production
        //altm_log('No content ID found for content ID: ' . $content_id);
        return array();
    }

    // Consider removing or conditionally using altm_log in production
    //altm_log('Content ID found: ' . $content_id);
    // Fetch main keyword
    $main_keyword_result = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value AS main_keyword FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_focuskw' AND post_id = %d",
        $content_id
    ));
    //altm_log('Main keyword result: ' . print_r($main_keyword_result, true));

    if (count($main_keyword_result) == 0 || strlen($main_keyword_result[0]->main_keyword) == 0) {
        return array();
    }

    $all_keywords = explode(',', $main_keyword_result[0]->main_keyword);

    // Fetch related keywords
    $related_keywords_result = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value AS related_keywords FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_focuskeywords' AND post_id = %d",
        $content_id
    ));

    //altm_log('Related keywords result: ' . print_r($related_keywords_result, true));

    if (count($related_keywords_result) > 0) {
        $parsed_related_keywords = json_decode($related_keywords_result[0]->related_keywords);
        foreach ($parsed_related_keywords as $keyword_object) {
            $all_keywords[] = $keyword_object->keyword;
        }
    }

    //altm_log('All keywords: ' . print_r($all_keywords, true));
    $comma_separated_keywords = implode(', ', array_filter(array_map('trim', $all_keywords)));
    //altm_log('Comma separated keywords: ' . $comma_separated_keywords);
    return $comma_separated_keywords;
}