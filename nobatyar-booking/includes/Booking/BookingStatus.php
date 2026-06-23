<?php

namespace Nobatyar\Booking;

if (! defined('ABSPATH')) {
    exit;
}

class BookingStatus
{
    public const PENDING   = 'pending';
    public const CONFIRMED = 'confirmed';
    public const DONE      = 'done';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW    = 'no_show';

    public const ACTIVE = [self::PENDING, self::CONFIRMED];

    public static function all(): array
    {
        return [self::PENDING, self::CONFIRMED, self::DONE, self::CANCELLED, self::NO_SHOW];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
