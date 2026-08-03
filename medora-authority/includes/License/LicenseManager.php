<?php

declare(strict_types=1);

namespace Medora\Authority\License;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Licence state, domain binding and grace-period handling.
 *
 * Two product rules are enforced here, both learned from competitors that get
 * them wrong:
 *
 * 1. A licence-server outage must never lock a customer out. Validation
 *    results are cached and, once expired, a grace window keeps the site fully
 *    functional while retries continue in the background.
 * 2. Domain transfer is self-service. Staging, agency handover and a domain
 *    rename must not require a support ticket.
 */
final class LicenseManager
{
    private const OPTION_KEY   = 'license';
    private const GRACE_DAYS   = 14;
    private const CHECK_WINDOW = DAY_IN_SECONDS;

    /** @var array<string, mixed>|null */
    private ?array $state = null;

    public function __construct(private readonly Options $options)
    {
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        if ($this->state === null) {
            $stored = $this->options->getArray(self::OPTION_KEY);

            $this->state = array_merge([
                'key'          => '',
                'tier'         => LicenseTier::FREE,
                'status'       => LicenseStatus::UNLICENSED,
                'domain_hash'  => '',
                'expires_at'   => '',
                'checked_at'   => '',
                'transfers'    => [],
                'last_error'   => '',
            ], $stored);
        }

        return $this->state;
    }

    public function tier(): string
    {
        $state = $this->state();

        // A hard-expired licence falls back to free rather than blocking the
        // site: data stays intact, premium modules simply stop booting.
        if ($this->status() === LicenseStatus::EXPIRED) {
            return LicenseTier::FREE;
        }

        $tier = (string) $state['tier'];

        /**
         * Filter the effective licence tier. SaaS hosts use this to grant a
         * tier from their own entitlement service.
         *
         * @param string $tier
         */
        return apply_filters('medora_license_tier', LicenseTier::isValid($tier) ? $tier : LicenseTier::FREE);
    }

    public function status(): string
    {
        $state  = $this->state();
        $status = (string) $state['status'];

        if ($status !== LicenseStatus::ACTIVE) {
            return $status;
        }

        $expiresAt = (string) $state['expires_at'];

        if ($expiresAt === '') {
            return LicenseStatus::ACTIVE;
        }

        $expiry = strtotime($expiresAt);

        if ($expiry === false || $expiry > time()) {
            return LicenseStatus::ACTIVE;
        }

        return time() <= $expiry + (self::GRACE_DAYS * DAY_IN_SECONDS)
            ? LicenseStatus::GRACE
            : LicenseStatus::EXPIRED;
    }

    public function allowsTier(string $required): bool
    {
        if ($required === LicenseTier::FREE) {
            return true;
        }

        return LicenseTier::rank($this->tier()) >= LicenseTier::rank($required);
    }

    public function isActive(): bool
    {
        return in_array($this->status(), [LicenseStatus::ACTIVE, LicenseStatus::GRACE], true);
    }

    /** Days left in the grace window, or null when not in grace. */
    public function graceDaysRemaining(): ?int
    {
        if ($this->status() !== LicenseStatus::GRACE) {
            return null;
        }

        $expiry = strtotime((string) $this->state()['expires_at']);

        if ($expiry === false) {
            return null;
        }

        $deadline = $expiry + (self::GRACE_DAYS * DAY_IN_SECONDS);

        return max(0, (int) ceil(($deadline - time()) / DAY_IN_SECONDS));
    }

    /**
     * Bind a licence key to this domain.
     *
     * @return array{ok: bool, message: string, state?: array<string, mixed>}
     */
    public function activate(string $key): array
    {
        $key = trim($key);

        if ($key === '') {
            return ['ok' => false, 'message' => __('Enter a licence key.', 'medora-authority')];
        }

        $response = $this->request('activate', [
            'key'    => $key,
            'domain' => $this->domainHash(),
        ]);

        if (! $response['ok']) {
            $this->persist(['last_error' => $response['message']]);

            return $response;
        }

        $payload = $response['data'];

        $this->persist([
            'key'         => $key,
            'tier'        => (string) ($payload['tier'] ?? LicenseTier::PRO),
            'status'      => LicenseStatus::ACTIVE,
            'domain_hash' => $this->domainHash(),
            'expires_at'  => (string) ($payload['expires_at'] ?? ''),
            'checked_at'  => gmdate('Y-m-d H:i:s'),
            'last_error'  => '',
        ]);

        do_action('medora_license_status_changed', LicenseStatus::ACTIVE, $this->state());

        return ['ok' => true, 'message' => __('Licence activated.', 'medora-authority'), 'state' => $this->publicState()];
    }

    public function deactivate(): array
    {
        $key = (string) $this->state()['key'];

        if ($key !== '') {
            $this->request('deactivate', ['key' => $key, 'domain' => $this->domainHash()]);
        }

        $this->persist([
            'key'         => '',
            'tier'        => LicenseTier::FREE,
            'status'      => LicenseStatus::UNLICENSED,
            'domain_hash' => '',
            'expires_at'  => '',
        ]);

        do_action('medora_license_status_changed', LicenseStatus::UNLICENSED, $this->state());

        return ['ok' => true, 'message' => __('Licence deactivated.', 'medora-authority')];
    }

    /**
     * Self-service domain transfer, rate limited to one move per 30 days so the
     * feature cannot be used to share a single licence across many sites.
     */
    public function transferToCurrentDomain(): array
    {
        $state = $this->state();
        $key   = (string) $state['key'];

        if ($key === '') {
            return ['ok' => false, 'message' => __('No licence key is stored on this site.', 'medora-authority')];
        }

        $transfers = is_array($state['transfers']) ? $state['transfers'] : [];
        $last      = $transfers === [] ? 0 : (int) strtotime((string) end($transfers));

        if ($last > 0 && (time() - $last) < 30 * DAY_IN_SECONDS) {
            return [
                'ok'      => false,
                'message' => __('This licence was transferred within the last 30 days. Contact support if you need another move.', 'medora-authority'),
            ];
        }

        $response = $this->request('transfer', [
            'key'        => $key,
            'domain'     => $this->domainHash(),
            'from'       => (string) $state['domain_hash'],
        ]);

        if (! $response['ok']) {
            return $response;
        }

        $transfers[] = gmdate('Y-m-d H:i:s');

        $this->persist([
            'domain_hash' => $this->domainHash(),
            'status'      => LicenseStatus::ACTIVE,
            'transfers'   => array_slice($transfers, -5),
            'checked_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => __('Licence moved to this domain.', 'medora-authority'), 'state' => $this->publicState()];
    }

    /** Daily cron entry point. */
    public function refresh(bool $force = false): void
    {
        $state = $this->state();

        if ((string) $state['key'] === '') {
            return;
        }

        $checkedAt = strtotime((string) $state['checked_at']) ?: 0;

        if (! $force && (time() - $checkedAt) < self::CHECK_WINDOW) {
            return;
        }

        $previous = $this->status();
        $response = $this->request('status', ['key' => (string) $state['key'], 'domain' => $this->domainHash()]);

        if (! $response['ok']) {
            // Server unreachable: keep the cached entitlement and try tomorrow.
            $this->persist(['last_error' => $response['message']]);

            return;
        }

        $payload = $response['data'];

        $this->persist([
            'tier'       => (string) ($payload['tier'] ?? $state['tier']),
            'status'     => (string) ($payload['status'] ?? LicenseStatus::ACTIVE),
            'expires_at' => (string) ($payload['expires_at'] ?? $state['expires_at']),
            'checked_at' => gmdate('Y-m-d H:i:s'),
            'last_error' => '',
        ]);

        $current = $this->status();

        if ($current !== $previous) {
            do_action('medora_license_status_changed', $current, $this->state());
        }
    }

    /**
     * Licence state safe to expose over REST — the key itself is masked.
     *
     * @return array<string, mixed>
     */
    public function publicState(): array
    {
        $state = $this->state();
        $key   = (string) $state['key'];

        return [
            'tier'            => $this->tier(),
            'tier_label'      => LicenseTier::label($this->tier()),
            'status'          => $this->status(),
            'masked_key'      => $key === '' ? '' : str_repeat('•', max(0, strlen($key) - 4)) . substr($key, -4),
            'expires_at'      => (string) $state['expires_at'],
            'grace_days_left' => $this->graceDaysRemaining(),
            'domain_bound'    => (string) $state['domain_hash'] === $this->domainHash(),
            'last_error'      => (string) $state['last_error'],
        ];
    }

    /** Hashed, not raw — the licence server never learns the customer's domain. */
    private function domainHash(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return hash('sha256', strtolower((string) $host));
    }

    /**
     * @param array<string, string> $body
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    private function request(string $action, array $body): array
    {
        /**
         * Filter the licence API endpoint. Self-hosted and SaaS deployments
         * point this at their own entitlement service.
         *
         * @param string $endpoint
         */
        $endpoint = (string) apply_filters('medora_license_endpoint', 'https://license.medora.ai/v1/');

        $response = wp_remote_post($endpoint . $action, [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json'],
            'body'    => array_merge($body, [
                'product' => 'medora-authority',
                'version' => MEDORA_VERSION,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message(), 'data' => []];
        }

        $code    = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($code < 200 || $code >= 300) {
            return [
                'ok'      => false,
                'message' => (string) ($decoded['message'] ?? __('The licence server rejected the request.', 'medora-authority')),
                'data'    => $decoded,
            ];
        }

        return ['ok' => true, 'message' => '', 'data' => $decoded];
    }

    /** @param array<string, mixed> $changes */
    private function persist(array $changes): void
    {
        $this->state = array_merge($this->state(), $changes);

        $this->options->set(self::OPTION_KEY, $this->state);
    }
}
