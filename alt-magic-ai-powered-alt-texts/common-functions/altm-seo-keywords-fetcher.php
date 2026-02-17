<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alt Magic SEO Keywords Fetcher
 * 
 * This file contains functions for fetching SEO keywords from various SEO plugins.
 * Used by both upload handler and alt text generator to avoid code duplication.
 */

/**
 * Fetch SEO keywords from all active SEO plugins for a given attachment ID
 * 
 * @param int $primary_post_id The primary post ID to fetch keywords for
 * @return string Comma-separated string of keywords from all active SEO plugins
 */
function altm_fetch_seo_keywords($primary_post_id) {
    
    $all_keywords = array();
    
    // Get keywords from Yoast SEO if active
    if (function_exists('altm_is_yoast_active') && altm_is_yoast_active()) {
        $yoast_keywords = altm_get_yoast_seo_keywords($primary_post_id);
        altm_log('Yoast keywords: ' . print_r($yoast_keywords, true));
        if (!empty($yoast_keywords)) {
            $all_keywords[] = $yoast_keywords;
        }
    }
    
    // Get keywords from Rank Math if active
    if (function_exists('altm_is_rankmath_active') && altm_is_rankmath_active()) {
        $rankmath_keywords = altm_get_rankmath_keywords($primary_post_id);
        altm_log('Rank Math keywords: ' . print_r($rankmath_keywords, true));
        if (!empty($rankmath_keywords)) {
            $all_keywords[] = $rankmath_keywords;
        }
    }
    
    // Get keywords from AIOSEO if active
    if (function_exists('altm_is_aiseo_active') && altm_is_aiseo_active()) {
        $aiseo_keywords = altm_get_aiseo_keywords($primary_post_id);
        altm_log('AIOSEO keywords: ' . print_r($aiseo_keywords, true));
        if (!empty($aiseo_keywords)) {
            $all_keywords[] = $aiseo_keywords;
        }
    }
    
    // Get keywords from SEO Press if active
    if (function_exists('altm_is_seopress_active') && altm_is_seopress_active()) {
        $seopress_keywords = altm_get_seopress_keywords($primary_post_id);
        altm_log('SEO Press keywords: ' . print_r($seopress_keywords, true));
        if (!empty($seopress_keywords)) {
            $all_keywords[] = $seopress_keywords;
        }
    }
    
    // Get keywords from Squirrly SEO if active
    if (function_exists('altm_is_squirrly_active') && altm_is_squirrly_active()) {
        $squirrly_keywords = altm_get_squirrly_seo_keywords($primary_post_id);
        altm_log('Squirrly keywords: ' . print_r($squirrly_keywords, true));
        if (!empty($squirrly_keywords)) {
            $all_keywords[] = $squirrly_keywords;
        }
    }
    
    // Combine all keywords into a single string
    if (!empty($all_keywords)) {
        // First, combine all keyword strings
        $combined_keywords = implode(', ', $all_keywords);
        
        // Split by comma to get individual keywords
        $keyword_array = array_map('trim', explode(',', $combined_keywords));
        
        // Remove duplicates and empty values
        $keyword_array = array_filter(array_unique($keyword_array));
        
        // Convert back to a comma-separated string
        $seo_keywords = implode(', ', $keyword_array);
        
        altm_log('Combined keywords from all active SEO plugins: ' . $seo_keywords);
        
        return $seo_keywords;
    }
    
    return '';
}

/**
 * Fetch SEO keywords as array for batch processing
 * 
 * @param int $attachment_id The attachment ID to fetch keywords for
 * @return array Array of keywords from all active SEO plugins
 */
function altm_fetch_seo_keywords_array($primary_post_id = 0) {
    
    $all_keywords = array();
    
    // Get keywords from Yoast SEO if active
    if (function_exists('altm_is_yoast_active') && altm_is_yoast_active()) {
        $yoast_keywords = altm_get_yoast_seo_keywords($primary_post_id);
        if (!empty($yoast_keywords)) {
            $all_keywords[] = $yoast_keywords;
        }
    }
    
    // Get keywords from Rank Math if active
    if (function_exists('altm_is_rankmath_active') && altm_is_rankmath_active()) {
        $rankmath_keywords = altm_get_rankmath_keywords($primary_post_id);
        if (!empty($rankmath_keywords)) {
            $all_keywords[] = $rankmath_keywords;
        }
    }
    
    // Get keywords from AIOSEO if active
    if (function_exists('altm_is_aiseo_active') && altm_is_aiseo_active()) {
        $aiseo_keywords = altm_get_aiseo_keywords($primary_post_id);
        if (!empty($aiseo_keywords)) {
            $all_keywords[] = $aiseo_keywords;
        }
    }
    
    // Get keywords from SEO Press if active
    if (function_exists('altm_is_seopress_active') && altm_is_seopress_active()) {
        $seopress_keywords = altm_get_seopress_keywords($primary_post_id);
        if (!empty($seopress_keywords)) {
            $all_keywords[] = $seopress_keywords;
        }
    }
    
    // Get keywords from Squirrly SEO if active
    if (function_exists('altm_is_squirrly_active') && altm_is_squirrly_active()) {
        $squirrly_keywords = altm_get_squirrly_seo_keywords($primary_post_id);
        if (!empty($squirrly_keywords)) {
            $all_keywords[] = $squirrly_keywords;
        }
    }
    
    // Combine all keywords into array
    if (!empty($all_keywords)) {
        $combined_keywords = implode(', ', $all_keywords);
        $keyword_array = array_map('trim', explode(',', $combined_keywords));
        $keywords_array = array_filter(array_unique($keyword_array));
        
        return $keywords_array;
    }
    
    return array();
}

?>
