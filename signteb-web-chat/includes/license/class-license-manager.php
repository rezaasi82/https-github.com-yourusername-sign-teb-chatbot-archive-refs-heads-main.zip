<?php
/**
 * SWC_License_Manager — annual-license scaffold with a free trial gate.
 *
 * The business model is an annual license sold per-site, plus a free trial
 * (default 50 messages) before activation is required. The activation-key
 * architecture is in place from day one so it can be wired to a license server
 * later by changing is_active(); call sites do not change.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_License_Manager
{
    private const OPTION       = 'swc_license';
    private const TRIAL_OPTION = 'swc_trial_used';
    private const TRIAL_LIMIT  = 50;

    /**
     * @return array{key:string,status:string,domain:string,activated_at:string}
     */
    public function info(): array
    {
        $stored = get_option(self::OPTION, []);
        return wp_parse_args(is_array($stored) ? $stored : [], [
            'key'          => '',
            'status'       => 'trial',
            'domain'       => self::domain_hash(),
            'activated_at' => '',
        ]);
    }

    public function activate(string $key): array
    {
        $key  = sanitize_text_field($key);
        $info = [
            'key'          => $key,
            'status'       => $key !== '' ? 'active' : 'trial',
            'domain'       => self::domain_hash(),
            'activated_at' => current_time('mysql'),
        ];
        update_option(self::OPTION, $info);

        do_action('swc_license_status_changed', $info['status']);
        return $info;
    }

    /**
     * Whether a full (paid) license is active. v1 trusts a non-empty key; swap
     * this for a real server check later without touching call sites.
     */
    public function is_active(): bool
    {
        $active = $this->info()['status'] === 'active';
        return (bool) apply_filters('swc_license_is_active', $active, $this->info());
    }

    public function trial_limit(): int
    {
        return (int) apply_filters('swc_trial_limit', self::TRIAL_LIMIT);
    }

    public function trial_used(): int
    {
        return (int) get_option(self::TRIAL_OPTION, 0);
    }

    public function trial_remaining(): int
    {
        return max(0, $this->trial_limit() - $this->trial_used());
    }

    /**
     * Gate the conversation engine: licensed sites are unlimited; unlicensed
     * sites may send until the free trial is exhausted.
     */
    public function can_send(): bool
    {
        if ($this->is_active()) {
            return true;
        }
        return $this->trial_remaining() > 0;
    }

    /**
     * Count one consumed message against the trial (no-op once licensed).
     */
    public function record_usage(): void
    {
        if ($this->is_active()) {
            return;
        }
        update_option(self::TRIAL_OPTION, $this->trial_used() + 1, false);
    }

    private static function domain_hash(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        return hash('sha256', strtolower($host));
    }
}
