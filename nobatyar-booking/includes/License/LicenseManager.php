<?php

namespace Nobatyar\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Talks to the self-hosted license.mynobatyar.ir server and keeps a single
 * cached nby_license row up to date. There is deliberately no domain-lock-in
 * logic here - "transferring" a license to a new domain is just calling
 * activate() again, satisfying the self-service transfer requirement
 * (CLAUDE.md) without any support-desk involvement. Any throttling of
 * repeated transfers is the server's responsibility, not the client's.
 */
class LicenseManager
{
    private const SERVER_VALIDATE_URL = 'https://license.mynobatyar.ir/api/v1/license/validate';

    /**
     * Placeholder shared secret for verifying the server's HMAC signature.
     * Must be overridden via the nobatyar_license_hmac_secret filter to
     * match whatever license.mynobatyar.ir actually signs responses with
     * once that server exists.
     */
    private const DEFAULT_HMAC_SECRET = 'nobatyar-license-hmac-placeholder';

    private GracePeriodHandler $grace_period_handler;

    public function __construct(GracePeriodHandler $grace_period_handler)
    {
        $this->grace_period_handler = $grace_period_handler;
    }

    public function register(): void
    {
        add_action('nobatyar_license_check', [$this, 'check']);
    }

    /**
     * @return array{status:string,tier:string,expires_at:?string}|\WP_Error
     */
    public function activate(string $license_key)
    {
        $result = $this->request_validation($license_key);

        if (is_wp_error($result)) {
            return $result;
        }

        $old_status = $this->current_status();
        $resolved   = $this->grace_period_handler->resolve($result['expires_at']);

        $this->store($license_key, $result['tier'], $resolved, $result['expires_at']);

        if ($resolved !== $old_status) {
            do_action('nobatyar_license_status_changed', $old_status, $resolved);
        }

        return ['status' => $resolved, 'tier' => $result['tier'], 'expires_at' => $result['expires_at']];
    }

    /**
     * @return array{status:string,tier:string,expires_at:?string}|\WP_Error
     */
    public function transfer_domain(string $license_key)
    {
        return $this->activate($license_key);
    }

    /**
     * Daily cron entry point. Network failures must never lock a customer
     * out immediately - the previously cached row (and its status) is left
     * untouched until the server is reachable again.
     */
    public function check(): void
    {
        $row = $this->current_row();

        if (! $row || empty($row['license_key'])) {
            return;
        }

        $result = $this->request_validation($row['license_key']);

        if (is_wp_error($result)) {
            return;
        }

        $old_status = $row['status'];
        $resolved   = $this->grace_period_handler->resolve($result['expires_at']);

        $this->store($row['license_key'], $result['tier'], $resolved, $result['expires_at']);

        if ($resolved !== $old_status) {
            do_action('nobatyar_license_status_changed', $old_status, $resolved);
        }
    }

    public function current_status(): string
    {
        $row = $this->current_row();

        return $row['status'] ?? LicenseStatus::INACTIVE;
    }

    public function current_row(): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row('SELECT * FROM ' . $this->table() . ' ORDER BY id DESC LIMIT 1', ARRAY_A);

        return $row ?: null;
    }

    /**
     * Domain identity is stored hashed, never raw, so a leaked nby_license
     * row (or telemetry sent to the license server) can't be used to fetch
     * or fingerprint a customer's plain domain name.
     */
    public function domain_hash(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return hash('sha256', $host ?: home_url());
    }

    /**
     * Allows overriding the validation endpoint via the
     * nobatyar_license_server_url filter, mirroring the
     * nobatyar_license_hmac_secret filter below - lets a site owner repoint
     * the client without a code edit if the server URL ever changes again.
     */
    private function server_url(): string
    {
        return (string) apply_filters('nobatyar_license_server_url', self::SERVER_VALIDATE_URL);
    }

    /**
     * @return array{tier:string,expires_at:?string}|\WP_Error
     */
    private function request_validation(string $license_key)
    {
        $response = wp_remote_post($this->server_url(), [
            'body' => [
                'license_key'    => $license_key,
                'domain_hash'    => $this->domain_hash(),
                'plugin_version' => NOBATYAR_VERSION,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! $this->verify_signature($body)) {
            return new \WP_Error('nobatyar_license_invalid_response', __('پاسخ سرور لایسنس قابل تأیید نیست.', 'nobatyar-booking'));
        }

        if ('valid' !== ($body['status'] ?? null)) {
            return new \WP_Error('nobatyar_license_invalid_key', __('کد لایسنس نامعتبر یا غیرفعال است.', 'nobatyar-booking'));
        }

        return [
            'tier'       => (string) ($body['tier'] ?? ''),
            'expires_at' => isset($body['expires_at']) ? (string) $body['expires_at'] : null,
        ];
    }

    /**
     * Canonical pipe-joined payload (not re-serialized JSON) so client/server
     * key-ordering never causes a false signature mismatch.
     */
    private function verify_signature(array $body): bool
    {
        if (empty($body['signature']) || ! isset($body['status'], $body['tier'])) {
            return false;
        }

        $payload  = sprintf('%s|%s|%s', $body['status'], $body['tier'], (string) ($body['expires_at'] ?? ''));
        $secret   = (string) apply_filters('nobatyar_license_hmac_secret', self::DEFAULT_HMAC_SECRET);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, (string) $body['signature']);
    }

    private function store(string $license_key, string $tier, string $status, ?string $expires_at): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->table() . ' WHERE license_key != %s', $license_key));

        $existing_id = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $this->table() . ' WHERE license_key = %s', $license_key));

        $data = [
            'license_key'       => $license_key,
            'tier'              => $tier,
            'status'            => $status,
            'last_validated_at' => current_time('mysql'),
            'expires_at'        => $expires_at,
            'domain_hash'       => $this->domain_hash(),
        ];

        if ($existing_id) {
            $wpdb->update($this->table(), $data, ['id' => (int) $existing_id], ['%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);
        } else {
            $wpdb->insert($this->table(), $data, ['%s', '%s', '%s', '%s', '%s', '%s']);
        }
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_license';
    }
}
