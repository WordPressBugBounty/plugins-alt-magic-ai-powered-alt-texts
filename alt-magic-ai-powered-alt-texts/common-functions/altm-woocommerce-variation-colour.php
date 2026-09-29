<?php

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the WooCommerce attributes available for explicit colour mapping.
 *
 * @return array<string,string> Mapping value to an admin-facing label.
 */
function altm_get_woocommerce_colour_attribute_mapping_options() {
    $options = array(
        'none' => 'None',
    );

    if (!function_exists('wc_get_attribute_taxonomies')) {
        return $options;
    }

    foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
        if (!is_object($attribute) || empty($attribute->attribute_name)) {
            continue;
        }

        $taxonomy = function_exists('wc_attribute_taxonomy_name')
            ? wc_attribute_taxonomy_name($attribute->attribute_name)
            : 'pa_' . $attribute->attribute_name;
        $label = !empty($attribute->attribute_label)
            ? $attribute->attribute_label
            : $attribute->attribute_name;

        $options[$taxonomy] = sprintf('%s (%s)', $label, $taxonomy);
    }

    return $options;
}

/**
 * Validate the selected WooCommerce colour-attribute mapping.
 *
 * @param mixed $value Saved or submitted mapping value.
 * @return string
 */
function altm_sanitize_woocommerce_colour_attribute_mapping($value) {
    $value = is_scalar($value) ? sanitize_text_field((string) $value) : 'none';
    $value = preg_replace('/^attribute_/', '', $value);
    $options = altm_get_woocommerce_colour_attribute_mapping_options();

    return array_key_exists($value, $options)
        ? $value
        : 'none';
}

/**
 * Get the active colour-attribute mapping.
 *
 * @return string "none" or a WooCommerce attribute taxonomy such as pa_chroma.
 */
function altm_get_woocommerce_colour_attribute_mapping() {
    return altm_sanitize_woocommerce_colour_attribute_mapping(
        get_option('alt_magic_woocommerce_colour_attribute', 'none')
    );
}

/**
 * Check whether a variation attribute matches an explicit mapping.
 *
 * @param string $attribute_name Attribute taxonomy/key.
 * @param string $mapping Configured taxonomy.
 * @return bool
 */
function altm_woocommerce_attribute_matches_mapping($attribute_name, $mapping) {
    if ($mapping === '' || $mapping === 'none') {
        return false;
    }

    $attribute_name = preg_replace('/^attribute_/', '', (string) $attribute_name);

    return $attribute_name === $mapping;
}

/**
 * Convert a stored variation attribute value into its display value.
 *
 * @param string $attribute_name Attribute taxonomy/key.
 * @param mixed  $stored_value Stored variation value.
 * @return string
 */
function altm_get_woocommerce_attribute_display_value($attribute_name, $stored_value) {
    if (!is_scalar($stored_value)) {
        return '';
    }

    $stored_value = trim(rawurldecode((string) $stored_value));
    if ($stored_value === '') {
        return '';
    }

    $taxonomy = preg_replace('/^attribute_/', '', (string) $attribute_name);
    if (taxonomy_exists($taxonomy)) {
        $term = get_term_by('slug', $stored_value, $taxonomy);

        if (!$term && preg_match('/^\d+$/', $stored_value)) {
            $term = get_term_by('id', (int) $stored_value, $taxonomy);
        }

        if ($term && !is_wp_error($term) && isset($term->name)) {
            return sanitize_text_field(wp_specialchars_decode($term->name, ENT_QUOTES));
        }

        // A deleted or stale term can leave a slug behind. Keep it readable.
        $stored_value = ucwords(str_replace(array('-', '_'), ' ', $stored_value));
    }

    return sanitize_text_field(wp_strip_all_tags($stored_value));
}

/**
 * Read the colour value from a WooCommerce variation object.
 *
 * @param WC_Product_Variation $variation WooCommerce variation product.
 * @return string
 */
function altm_get_woocommerce_colour_from_variation($variation) {
    if (!is_object($variation) || !method_exists($variation, 'get_attributes')) {
        return '';
    }

    $matched_values = array();
    $attribute_mapping = altm_get_woocommerce_colour_attribute_mapping();
    foreach ((array) $variation->get_attributes() as $attribute_name => $stored_value) {
        $is_colour_attribute = altm_woocommerce_attribute_matches_mapping($attribute_name, $attribute_mapping);

        if (!$is_colour_attribute) {
            continue;
        }

        $display_value = altm_get_woocommerce_attribute_display_value($attribute_name, $stored_value);
        if ($display_value !== '') {
            $comparison_key = function_exists('mb_strtolower')
                ? mb_strtolower($display_value, 'UTF-8')
                : strtolower($display_value);
            $matched_values[$comparison_key] = $display_value;
        }
    }

    // Avoid guessing if a variation unexpectedly contains conflicting colour attributes.
    return count($matched_values) === 1 ? reset($matched_values) : '';
}

/**
 * Find WooCommerce variations that explicitly use an attachment as their image.
 *
 * @param int $attachment_id WordPress attachment ID.
 * @return int[]
 */
function altm_get_woocommerce_variation_ids_for_attachment($attachment_id) {
    $attachment_id = absint($attachment_id);
    if (!$attachment_id || !post_type_exists('product_variation')) {
        return array();
    }

    $variation_ids = get_posts(array(
        'post_type' => 'product_variation',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => '_thumbnail_id',
                'value' => (string) $attachment_id,
                'compare' => '=',
            ),
        ),
    ));

    // Some importers attach the media item directly to the variation.
    $attachment = get_post($attachment_id);
    if ($attachment && !empty($attachment->post_parent) && get_post_type($attachment->post_parent) === 'product_variation') {
        $variation_ids[] = (int) $attachment->post_parent;
    }

    $variation_ids = array_values(array_unique(array_filter(array_map('absint', (array) $variation_ids))));

    /**
     * Filter variation IDs associated with an attachment.
     *
     * @param int[] $variation_ids Variation IDs.
     * @param int   $attachment_id Attachment ID.
     */
    return apply_filters('altm_woocommerce_variation_ids_for_attachment', $variation_ids, $attachment_id);
}

/**
 * Resolve one safe colour across one or more variations.
 *
 * @param int[] $variation_ids Variation IDs.
 * @return string
 */
function altm_resolve_woocommerce_colour_for_variations($variation_ids) {
    if (!function_exists('wc_get_product')) {
        return '';
    }

    $resolved_colours = array();
    $valid_variation_count = 0;
    $missing_colour = false;

    foreach (array_values(array_unique(array_filter(array_map('absint', (array) $variation_ids)))) as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
            continue;
        }

        $valid_variation_count++;
        $colour = altm_get_woocommerce_colour_from_variation($variation);
        if ($colour === '') {
            $missing_colour = true;
            continue;
        }

        $comparison_key = function_exists('remove_accents') ? remove_accents($colour) : $colour;
        $comparison_key = trim($comparison_key);
        $comparison_key = function_exists('mb_strtolower')
            ? mb_strtolower($comparison_key, 'UTF-8')
            : strtolower($comparison_key);
        $resolved_colours[$comparison_key] = $colour;
    }

    // A shared attachment is safe only when every matched variation has the same colour.
    if ($valid_variation_count === 0 || $missing_colour || count($resolved_colours) !== 1) {
        return '';
    }

    return reset($resolved_colours);
}

/**
 * Build the optional attributes payload for an attachment.
 *
 * @param int $attachment_id WordPress attachment ID.
 * @return array
 */
function altm_get_woocommerce_variation_colour_attributes($attachment_id) {
    if (altm_get_woocommerce_colour_attribute_mapping() === 'none') {
        return array();
    }

    $variation_ids = altm_get_woocommerce_variation_ids_for_attachment($attachment_id);
    $colour = altm_resolve_woocommerce_colour_for_variations($variation_ids);

    if ($colour === '') {
        if (!empty($variation_ids)) {
            altm_log('WooCommerce variation colour was not sent for attachment ID ' . absint($attachment_id) . ' because no single safe colour could be resolved.');
        }
        return array();
    }

    return array('colour' => $colour);
}
