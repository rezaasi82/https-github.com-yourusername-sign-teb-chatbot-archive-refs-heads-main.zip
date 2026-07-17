<?php

namespace App\Domain\Usage;

use App\Models\License;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Redis;

/**
 * MEDORA AI token quota enforcement (Module 8).
 *
 * Hot counters live in Redis (atomic INCRBY, TTL to end of month); rows are
 * flushed to usage_records for reporting. Quota comes from the license's plan
 * (`monthly_token_limit`, NULL = no AI entitlement).
 */
class QuotaService
{
    public function remainingTokens(License $license): ?int
    {
        $limit = $license->plan?->monthly_token_limit;

        if ($limit === null) {
            return null; // plan has no AI entitlement
        }

        return max(0, (int) $limit - $this->usedTokens($license));
    }

    public function usedTokens(License $license): int
    {
        return (int) (Redis::get($this->key($license)) ?? 0);
    }

    /**
     * Reserve tokens atomically. Returns false when the quota would be exceeded.
     */
    public function consume(License $license, int $tokens, string $service = 'ai_chat'): bool
    {
        $limit = $license->plan?->monthly_token_limit;

        if ($limit === null) {
            return false;
        }

        $key = $this->key($license);
        $used = (int) Redis::incrby($key, $tokens);
        Redis::expireat($key, now()->endOfMonth()->timestamp);

        if ($used > (int) $limit) {
            Redis::decrby($key, $tokens); // roll back the reservation
            return false;
        }

        UsageRecord::create([
            'license_id' => $license->id,
            'service' => $service,
            'tokens_used' => $tokens,
            'period' => now()->format('Y-m'),
        ]);

        return true;
    }

    public function resetsAt(): string
    {
        return now()->endOfMonth()->toIso8601String();
    }

    private function key(License $license): string
    {
        return sprintf('%s:%d:%s', config('slm.ai.quota_prefix'), $license->id, now()->format('Y-m'));
    }
}
