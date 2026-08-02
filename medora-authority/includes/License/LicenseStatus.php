<?php

declare(strict_types=1);

namespace Medora\Authority\License;

if (! defined('ABSPATH')) {
    exit;
}

final class LicenseStatus
{
    public const UNLICENSED = 'unlicensed';
    public const ACTIVE     = 'active';
    /** Past expiry but inside the 14-day window; everything still works. */
    public const GRACE      = 'grace';
    public const EXPIRED    = 'expired';
    public const INVALID    = 'invalid';

    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE  => __('Active', 'medora-authority'),
            self::GRACE   => __('Renewal due', 'medora-authority'),
            self::EXPIRED => __('Expired', 'medora-authority'),
            self::INVALID => __('Invalid', 'medora-authority'),
            default       => __('Not licensed', 'medora-authority'),
        };
    }
}
