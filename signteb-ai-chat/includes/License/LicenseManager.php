<?php

namespace STMC_Chat\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight, forward-compatible license scaffold. The product ships with an
 * annual-license business model, so the architecture supports activation keys
 * from day one — but enforcement is intentionally permissive for v1 so the
 * plugin is fully functional out of the box. Tightening this later only means
 * changing is_valid()'s return, not the call sites.
 */
class LicenseManager
{
    private const OPTION = 'stmc_chat_license';

    /**
     * @return array{key:string,status:string,domain:string,activated_at:string}
     */
    public function info(): array
    {
        $stored = get_option(self::OPTION, []);
        return wp_parse_args(is_array($stored) ? $stored : [], [
            'key'          => '',
            'status'       => 'unregistered',
            'domain'       => self::domain_hash(),
            'activated_at' => '',
        ]);
    }

    public function activate(string $key): array
    {
        $key  = sanitize_text_field($key);
        $info = [
            'key'          => $key,
            'status'       => $key !== '' ? 'active' : 'unregistered',
            'domain'       => self::domain_hash(),
            'activated_at' => current_time('mysql'),
        ];
        update_option(self::OPTION, $info);

        do_action('stmc_chat_license_status_changed', $info['status']);
        return $info;
    }

    /**
     * v1 is permissive: the plugin works whether or not a key is present.
     * Replace the return with a real check against the license server later.
     */
    public function is_valid(): bool
    {
        return (bool) apply_filters('stmc_chat_license_is_valid', true, $this->info());
    }

    private static function domain_hash(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        return hash('sha256', strtolower($host));
    }
}
