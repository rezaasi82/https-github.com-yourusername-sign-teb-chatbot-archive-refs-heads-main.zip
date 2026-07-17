<?php

use App\Domain\Licensing\LicenseStatus;

test('revoked is terminal', function () {
    foreach (LicenseStatus::cases() as $target) {
        expect(LicenseStatus::Revoked->canTransitionTo($target))->toBeFalse();
    }
});

test('active can enter grace but not pending', function () {
    expect(LicenseStatus::Active->canTransitionTo(LicenseStatus::Grace))->toBeTrue()
        ->and(LicenseStatus::Active->canTransitionTo(LicenseStatus::Pending))->toBeFalse();
});

test('grace can recover to active after renewal', function () {
    expect(LicenseStatus::Grace->canTransitionTo(LicenseStatus::Active))->toBeTrue();
});

test('expired can be reactivated by renewal', function () {
    expect(LicenseStatus::Expired->canTransitionTo(LicenseStatus::Active))->toBeTrue();
});

test('only active and grace are usable', function () {
    $usable = array_filter(LicenseStatus::cases(), fn ($s) => $s->isUsable());

    expect(array_values($usable))->toEqual([LicenseStatus::Active, LicenseStatus::Grace]);
});
