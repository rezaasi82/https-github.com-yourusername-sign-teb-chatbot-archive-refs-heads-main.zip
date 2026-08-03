<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One reason a page lost points, with the fix.
 *
 * Every deduction must carry a recommendation. A score that says "72/100" and
 * nothing else is a vanity metric; the product promise is that every lost
 * point is explained and actionable.
 */
final class Deduction
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_LOW      = 'low';

    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly float $points,
        public readonly string $recommendation,
        public readonly string $severity = self::SEVERITY_MEDIUM,
        /** Where to go to fix it: 'editor', 'settings', 'author-profile'. */
        public readonly string $fixLocation = 'editor',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code'           => $this->code,
            'label'          => $this->label,
            'points'         => round($this->points, 1),
            'recommendation' => $this->recommendation,
            'severity'       => $this->severity,
            'fix_location'   => $this->fixLocation,
        ];
    }

    /** Sort order for the dashboard: worst impact first. */
    public static function compare(self $a, self $b): int
    {
        $rank = [
            self::SEVERITY_CRITICAL => 0,
            self::SEVERITY_HIGH     => 1,
            self::SEVERITY_MEDIUM   => 2,
            self::SEVERITY_LOW      => 3,
        ];

        $bySeverity = ($rank[$a->severity] ?? 9) <=> ($rank[$b->severity] ?? 9);

        return $bySeverity !== 0 ? $bySeverity : ($b->points <=> $a->points);
    }
}
