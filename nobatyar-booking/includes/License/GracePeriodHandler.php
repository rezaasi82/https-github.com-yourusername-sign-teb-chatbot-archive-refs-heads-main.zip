<?php

namespace Nobatyar\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns a license's raw expiry date into one of ACTIVE/GRACE/LOCKED and
 * tells callers which feature tiers that status should disable - the
 * 14-day grace window is a non-negotiable acceptance criterion (CLAUDE.md):
 * SMS/payment soft-lock first, booking itself only hard-locks after grace.
 */
class GracePeriodHandler
{
    public const GRACE_PERIOD_DAYS = 14;

    public function resolve(?string $expires_at): string
    {
        if (! $expires_at) {
            return LicenseStatus::INACTIVE;
        }

        $expires = strtotime($expires_at . ' 23:59:59');
        $now     = time();

        if (false === $expires || $now <= $expires) {
            return LicenseStatus::ACTIVE;
        }

        $grace_ends = $expires + (self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS);

        return $now <= $grace_ends ? LicenseStatus::GRACE : LicenseStatus::LOCKED;
    }

    /**
     * Soft lock: SMS and online payment must be disabled.
     */
    public function is_feature_locked(string $status): bool
    {
        return in_array($status, [LicenseStatus::GRACE, LicenseStatus::LOCKED], true);
    }

    /**
     * Full lock: the front-end booking flow itself must be disabled.
     * Data is never deleted regardless of this flag.
     */
    public function is_fully_locked(string $status): bool
    {
        return LicenseStatus::LOCKED === $status;
    }
}
