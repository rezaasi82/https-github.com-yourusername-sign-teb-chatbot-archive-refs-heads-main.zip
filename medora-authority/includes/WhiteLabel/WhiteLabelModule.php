<?php

declare(strict_types=1);

namespace Medora\Authority\WhiteLabel;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * White Label Framework.
 *
 * Lets an agency present the platform under their own brand: menu name, admin
 * colours, support links and the plugin row itself. Every value flows through
 * `BrandingManager`, so a SaaS host can force branding from code without the
 * customer being able to change it.
 */
final class WhiteLabelModule extends AbstractModule
{
    public function id(): string
    {
        return 'white_label';
    }

    public function title(): string
    {
        return __('White Label', 'medora-authority');
    }

    public function description(): string
    {
        return __('Present the platform under your own brand.', 'medora-authority');
    }

    public function requiredTier(): string
    {
        return LicenseTier::AGENCY;
    }

    public function enabledByDefault(): bool
    {
        return false;
    }

    public function register(Container $container): void
    {
        $container->singleton(
            BrandingManager::class,
            static fn (Container $c): BrandingManager => new BrandingManager($c->get(Options::class))
        );
    }

    public function boot(Container $container): void
    {
        $branding = $container->get(BrandingManager::class);

        if (! $branding->isActive()) {
            return;
        }

        $branding->register();
    }
}
