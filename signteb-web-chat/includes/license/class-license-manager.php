<?php
/**
 * SWC_License_Manager — per-site license with trial gate and (optional)
 * remote validation against Medora Cloud.
 *
 * Backward compatible: with no cloud endpoint configured the plugin behaves
 * exactly as before (local key + free trial). When a cloud endpoint + secret
 * are set, the daily cron fetches a *signed* license verdict and caches it; the
 * signature is verified with the shared secret, and an unverifiable/absent
 * response never locks the site (fail-open) — only an explicit remote
 * "expired/suspended" locks premium features.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_License_Manager
{
    private const OPTION        = 'swc_license';
    private const REMOTE_OPTION = 'swc_license_remote';
    private const TRIAL_OPTION  = 'swc_trial_used';
    private const TRIAL_LIMIT   = 50;
    public const CRON           = 'swc_license_check';

    /** Features disabled during the grace period (soft lock). */
    private const PREMIUM_FEATURES = ['export', 'cloud', 'seo'];

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

        // Immediately try a remote validation if the cloud is configured.
        $this->remote_check(true);
        return $info;
    }

    /* -------------------- state -------------------- */

    /**
     * Effective state: active | grace | locked | trial.
     */
    public function state(): string
    {
        $remote = $this->remote();
        if ($remote !== null) {
            switch ($remote['status']) {
                case 'active':
                    return 'active';
                case 'grace':
                    return 'grace';
                case 'expired':
                case 'suspended':
                    return 'locked';
            }
        }
        // No trusted remote verdict — local behaviour.
        return $this->info()['status'] === 'active' ? 'active' : 'trial';
    }

    public function is_active(): bool
    {
        $active = in_array($this->state(), ['active', 'grace'], true);
        return (bool) apply_filters('swc_license_is_active', $active, $this->info());
    }

    public function is_locked(): bool
    {
        return $this->state() === 'locked';
    }

    /**
     * Whether a premium feature may run. Locked = nothing; grace = core only.
     */
    public function allows(string $feature): bool
    {
        $state = $this->state();
        if ($state === 'locked') {
            return false;
        }
        if ($state === 'grace' && in_array($feature, self::PREMIUM_FEATURES, true)) {
            return false;
        }
        return true;
    }

    public function plan(): string
    {
        $remote = $this->remote();
        return $remote['plan'] ?? ($this->info()['status'] === 'active' ? 'active' : 'trial');
    }

    public function expires(): string
    {
        return (string) ($this->remote()['expires'] ?? '');
    }

    public function days_left(): ?int
    {
        $exp = $this->expires();
        if ($exp === '') {
            return null;
        }
        $ts = strtotime($exp);
        return $ts ? (int) ceil(($ts - time()) / DAY_IN_SECONDS) : null;
    }

    /* -------------------- trial -------------------- */

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

    public function can_send(): bool
    {
        if ($this->is_locked()) {
            return false;
        }
        if ($this->is_active()) {
            return true;
        }
        return $this->trial_remaining() > 0;
    }

    public function record_usage(): void
    {
        if ($this->is_active()) {
            return;
        }
        update_option(self::TRIAL_OPTION, $this->trial_used() + 1, false);
    }

    /* -------------------- remote validation -------------------- */

    /**
     * Cached, signature-verified remote verdict, or null when not available.
     *
     * @return array{status:string,plan:string,expires:?string,features:array,checked_at:int}|null
     */
    public function remote(): ?array
    {
        $cached = get_option(self::REMOTE_OPTION, null);
        if (! is_array($cached) || empty($cached['status'])) {
            return null;
        }
        return $cached;
    }

    /**
     * Fetch and cache the remote verdict. Runs daily on cron; safe to call
     * anytime. Fail-open: any error leaves the previous cache untouched.
     */
    public function remote_check(bool $force = false): void
    {
        $endpoint = $this->license_endpoint();
        $secret   = (new SWC_Cloud_Client())->secret();
        if ($endpoint === '') {
            return;
        }

        $cached = $this->remote();
        if (! $force && $cached && (time() - (int) ($cached['checked_at'] ?? 0)) < DAY_IN_SECONDS) {
            return;
        }

        $response = wp_remote_get($endpoint, ['timeout' => 15]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['status'])) {
            return;
        }

        // Verify the canonical signature when a secret is configured.
        if ($secret !== '') {
            $base = implode('|', [
                (string) ($data['domain'] ?? ''),
                (string) $data['status'],
                (string) ($data['plan'] ?? ''),
                (string) ($data['expires'] ?? ''),
                (string) ($data['grace'] ?? ''),
                (string) ($data['ts'] ?? ''),
            ]);
            $expected = hash_hmac('sha256', $base, $secret);
            if (! hash_equals($expected, (string) ($data['signature'] ?? ''))) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Medora AI] license signature mismatch — ignoring remote verdict.');
                }
                return; // fail-open
            }
        }

        $verdict = [
            'status'     => sanitize_key($data['status']),
            'plan'       => sanitize_key($data['plan'] ?? 'starter'),
            'expires'    => isset($data['expires']) ? sanitize_text_field((string) $data['expires']) : null,
            'features'   => array_map('sanitize_key', (array) ($data['features'] ?? [])),
            'checked_at' => time(),
        ];

        $prev = $cached['status'] ?? '';
        update_option(self::REMOTE_OPTION, $verdict, false);
        if ($prev !== $verdict['status']) {
            do_action('swc_license_status_changed', $verdict['status']);
            SWC_Audit_Log::record('license_status', ['object' => $verdict['status'], 'severity' => $verdict['status'] === 'active' ? 'info' : 'warning']);
        }
    }

    public static function cron_check(): void
    {
        (new self())->remote_check(false);
    }

    private function license_endpoint(): string
    {
        $base = $this->cloud_base();
        return $base === '' ? '' : $base . '/v1/license/' . self::domain_hash();
    }

    /**
     * Scheme+host of the configured cloud endpoint (…/v1/heartbeat → origin).
     */
    private function cloud_base(): string
    {
        $endpoint = (string) (new SWC_Settings())->get('cloud_endpoint', '');
        if ($endpoint === '') {
            return '';
        }
        $parts = wp_parse_url($endpoint);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }

    private static function domain_hash(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        return hash('sha256', strtolower($host));
    }
}
