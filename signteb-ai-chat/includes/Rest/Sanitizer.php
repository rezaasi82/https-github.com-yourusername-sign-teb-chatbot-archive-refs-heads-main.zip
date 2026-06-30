<?php

namespace STMC_Chat\Rest;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared input sanitization for both REST and admin-ajax transports.
 */
class Sanitizer
{
    public static function session_id(string $raw): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $raw);
        $clean = substr((string) $clean, 0, 64);
        return $clean !== '' ? $clean : wp_generate_uuid4();
    }

    public static function client_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
