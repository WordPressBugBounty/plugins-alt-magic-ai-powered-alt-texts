<?php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the private, site-specific debug log path.
 *
 * WordPress normally resolves get_temp_dir() outside the public web root. The
 * site-specific hash keeps the filename unpredictable if a host falls back to
 * a web-accessible temporary directory.
 *
 * @return string
 */
function altm_get_log_file_path() {
    $site_hash = hash_hmac('sha256', home_url('/'), wp_salt('auth'));

    return trailingslashit(get_temp_dir()) . 'altm-debug-' . substr($site_hash, 0, 32) . '.log';
}

/**
 * Return the legacy public debug log path used before version 1.7.10.
 *
 * @return string
 */
function altm_get_legacy_log_file_path() {
    return trailingslashit(WP_CONTENT_DIR) . 'altm_debug.log';
}

/**
 * Remove any legacy log left in the public wp-content directory.
 *
 * If deletion is not possible, truncate the file so previously logged secrets
 * are no longer exposed while the host permissions are corrected.
 */
function altm_cleanup_legacy_public_log_file() {
    $legacy_log_file = altm_get_legacy_log_file_path();

    if (!file_exists($legacy_log_file)) {
        return;
    }

    wp_delete_file($legacy_log_file);
    clearstatcache(true, $legacy_log_file);

    if (file_exists($legacy_log_file) && is_writable($legacy_log_file)) {
        file_put_contents($legacy_log_file, '', LOCK_EX);
    }
}
add_action('plugins_loaded', 'altm_cleanup_legacy_public_log_file', 1);

/**
 * Redact common credentials and personal data before writing a log entry.
 *
 * @param mixed $message Log message.
 * @return string
 */
function altm_redact_log_message($message) {
    if (!is_scalar($message)) {
        $message = wp_json_encode($message);
    }

    if ($message === false || $message === null) {
        return '[unavailable log context]';
    }

    $message = (string) $message;
    $patterns = array(
        '/("(?:api_key|access_token|refresh_token|authorization)"\s*:\s*")[^"]*(")/i',
        '/\bBearer\s+[A-Za-z0-9._~+\/=\-]+/i',
        '/(Authorization\s*(?:=>|:|=)\s*).+$/im',
        '/((?:api[_ -]?key|access[_ -]?token|refresh[_ -]?token)\s*(?:=>|:|=)\s*)[^\s,}\]]+/i',
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
        '/([?&](?:key|token|signature|sig|auth)=)[^&\s]+/i',
    );
    $replacements = array(
        '$1[REDACTED]$2',
        'Bearer [REDACTED]',
        '$1[REDACTED]',
        '$1[REDACTED]',
        '[REDACTED EMAIL]',
        '$1[REDACTED]',
    );
    $redacted_message = preg_replace($patterns, $replacements, $message);

    return $redacted_message === null ? '[redacted log context]' : $redacted_message;
}

// Custom logging function.
function altm_log($message) {
    if (!get_option('altm_debug_mode')) {
        return;
    }

    $log_file_path = altm_get_log_file_path();
    $log_file_exists = file_exists($log_file_path);

    // Keep debug logs bounded and avoid retaining an unlimited history.
    if ($log_file_exists && filesize($log_file_path) > (5 * MB_IN_BYTES)) {
        file_put_contents($log_file_path, '', LOCK_EX);
    }

    $log_message = gmdate('Y-m-d H:i:s') . ' ' . altm_redact_log_message($message) . PHP_EOL;
    $bytes_written = file_put_contents($log_file_path, $log_message, FILE_APPEND | LOCK_EX);

    if (!$log_file_exists && $bytes_written !== false) {
        // Best effort: restrict a newly created log to the current system user.
        @chmod($log_file_path, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }
}
