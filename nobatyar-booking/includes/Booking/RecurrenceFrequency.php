<?php

namespace Nobatyar\Booking;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recurrence patterns for Recurring Appointments (Business-tier only).
 * Monthly advances via DateTimeImmutable's native "+1 month" arithmetic,
 * which rolls over for short months (e.g. Jan 31 -> Mar 3) - an accepted
 * limitation rather than custom day-clamping logic.
 */
class RecurrenceFrequency
{
    public const WEEKLY   = 'weekly';
    public const BIWEEKLY = 'biweekly';
    public const MONTHLY  = 'monthly';

    private const INTERVALS = [
        self::WEEKLY   => '+1 week',
        self::BIWEEKLY => '+2 weeks',
        self::MONTHLY  => '+1 month',
    ];

    public static function is_valid(string $frequency): bool
    {
        return isset(self::INTERVALS[$frequency]);
    }

    public static function advance(\DateTimeImmutable $date, string $frequency): \DateTimeImmutable
    {
        return $date->modify(self::INTERVALS[$frequency]);
    }

    public static function all(): array
    {
        return array_keys(self::INTERVALS);
    }
}
