<?php

namespace Nobatyar\Coupons;

if (! defined('ABSPATH')) {
    exit;
}

class CouponDiscountType
{
    public const PERCENT = 'percent';
    public const FIXED   = 'fixed';

    public static function all(): array
    {
        return [self::PERCENT, self::FIXED];
    }

    public static function is_valid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
