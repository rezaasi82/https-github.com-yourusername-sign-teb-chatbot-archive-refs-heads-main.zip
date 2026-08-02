<?php

declare(strict_types=1);

namespace Medora\Authority\Analytics;

use Medora\Authority\Support\Cache;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Period-over-period comparison for the analytics dashboard.
 */
final class TrendCalculator
{
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public function __construct(
        private readonly ReferralRepository $referrals,
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array{
     *     window_days: int,
     *     total_visits: int,
     *     previous_visits: int,
     *     change_percent: float|null,
     *     sources: list<array<string, mixed>>,
     *     series: list<array<string, mixed>>,
     *     top_pages: list<array<string, mixed>>
     * }
     */
    public function report(int $days = 30): array
    {
        return $this->cache->remember(
            sprintf('referral_report_%d', $days),
            self::CACHE_TTL,
            function () use ($days): array {
                $current = $this->referrals->countSince($days);
                // Visits in the window *before* this one: total over twice the
                // period, minus the current period.
                $previous = max(0, $this->referrals->countSince($days * 2) - $current);

                return [
                    'window_days'     => $days,
                    'total_visits'    => $current,
                    'previous_visits' => $previous,
                    'change_percent'  => $this->percentChange($previous, $current),
                    'sources'         => $this->referrals->totalsBySource($days),
                    'series'          => $this->referrals->dailySeries($days),
                    'top_pages'       => $this->referrals->topLandingPages($days),
                ];
            }
        );
    }

    /**
     * Null rather than a fabricated percentage when there is no baseline —
     * "+100%" from a base of zero is noise, not a trend.
     */
    private function percentChange(int $previous, int $current): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
