<?php

declare(strict_types=1);

namespace Medora\Authority\Module;

use Medora\Authority\Core\Container;
use Medora\Authority\License\LicenseTier;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Convenience base class supplying sane defaults for optional module hooks.
 */
abstract class AbstractModule implements ModuleInterface
{
    public function description(): string
    {
        return '';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function requiredTier(): string
    {
        return LicenseTier::FREE;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }
}
