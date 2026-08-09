<?php
/**
 * Single entry point for reading request data.
 *
 * Every superglobal read in the plugin goes through this class, so unslashing
 * and sanitising can never be forgotten at a call site. Nonce and capability
 * checks stay with the handler that owns the request.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Input
{
    /** Unslashed raw value, or null when the key is absent. */
    private static function raw(array $source, string $key)
    {
        if (! isset($source[$key])) {
            return null;
        }
        return wp_unslash($source[$key]);
    }

    public static function post_text(string $key, string $default = ''): string
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : sanitize_text_field((string) $v);
    }

    public static function post_textarea(string $key, string $default = ''): string
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : sanitize_textarea_field((string) $v);
    }

    public static function post_key(string $key, string $default = ''): string
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : sanitize_key((string) $v);
    }

    public static function post_int(string $key, int $default = 0): int
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : absint($v);
    }

    public static function post_email(string $key, string $default = ''): string
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : sanitize_email((string) $v);
    }

    public static function post_url(string $key, string $default = ''): string
    {
        $v = self::raw($_POST, $key);
        return $v === null ? $default : esc_url_raw((string) $v);
    }

    public static function post_bool(string $key): bool
    {
        return isset($_POST[$key]);
    }

    public static function has_post(string $key): bool
    {
        return isset($_POST[$key]);
    }

    public static function get_text(string $key, string $default = ''): string
    {
        $v = self::raw($_GET, $key);
        return $v === null ? $default : sanitize_text_field((string) $v);
    }

    public static function get_key(string $key, string $default = ''): string
    {
        $v = self::raw($_GET, $key);
        return $v === null ? $default : sanitize_key((string) $v);
    }

    public static function get_int(string $key, int $default = 0): int
    {
        $v = self::raw($_GET, $key);
        return $v === null ? $default : absint($v);
    }

    public static function has_get(string $key): bool
    {
        return isset($_GET[$key]);
    }

    /**
     * Whole POST body, unslashed only.
     *
     * Used by the settings screen, which sanitises every field individually
     * with the type that field needs (text, textarea, url, key, int). Callers
     * must not pass values from here to output or SQL without sanitising.
     *
     * @return array<string,mixed>
     */
    public static function post_fields(): array
    {
        return (array) wp_unslash($_POST);
    }

    /** Current request path, safe for logging and display. */
    public static function request_uri(): string
    {
        $v = self::raw($_SERVER, 'REQUEST_URI');
        return $v === null ? '' : esc_url_raw((string) $v);
    }

    /** Client address, validated as an IP. */
    public static function client_ip(): string
    {
        $v = self::raw($_SERVER, 'REMOTE_ADDR');
        if ($v === null) {
            return '';
        }
        $ip = filter_var((string) $v, FILTER_VALIDATE_IP);
        return $ip === false ? '' : $ip;
    }

}
