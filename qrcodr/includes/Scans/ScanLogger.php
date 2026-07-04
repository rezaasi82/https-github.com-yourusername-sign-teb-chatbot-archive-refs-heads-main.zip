<?php

namespace QRCODR\Scans;

if (!defined('ABSPATH')) {
    exit;
}

class ScanLogger
{
    public static function log($code_id)
    {
        global $wpdb;

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $ip = self::client_ip();

        $wpdb->insert(
            $wpdb->prefix . 'qrcodr_scans',
            array(
                'code_id' => (int) $code_id,
                'scanned_at' => current_time('mysql'),
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : null,
                'device_type' => UserAgentParser::device_type($user_agent),
                'browser' => UserAgentParser::browser($user_agent),
                'os' => UserAgentParser::os($user_agent),
                'referrer' => $referrer ? substr($referrer, 0, 500) : null,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    private static function client_ip()
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            return '';
        }

        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}
