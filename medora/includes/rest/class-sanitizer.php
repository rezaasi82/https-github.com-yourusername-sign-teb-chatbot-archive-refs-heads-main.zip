<?php
/**
 * Shared input sanitization for both transports.
 *
 * @package Medora
 */

namespace Medora\Rest;

if (! defined('ABSPATH')) {
    exit;
}

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
        $ip = \Medora\Core\Input::client_ip();
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function name(string $raw): string
    {
        $clean = sanitize_text_field($raw);
        return mb_substr($clean, 0, 120);
    }

    /**
     * Normalize a phone number: convert Persian/Arabic digits to Latin, keep
     * only digits and a leading +, and require a plausible length. Returns ''
     * when the input isn't a usable number.
     */
    public static function phone(string $raw): string
    {
        $fa    = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar    = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $raw   = str_replace(array_merge($fa, $ar), ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $raw);
        $clean = preg_replace('/[^\d+]/', '', $raw);
        $clean = (string) $clean;
        $digits = preg_replace('/\D/', '', $clean);
        if (strlen((string) $digits) < 7 || strlen((string) $digits) > 15) {
            return '';
        }
        return mb_substr($clean, 0, 32);
    }
}
