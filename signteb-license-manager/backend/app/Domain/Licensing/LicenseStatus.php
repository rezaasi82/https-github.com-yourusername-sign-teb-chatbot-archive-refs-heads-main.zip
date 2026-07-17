<?php

namespace App\Domain\Licensing;

enum LicenseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /**
     * Allowed state transitions. Revoked is terminal.
     *
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [self::Active, self::Revoked],
            self::Active->value => [self::Grace, self::Expired, self::Suspended, self::Revoked],
            self::Grace->value => [self::Active, self::Expired, self::Suspended, self::Revoked],
            self::Expired->value => [self::Active, self::Revoked],
            self::Suspended->value => [self::Active, self::Grace, self::Revoked],
            self::Revoked->value => [],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitions()[$this->value], true);
    }

    /**
     * Whether /validate should report the license as usable.
     * Grace is usable (soft-lock happens client-side via the `grace` flag).
     */
    public function isUsable(): bool
    {
        return $this === self::Active || $this === self::Grace;
    }
}
