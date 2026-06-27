<?php

namespace Nobatyar\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tier ranking for gating premium features (Group Booking, Packages,
 * Recurring Appointments, Gift Cards: Business-only; Coupons: Pro+Business).
 * Free-tier sites have no nby_license row at all, so any unrecognized or
 * missing tier value ranks as FREE rather than erroring.
 */
class LicenseTier
{
    public const FREE     = 'free';
    public const PRO      = 'pro';
    public const BUSINESS = 'business';

    private const RANK = [
        self::FREE     => 0,
        self::PRO      => 1,
        self::BUSINESS => 2,
    ];

    public static function meets(string $tier, string $minimum_tier): bool
    {
        return (self::RANK[$tier] ?? 0) >= (self::RANK[$minimum_tier] ?? 0);
    }
}
