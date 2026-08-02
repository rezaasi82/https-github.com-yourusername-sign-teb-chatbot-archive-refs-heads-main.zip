<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

use Medora\Authority\Core\Container;
use Medora\Authority\Module\AbstractModule;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard — mounts the React app, the editor meta box and the setup wizard.
 */
final class AdminModule extends AbstractModule
{
    public function id(): string
    {
        return 'admin';
    }

    public function title(): string
    {
        return __('Dashboard', 'medora-authority');
    }

    public function description(): string
    {
        return __('The Medora admin experience: authority dashboard, entity explorer and setup wizard.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['rest'];
    }

    public function register(Container $container): void
    {
        $container->singleton(AssetManager::class, static fn (): AssetManager => new AssetManager());
        $container->singleton(
            AdminMenu::class,
            static fn (Container $c): AdminMenu => new AdminMenu($c, $c->get(AssetManager::class))
        );
    }

    public function boot(Container $container): void
    {
        $container->get(AdminMenu::class)->register();

        (new MetaBox($container))->register();
        (new SetupWizard($container))->register();
    }
}
