<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if the WordPress site should be considered private (local domain check only)
 * 
 * @return int 1 if site is private (.local domain or localhost), 0 if appears to be public
 */
function alt_magic_is_site_private_by_domain() {
    // Check if site is running locally based on domain patterns
    $site_url = get_site_url();
    
    if ($site_url) {
        $parsed_url = wp_parse_url($site_url);
        $host = isset($parsed_url['host']) ? strtolower($parsed_url['host']) : '';
        
        // Check for common local development domains and IPs
        $local_patterns = array(
            '.local',
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0',
            '.test',
            '.dev',
            '.example'
        );
        
        foreach ($local_patterns as $pattern) {
            if (strpos($host, $pattern) !== false || $host === $pattern) {
                return 1;
            }
        }
        
        // Check for private IP ranges (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
        if (preg_match('/^192\.168\.\d{1,3}\.\d{1,3}$/', $host) ||
            preg_match('/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host) ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3}$/', $host)) {
            return 1;
        }
    }
    
    return 0;
}

/**
 * Check image accessibility via API
 * 
 * @return bool|null Returns true if accessible, false if not accessible, null if check failed
 */
function alt_magic_check_image_accessibility_via_api() {
    try {
        // Verify ALT_MAGIC_API_BASE_URL is defined
        if (!defined('ALT_MAGIC_API_BASE_URL') || empty(ALT_MAGIC_API_BASE_URL)) {
            return null;
        }

        // Get a random image from the media library to test
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'orderby' => 'rand'
        );
        
        $images = get_posts($args);
        
        if (empty($images)) {
            // No images found - we'll use a WordPress default image as fallback
            $site_url = get_site_url();
            if (!$site_url) {
                return null;
            }
            $test_url = $site_url . '/wp-includes/images/media/default.png';
        } else {
            $test_url = wp_get_attachment_url($images[0]->ID);
        }
        
        if (!$test_url || !filter_var($test_url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Call the Alt Magic API to check if the image is accessible
        $api_url = ALT_MAGIC_API_BASE_URL . '/check-image-accessibility';
        
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'image_url' => $test_url
            )),
            'timeout' => 10,
            'sslverify' => true,
            'blocking' => true
        ));

        if (is_wp_error($response)) {
            altm_log('Alt Magic: Image accessibility check during initialization failed - ' . $response->get_error_message());
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (!$response_code || $response_code !== 200 || empty($response_body)) {
            return null;
        }

        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        // Return the accessibility status
        return isset($data['accessible']) ? (bool) $data['accessible'] : null;
        
    } catch (Exception $e) {
        altm_log('Alt Magic: Image accessibility check during initialization caught exception - ' . $e->getMessage());
        return null;
    } catch (Error $e) {
        altm_log('Alt Magic: Image accessibility check during initialization caught error - ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if the WordPress site should be considered private
 * Uses domain-based detection first, then API verification if domain appears public
 * 
 * @return int 1 if site is private, 0 if public
 */
function alt_magic_is_site_private() {
    // First, check if domain is obviously local
    $is_local_domain = alt_magic_is_site_private_by_domain();
    
    if ($is_local_domain === 1) {
        // Domain is definitely local, return 1 (private)
        return 1;
    }
    
    // Domain appears to be public, verify with API
    $api_check = alt_magic_check_image_accessibility_via_api();
    
    if ($api_check === true) {
        // Images are accessible, site is public
        altm_log('Alt Magic: Site initialization - Images are accessible from the internet. Setting private_site to 0.');
        return 0;
    } elseif ($api_check === false) {
        // Images are not accessible, site is private (firewall, etc.)
        altm_log('Alt Magic: Site initialization - Images are not accessible from the internet. Setting private_site to 1.');
        return 1;
    } else {
        // API check failed or returned null, default to safe option (private)
        altm_log('Alt Magic: Site initialization - Could not verify image accessibility. Defaulting to private_site = 1 for safety.');
        return 1;
    }
}

// Add plugin settings
function alt_magic_add_settings() {
    // Only check site privacy status if the setting doesn't exist yet
    // This prevents the API call from running on every page load
    $is_private_site = (get_option('alt_magic_private_site') === false) 
        ? alt_magic_is_site_private() 
        : get_option('alt_magic_private_site', 1);
    
    // Define settings with their default values and types
    $settings = [
        'alt_magic_account_active' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_api_key' => ['default' => '', 'type' => 'string'],
        'alt_magic_user_id' => ['default' => '', 'type' => 'string'],
        'alt_magic_language' => ['default' => 'en', 'type' => 'string'],
        'alt_magic_use_for_title' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_use_for_caption' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_use_for_description' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_prepend_string' => ['default' => '', 'type' => 'string'],
        'alt_magic_append_string' => ['default' => '', 'type' => 'string'],
        'alt_magic_auto_generate' => ['default' => 1, 'type' => 'boolean'],
        'alt_magic_auto_rename_on_upload' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_use_seo_keywords' => ['default' => 1, 'type' => 'boolean'],
        'alt_magic_use_post_title' => ['default' => 1, 'type' => 'boolean'],
        'alt_magic_refresh_alt_text' => ['default' => 'all', 'type' => 'string'],
        'alt_magic_private_site' => ['default' => $is_private_site, 'type' => 'boolean'],
        'alt_magic_woocommerce_use_product_name' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_rename_use_seo_keywords' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_rename_use_post_title' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_rename_use_woocommerce_product_name' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_max_concurrency' => ['default' => 5, 'type' => 'integer'],
        'alt_magic_enable_redirections' => ['default' => 0, 'type' => 'boolean'],
        'altm_debug_mode' => ['default' => 0, 'type' => 'boolean'],
        'alt_magic_extra_prompt' => ['default' => '', 'type' => 'textarea'],
        'alt_magic_rename_language' => ['default' => 'en', 'type' => 'string']
    ];

    foreach ($settings as $option_name => $config) {
        if (get_option($option_name) === false) {
            add_option($option_name, $config['default']);
        }
        
        // Determine the appropriate sanitization callback based on type
        $type = $config['type'];
        
        // Select the appropriate sanitization callback
        switch ($type) {
            case 'boolean':
                $sanitize_callback = 'alt_magic_sanitize_boolean';
                break;
            case 'integer':
                $sanitize_callback = 'absint';
                break;
            case 'textarea':
                $sanitize_callback = 'sanitize_textarea_field';
                break;
            case 'string':
            default:
                $sanitize_callback = 'sanitize_text_field';
                break;
        }
        
        register_setting('alt_magic_options', $option_name, array(
            'type' => $type === 'boolean' ? 'integer' : $type,
            'sanitize_callback' => $sanitize_callback,
            'default' => $config['default']
        ));
    }

}

/**
 * Sanitize boolean settings (convert to 0 or 1)
 * 
 * @param mixed $value The value to sanitize
 * @return int Returns 1 for truthy values, 0 otherwise
 */
function alt_magic_sanitize_boolean($value) {
    return absint($value) ? 1 : 0;
}

add_action('admin_init', 'alt_magic_add_settings');