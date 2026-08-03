<?php

declare(strict_types=1);

namespace Medora\Authority\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Commercial tiers, ordered by capability.
 *
 * Modelled as ranked string constants rather than a PHP enum because tiers
 * cross the REST boundary and are persisted in options, where a plain string
 * survives serialisation round-trips without a custom cast.
 */
final class LicenseTier
{
    public const FREE       = 'free';
    public const PRO        = 'pro';
    public const AGENCY     = 'agency';
    public const ENTERPRISE = 'enterprise';

    /** @var array<string, int> */
    private const RANKS = [
        self::FREE       => 0,
        self::PRO        => 10,
        self::AGENCY     => 20,
        self::ENTERPRISE => 30,
    ];

    public static function rank(string $tier): int
    {
        return self::RANKS[$tier] ?? 0;
    }

    public static function isValid(string $tier): bool
    {
        return isset(self::RANKS[$tier]);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::RANKS);
    }

    public static function label(string $tier): string
    {
        return match ($tier) {
            self::PRO        => __('Pro', 'medora-authority'),
            self::AGENCY     => __('Agency', 'medora-authority'),
            self::ENTERPRISE => __('Enterprise', 'medora-authority'),
            default          => __('Free', 'medora-authority'),
        };
    }
}
