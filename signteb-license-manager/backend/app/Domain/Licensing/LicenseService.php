<?php

namespace App\Domain\Licensing;

use App\Domain\Licensing\Exceptions\ActivationLimitReached;
use App\Domain\Licensing\Exceptions\IllegalStateTransition;
use App\Domain\Licensing\Exceptions\TransferLimitReached;
use App\Models\Activation;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LicenseService
{
    public function __construct(
        private readonly LicenseKeyGenerator $keys,
        private readonly DomainNormalizer $domains,
    ) {}

    public function create(Customer $customer, Product $product, ?Plan $plan = null, ?\DateTimeInterface $expiresAt = null): License
    {
        $license = License::create([
            'uuid' => (string) Str::uuid(),
            'license_key' => $this->keys->generate($product->key_prefix),
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'plan_id' => $plan?->id,
            'status' => LicenseStatus::Pending,
            'activation_limit' => $plan?->activation_limit ?? config('slm.license.default_activation_limit'),
            'expires_at' => $expiresAt,
        ]);

        $this->audit($license, 'created', meta: ['plan' => $plan?->slug]);

        return $license;
    }

    /**
     * Activate a license on a domain. Idempotent: re-activating an already-active
     * domain refreshes its metadata instead of consuming another slot.
     *
     * @param  array<string, mixed>  $siteMeta  sdk_version, wp_version, php_version, fingerprint, ip
     */
    public function activate(License $license, string $domain, array $siteMeta = []): Activation
    {
        $normalized = $this->domains->normalize($domain);
        $hash = $this->domains->hash($normalized);
        $isDev = $this->domains->isDevDomain($normalized);

        return DB::transaction(function () use ($license, $normalized, $hash, $isDev, $siteMeta) {
            $license = License::whereKey($license->id)->lockForUpdate()->firstOrFail();

            if ($license->status === LicenseStatus::Pending) {
                $this->transition($license, LicenseStatus::Active, 'activated');
            }

            $existing = $license->activations()
                ->where('domain_hash', $hash)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                $existing->update([
                    'last_seen_at' => now(),
                    'ip' => $siteMeta['ip'] ?? $existing->ip,
                    'sdk_version' => $siteMeta['sdk_version'] ?? $existing->sdk_version,
                    'wp_version' => $siteMeta['wp_version'] ?? $existing->wp_version,
                    'php_version' => $siteMeta['php_version'] ?? $existing->php_version,
                ]);

                return $existing;
            }

            if (! $isDev) {
                $used = $license->activations()
                    ->where('is_active', true)
                    ->where('environment', 'production')
                    ->count();

                if ($used >= $license->activation_limit) {
                    $this->audit($license, 'limit_exceeded', meta: ['domain' => $normalized]);
                    throw new ActivationLimitReached($license->activation_limit);
                }
            }

            $activation = $license->activations()->create([
                'domain' => $normalized,
                'domain_hash' => $hash,
                'site_url' => $siteMeta['site_url'] ?? null,
                'ip' => $siteMeta['ip'] ?? null,
                'device_fingerprint' => $siteMeta['fingerprint'] ?? null,
                'environment' => $isDev ? 'local' : 'production',
                'sdk_version' => $siteMeta['sdk_version'] ?? null,
                'wp_version' => $siteMeta['wp_version'] ?? null,
                'php_version' => $siteMeta['php_version'] ?? null,
                'is_active' => true,
                'activated_at' => now(),
                'last_seen_at' => now(),
            ]);

            $this->audit($license, 'activated', meta: ['domain' => $normalized, 'dev' => $isDev]);

            return $activation;
        });
    }

    public function deactivate(License $license, string $domain): void
    {
        $hash = $this->domains->hash($domain);

        $updated = $license->activations()
            ->where('domain_hash', $hash)
            ->where('is_active', true)
            ->update(['is_active' => false, 'deactivated_at' => now()]);

        if ($updated > 0) {
            $this->audit($license, 'deactivated', meta: ['domain' => $this->domains->normalize($domain)]);
        }
    }

    /**
     * Self-service domain transfer: deactivate old, activate new — limited per month
     * so keys can't be hot-swapped across a fleet of sites.
     */
    public function transfer(License $license, string $fromDomain, string $toDomain, array $siteMeta = []): Activation
    {
        $monthlyLimit = (int) config('slm.license.max_transfers_per_month');

        $transfersThisMonth = $license->events()
            ->where('event', 'transferred')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($transfersThisMonth >= $monthlyLimit) {
            throw new TransferLimitReached($monthlyLimit);
        }

        return DB::transaction(function () use ($license, $fromDomain, $toDomain, $siteMeta) {
            $this->deactivate($license, $fromDomain);
            $activation = $this->activate($license, $toDomain, $siteMeta);

            $license->increment('transfers_used');
            $this->audit($license, 'transferred', 'customer', meta: [
                'from' => $this->domains->normalize($fromDomain),
                'to' => $this->domains->normalize($toDomain),
            ]);

            return $activation;
        });
    }

    /**
     * Validation payload for the SDK. Also refreshes last_seen_at (heartbeat).
     *
     * @return array<string, mixed>
     */
    public function validate(License $license, string $domain): array
    {
        $hash = $this->domains->hash($domain);

        $activation = $license->activations()
            ->where('domain_hash', $hash)
            ->where('is_active', true)
            ->first();

        $activation?->update(['last_seen_at' => now()]);

        $status = $license->status;
        $valid = $status->isUsable() && $activation !== null;

        if (! $valid) {
            $this->audit($license, 'validated_fail', 'api', meta: [
                'domain' => $this->domains->normalize($domain),
                'status' => $status->value,
                'activated' => $activation !== null,
            ]);
        }

        return [
            'valid' => $valid,
            'status' => $status->value,
            'grace' => $status === LicenseStatus::Grace,
            'reason' => $valid ? null : ($activation === null ? 'domain_not_activated' : 'license_'.$status->value),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'grace_ends_at' => $license->grace_ends_at?->toIso8601String(),
            'activations_used' => $license->activations()->where('is_active', true)->count(),
            'activation_limit' => $license->activation_limit,
            'product' => $license->product->slug,
            'plan' => $license->plan?->tier,
        ];
    }

    public function transition(License $license, LicenseStatus $to, string $event, string $actorType = 'system', ?int $actorId = null): void
    {
        if (! $license->status->canTransitionTo($to)) {
            throw new IllegalStateTransition($license->status, $to);
        }

        $license->update([
            'status' => $to,
            'grace_ends_at' => $to === LicenseStatus::Grace
                ? now()->addDays($license->plan?->grace_days ?? config('slm.license.default_grace_days'))
                : null,
        ]);

        $this->audit($license, $event, $actorType, $actorId);
    }

    private function audit(License $license, string $event, string $actorType = 'system', ?int $actorId = null, array $meta = []): void
    {
        $license->events()->create([
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'ip' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
