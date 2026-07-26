<?php
/**
 * Symmetric encryption for API keys at rest.
 *
 * @package Pazira
 */

namespace Pazira\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated symmetric encryption keyed off WordPress' own salts, so
 * provider API keys and integration secrets are never stored in plaintext in
 * wp_options. New values use AES-256-GCM (authenticated: tampering is
 * detected). Older 'enc:' (AES-256-CBC) and 'b64:' values still decrypt, so
 * upgrades are seamless. If OpenSSL is missing it degrades to reversible
 * obfuscation — it never throws.
 */
class Encryption
{
    private const CIPHER_GCM = 'aes-256-gcm';
    private const CIPHER_CBC = 'aes-256-cbc';

    private static function key(): string
    {
        $salt = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '');
        if ($salt === '') {
            $salt = (string) get_option('pzr_fallback_salt');
            if ($salt === '') {
                $salt = wp_generate_password(64, true, true);
                update_option('pzr_fallback_salt', $salt, false);
            }
        }
        return hash('sha256', $salt, true);
    }

    private static function gcm_available(): bool
    {
        return function_exists('openssl_encrypt')
            && in_array(self::CIPHER_GCM, openssl_get_cipher_methods(), true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        if (self::gcm_available()) {
            $iv     = openssl_random_pseudo_bytes(12); // 96-bit nonce for GCM
            $tag    = '';
            $cipher = openssl_encrypt($plain, self::CIPHER_GCM, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher !== false) {
                return 'gcm:' . base64_encode($iv . $tag . $cipher);
            }
        }

        // Fallbacks.
        if (function_exists('openssl_encrypt')) {
            $iv_len = openssl_cipher_iv_length(self::CIPHER_CBC);
            $iv     = openssl_random_pseudo_bytes($iv_len);
            $cipher = openssl_encrypt($plain, self::CIPHER_CBC, self::key(), OPENSSL_RAW_DATA, $iv);
            if ($cipher !== false) {
                return 'enc:' . base64_encode($iv . $cipher);
            }
        }
        return 'b64:' . base64_encode($plain);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (str_starts_with($stored, 'gcm:')) {
            return self::decrypt_gcm(substr($stored, 4));
        }
        if (str_starts_with($stored, 'enc:')) {
            return self::decrypt_cbc(substr($stored, 4));
        }
        if (str_starts_with($stored, 'b64:')) {
            return (string) base64_decode(substr($stored, 4));
        }
        return '';
    }

    private static function decrypt_gcm(string $b64): string
    {
        if (! self::gcm_available()) {
            return '';
        }
        $raw = base64_decode($b64);
        if ($raw === false || strlen($raw) <= 28) { // 12 iv + 16 tag
            return '';
        }
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, self::CIPHER_GCM, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    private static function decrypt_cbc(string $b64): string
    {
        if (! function_exists('openssl_decrypt')) {
            return '';
        }
        $raw    = base64_decode($b64);
        $iv_len = openssl_cipher_iv_length(self::CIPHER_CBC);
        if ($raw === false || strlen($raw) <= $iv_len) {
            return '';
        }
        $iv     = substr($raw, 0, $iv_len);
        $cipher = substr($raw, $iv_len);
        $plain  = openssl_decrypt($cipher, self::CIPHER_CBC, self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}
