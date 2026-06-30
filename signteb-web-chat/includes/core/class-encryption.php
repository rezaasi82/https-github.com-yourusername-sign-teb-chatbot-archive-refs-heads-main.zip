<?php
/**
 * SWC_Encryption — symmetric encryption for API keys at rest.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AES-256-CBC symmetric encryption keyed off WordPress' own salts so provider
 * API keys are never stored in plaintext in wp_options. If OpenSSL is missing
 * (rare on Iranian shared hosts) it degrades to reversible obfuscation so the
 * plugin keeps working — it never throws.
 */
class SWC_Encryption
{
    private const CIPHER = 'aes-256-cbc';

    private static function key(): string
    {
        $salt = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '');
        if ($salt === '') {
            $salt = (string) get_option('swc_fallback_salt');
            if ($salt === '') {
                $salt = wp_generate_password(64, true, true);
                update_option('swc_fallback_salt', $salt, false);
            }
        }
        return hash('sha256', $salt, true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        if (! function_exists('openssl_encrypt')) {
            return 'b64:' . base64_encode($plain);
        }
        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $iv     = openssl_random_pseudo_bytes($iv_len);
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return 'b64:' . base64_encode($plain);
        }
        return 'enc:' . base64_encode($iv . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (str_starts_with($stored, 'b64:')) {
            return (string) base64_decode(substr($stored, 4));
        }
        if (! str_starts_with($stored, 'enc:') || ! function_exists('openssl_decrypt')) {
            return '';
        }
        $raw    = base64_decode(substr($stored, 4));
        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        if ($raw === false || strlen($raw) <= $iv_len) {
            return '';
        }
        $iv     = substr($raw, 0, $iv_len);
        $cipher = substr($raw, $iv_len);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}
