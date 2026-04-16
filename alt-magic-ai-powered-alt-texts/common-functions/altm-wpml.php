<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function altm_is_wpml_active() {
    return isset($GLOBALS['sitepress']) || defined('ICL_SITEPRESS_VERSION') || has_filter('wpml_current_language') || has_filter('wpml_post_language_details');
}

function altm_normalize_supported_language_code($language_code) {
    global $altm_supported_languages;

    if (!is_string($language_code)) {
        return '';
    }

    $language_code = trim($language_code);
    if ($language_code === '') {
        return '';
    }

    $variants = array(
        $language_code,
        str_replace('_', '-', $language_code),
    );

    $candidates = array();

    foreach ($variants as $variant) {
        if ($variant === '') {
            continue;
        }

        $candidates[] = $variant;

        $lowercase_variant = strtolower($variant);
        $candidates[] = $lowercase_variant;

        $parts = explode('-', $lowercase_variant);

        if (!empty($parts[0])) {
            $candidates[] = $parts[0];
        }

        if (count($parts) > 1) {
            $canonical_parts = array();

            foreach ($parts as $index => $part) {
                if ($index === 0) {
                    $canonical_parts[] = $part;
                    continue;
                }

                if (strlen($part) === 2) {
                    $canonical_parts[] = strtoupper($part);
                } elseif (strlen($part) === 4) {
                    $canonical_parts[] = ucfirst($part);
                } else {
                    $canonical_parts[] = $part;
                }
            }

            $candidates[] = implode('-', $canonical_parts);
        }
    }

    $candidates = array_values(array_unique(array_filter($candidates)));

    foreach ($candidates as $candidate) {
        if (isset($altm_supported_languages[$candidate])) {
            return $candidate;
        }
    }

    return '';
}

function altm_get_supported_language_label($language_code) {
    global $altm_supported_languages;

    $normalized_code = altm_normalize_supported_language_code($language_code);

    if ($normalized_code !== '' && isset($altm_supported_languages[$normalized_code])) {
        return $altm_supported_languages[$normalized_code];
    }

    if (is_string($language_code) && trim($language_code) !== '') {
        return strtoupper(trim($language_code));
    }

    return __('Unknown', 'alt-magic');
}

function altm_get_language_code_from_url($url) {
    if (!is_string($url) || $url === '') {
        return '';
    }

    $query_string = wp_parse_url($url, PHP_URL_QUERY);

    if (!is_string($query_string) || $query_string === '') {
        return '';
    }

    parse_str($query_string, $query_params);

    if (!empty($query_params['lang']) && is_string($query_params['lang'])) {
        return sanitize_text_field($query_params['lang']);
    }

    return '';
}

function altm_get_wpml_post_language_details($post_id) {
    if (!$post_id || !altm_is_wpml_active()) {
        return array();
    }

    $language_details = apply_filters('wpml_post_language_details', null, (int)$post_id);

    return is_array($language_details) ? $language_details : array();
}

function altm_get_wpml_post_language_code($post_id) {
    $language_details = altm_get_wpml_post_language_details($post_id);

    if (!empty($language_details['language_code']) && is_string($language_details['language_code'])) {
        return $language_details['language_code'];
    }

    return '';
}

function altm_get_wpml_flags_base_url() {
    if (class_exists('WPML_Flags') && method_exists('WPML_Flags', 'get_wpml_flags_url')) {
        return WPML_Flags::get_wpml_flags_url();
    }

    if (defined('ICL_PLUGIN_URL')) {
        return trailingslashit(ICL_PLUGIN_URL) . 'res/flags/';
    }

    return '';
}

function altm_get_wpml_flag_file_name($language_code) {
    if (!is_string($language_code) || trim($language_code) === '') {
        $language_code = 'nil';
    }

    $language_code = trim($language_code);
    $base_language_code = strtolower(str_replace('_', '-', $language_code));
    $base_language_parts = explode('-', $base_language_code);
    $base_language = !empty($base_language_parts[0]) ? $base_language_parts[0] : $base_language_code;

    $candidates = array_values(array_unique(array_filter(array(
        $language_code,
        str_replace('_', '-', $language_code),
        strtolower($language_code),
        $base_language_code,
        $base_language,
        'nil',
    ))));

    if (function_exists('wpml_get_flag_file_name')) {
        foreach ($candidates as $candidate) {
            $file_name = wpml_get_flag_file_name($candidate);

            if (is_string($file_name) && $file_name !== '') {
                return $file_name;
            }
        }
    }

    foreach ($candidates as $candidate) {
        foreach (array('svg', 'png') as $extension) {
            $flag_path = WPML_PLUGIN_PATH . '/res/flags/' . $candidate . '.' . $extension;

            if (file_exists($flag_path)) {
                return $candidate . '.' . $extension;
            }
        }
    }

    return 'nil.svg';
}

function altm_get_wpml_flag_url($language_code) {
    $base_url = altm_get_wpml_flags_base_url();

    if ($base_url === '') {
        return '';
    }

    return $base_url . altm_get_wpml_flag_file_name($language_code);
}

function altm_resolve_generation_language($attachment_id = 0) {
    $default_language = altm_normalize_supported_language_code((string)get_option('alt_magic_language', 'en'));

    if ($default_language === '') {
        $default_language = 'en';
    }

    if (!altm_is_wpml_active()) {
        return array(
            'code' => $default_language,
            'source' => 'plugin_settings',
        );
    }

    $candidates = array();

    if ($attachment_id > 0) {
        $attachment_language = altm_get_wpml_post_language_code($attachment_id);

        if ($attachment_language !== '') {
            $candidates[] = array(
                'code' => $attachment_language,
                'source' => 'wpml_attachment',
            );
        }
    }

    $request_post_id = 0;
    if (!empty($_REQUEST['post_id'])) {
        $request_post_id = absint(wp_unslash($_REQUEST['post_id']));
    } elseif (!empty($_REQUEST['post'])) {
        $request_post_id = absint(wp_unslash($_REQUEST['post']));
    }

    if ($request_post_id > 0) {
        $request_post_language = altm_get_wpml_post_language_code($request_post_id);

        if ($request_post_language !== '') {
            $candidates[] = array(
                'code' => $request_post_language,
                'source' => 'wpml_post_context',
            );
        }
    }

    if (!empty($_REQUEST['lang'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_REQUEST['lang'])),
            'source' => 'wpml_request',
        );
    }

    $referer = wp_get_referer();
    if (!$referer && !empty($_SERVER['HTTP_REFERER'])) {
        $referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
    }

    $referer_language = altm_get_language_code_from_url($referer);
    if ($referer_language !== '') {
        $candidates[] = array(
            'code' => $referer_language,
            'source' => 'wpml_referer',
        );
    }

    if (!empty($_COOKIE['wp-wpml_current_language'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_COOKIE['wp-wpml_current_language'])),
            'source' => 'wpml_cookie',
        );
    }

    $current_wpml_language = apply_filters('wpml_current_language', null);
    if (is_string($current_wpml_language) && $current_wpml_language !== '') {
        $candidates[] = array(
            'code' => $current_wpml_language,
            'source' => 'wpml_current_language',
        );
    }

    if (defined('ICL_LANGUAGE_CODE') && is_string(ICL_LANGUAGE_CODE) && ICL_LANGUAGE_CODE !== '') {
        $candidates[] = array(
            'code' => ICL_LANGUAGE_CODE,
            'source' => 'icl_language_code',
        );
    }

    foreach ($candidates as $candidate) {
        $normalized_code = altm_normalize_supported_language_code($candidate['code']);

        if ($normalized_code !== '') {
            return array(
                'code' => $normalized_code,
                'source' => $candidate['source'],
            );
        }
    }

    return array(
        'code' => $default_language,
        'source' => 'plugin_settings',
    );
}

function altm_resolve_rename_language($attachment_id = 0, $post_context = array()) {
    $default_language = altm_normalize_supported_language_code((string) get_option('alt_magic_rename_language', 'en'));

    if ($default_language === '') {
        $default_language = 'en';
    }

    if (!altm_is_wpml_active()) {
        return array(
            'code' => $default_language,
            'source' => 'plugin_settings',
        );
    }

    $candidates = array();

    if ($attachment_id > 0) {
        $attachment_language = altm_get_wpml_post_language_code($attachment_id);

        if ($attachment_language !== '') {
            $candidates[] = array(
                'code' => $attachment_language,
                'source' => 'wpml_attachment',
            );
        }
    }

    if (is_array($post_context) && !empty($post_context['post_id'])) {
        $context_post_language = altm_get_wpml_post_language_code((int) $post_context['post_id']);

        if ($context_post_language !== '') {
            $candidates[] = array(
                'code' => $context_post_language,
                'source' => 'wpml_post_context',
            );
        }
    }

    if (!empty($_REQUEST['post_id'])) {
        $request_post_language = altm_get_wpml_post_language_code(absint(wp_unslash($_REQUEST['post_id'])));

        if ($request_post_language !== '') {
            $candidates[] = array(
                'code' => $request_post_language,
                'source' => 'wpml_request_post_context',
            );
        }
    } elseif (!empty($_REQUEST['post'])) {
        $request_post_language = altm_get_wpml_post_language_code(absint(wp_unslash($_REQUEST['post'])));

        if ($request_post_language !== '') {
            $candidates[] = array(
                'code' => $request_post_language,
                'source' => 'wpml_request_post_context',
            );
        }
    }

    if (!empty($_REQUEST['lang'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_REQUEST['lang'])),
            'source' => 'wpml_request',
        );
    }

    $current_wpml_language = apply_filters('wpml_current_language', null);
    if (is_string($current_wpml_language) && $current_wpml_language !== '') {
        $candidates[] = array(
            'code' => $current_wpml_language,
            'source' => 'wpml_current_language',
        );
    }

    if (defined('ICL_LANGUAGE_CODE') && is_string(ICL_LANGUAGE_CODE) && ICL_LANGUAGE_CODE !== '') {
        $candidates[] = array(
            'code' => ICL_LANGUAGE_CODE,
            'source' => 'icl_language_code',
        );
    }

    if (!empty($_COOKIE['wp-wpml_current_language'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_COOKIE['wp-wpml_current_language'])),
            'source' => 'wpml_cookie',
        );
    }

    foreach ($candidates as $candidate) {
        $normalized_code = altm_normalize_supported_language_code($candidate['code']);

        if ($normalized_code !== '') {
            return array(
                'code' => $normalized_code,
                'source' => $candidate['source'],
            );
        }
    }

    return array(
        'code' => $default_language,
        'source' => 'plugin_settings',
    );
}

function altm_get_wpml_current_language_data() {
    if (!altm_is_wpml_active()) {
        return array(
            'code' => '',
            'label' => __('WPML not detected', 'alt-magic'),
            'source' => 'unavailable',
        );
    }

    $candidates = array();

    if (!empty($_REQUEST['lang'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_REQUEST['lang'])),
            'source' => 'wpml_request',
        );
    }

    $current_wpml_language = apply_filters('wpml_current_language', null);
    if (is_string($current_wpml_language) && $current_wpml_language !== '') {
        $candidates[] = array(
            'code' => $current_wpml_language,
            'source' => 'wpml_current_language',
        );
    }

    if (defined('ICL_LANGUAGE_CODE') && is_string(ICL_LANGUAGE_CODE) && ICL_LANGUAGE_CODE !== '') {
        $candidates[] = array(
            'code' => ICL_LANGUAGE_CODE,
            'source' => 'icl_language_code',
        );
    }

    if (!empty($_COOKIE['wp-wpml_current_language'])) {
        $candidates[] = array(
            'code' => sanitize_text_field(wp_unslash($_COOKIE['wp-wpml_current_language'])),
            'source' => 'wpml_cookie',
        );
    }

    $language_resolution = array(
        'code' => '',
        'source' => 'unresolved',
    );

    foreach ($candidates as $candidate) {
        $normalized_code = altm_normalize_supported_language_code($candidate['code']);

        if ($normalized_code !== '') {
            $language_resolution = array(
                'code' => $normalized_code,
                'source' => $candidate['source'],
            );
            break;
        }
    }

    return array(
        'code' => $language_resolution['code'],
        'label' => $language_resolution['code'] !== ''
            ? altm_get_supported_language_label($language_resolution['code'])
            : __('Unknown', 'alt-magic'),
        'source' => $language_resolution['source'],
    );
}

function altm_get_wpml_bulk_image_scope() {
    $scope = get_option('alt_magic_wpml_bulk_image_scope', 'current_language');

    if (!in_array($scope, array('current_language', 'all_images'), true)) {
        return 'current_language';
    }

    return $scope;
}

function altm_get_wpml_attachment_language_data($attachment_id) {
    $details = altm_get_wpml_post_language_details($attachment_id);
    $code = '';
    $label = '';
    $raw_code = '';

    if (!empty($details['language_code']) && is_string($details['language_code'])) {
        $raw_code = $details['language_code'];
        $code = altm_normalize_supported_language_code($details['language_code']);
        $label = altm_get_supported_language_label($details['language_code']);
    }

    if ($code === '' && function_exists('altm_get_primary_parent_post')) {
        $parent = altm_get_primary_parent_post($attachment_id);

        if (is_array($parent) && !empty($parent['id'])) {
            $details = altm_get_wpml_post_language_details((int)$parent['id']);

            if (!empty($details['language_code']) && is_string($details['language_code'])) {
                $raw_code = $details['language_code'];
                $code = altm_normalize_supported_language_code($details['language_code']);
                $label = altm_get_supported_language_label($details['language_code']);
            }
        }
    }

    if ($label === '' && $code !== '') {
        $label = altm_get_supported_language_label($code);
    }

    return array(
        'code' => $code,
        'label' => $label,
        'raw_code' => $raw_code,
        'flag_url' => altm_get_wpml_flag_url($raw_code !== '' ? $raw_code : $code),
    );
}

function altm_get_wpml_attachment_translations($attachment_id, $include_original = false) {
    if (!$attachment_id || !altm_is_wpml_active()) {
        return array();
    }

    $translations = apply_filters('wpml_content_translations', array(), (int) $attachment_id, 'attachment');

    if (!is_array($translations) || empty($translations)) {
        return array();
    }

    $attachment_id = (int) $attachment_id;
    $translation_ids = array();

    foreach ($translations as $translation) {
        $translated_id = 0;

        if (is_object($translation) && isset($translation->element_id)) {
            $translated_id = absint($translation->element_id);
        } elseif (is_array($translation) && isset($translation['element_id'])) {
            $translated_id = absint($translation['element_id']);
        }

        if ($translated_id <= 0) {
            continue;
        }

        if (!$include_original && $translated_id === $attachment_id) {
            continue;
        }

        $translation_ids[] = $translated_id;
    }

    return array_values(array_unique($translation_ids));
}

function altm_prepare_wpml_image_collection($images, $id_property = 'ID') {
    if (!is_array($images)) {
        return array();
    }

    if (!altm_is_wpml_active()) {
        return $images;
    }

    $scope = altm_get_wpml_bulk_image_scope();
    $current_language = altm_get_wpml_current_language_data();
    $current_code = $current_language['code'];
    $prepared_images = array();

    foreach ($images as $image) {
        if (is_object($image)) {
            $attachment_id = isset($image->{$id_property}) ? absint($image->{$id_property}) : 0;
        } elseif (is_array($image)) {
            $attachment_id = isset($image[$id_property]) ? absint($image[$id_property]) : 0;
        } else {
            continue;
        }

        $language_data = altm_get_wpml_attachment_language_data($attachment_id);

        if (is_object($image)) {
            $image->wpml_language_code = $language_data['code'];
            $image->wpml_language_label = $language_data['label'];
            $image->wpml_flag_url = $language_data['flag_url'];
        } else {
            $image['wpml_language_code'] = $language_data['code'];
            $image['wpml_language_label'] = $language_data['label'];
            $image['wpml_flag_url'] = $language_data['flag_url'];
        }

        if ($scope === 'current_language' && $current_code !== '' && $language_data['code'] !== $current_code) {
            continue;
        }

        $prepared_images[] = $image;
    }

    return $prepared_images;
}
