<?php

declare(strict_types=1);

namespace Medora\Authority\License;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Module\AbstractModule;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Licensing, entitlement and update checking.
 */
final class LicenseModule extends AbstractModule
{
    public function id(): string
    {
        return 'license';
    }

    public function title(): string
    {
        return __('Licensing', 'medora-authority');
    }

    public function description(): string
    {
        return __('Licence activation, self-service domain transfer and automatic updates.', 'medora-authority');
    }

    public function register(Container $container): void
    {
        $container->singleton(
            UpdateChecker::class,
            static fn (Container $c): UpdateChecker => new UpdateChecker($c->get(LicenseManager::class))
        );
    }

    public function boot(Container $container): void
    {
        add_action(Cron::LICENSE_CHECK, static function () use ($container): void {
            $container->get(LicenseManager::class)->refresh();
        });

        $container->get(UpdateChecker::class)->register();

        add_action('admin_notices', function () use ($container): void {
            $this->renderGraceNotice($container);
        });
    }

    private function renderGraceNotice(Container $container): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        $license = $container->get(LicenseManager::class);

        if ($license->status() !== LicenseStatus::GRACE) {
            return;
        }

        $daysLeft = $license->graceDaysRemaining();

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Medora Authority:', 'medora-authority'),
            esc_html(sprintf(
                /* translators: %d: number of days. */
                _n(
                    'Your licence has expired. Everything keeps working for %d more day — renew to avoid interruption.',
                    'Your licence has expired. Everything keeps working for %d more days — renew to avoid interruption.',
                    (int) $daysLeft,
                    'medora-authority'
                ),
                (int) $daysLeft
            ))
        );
    }
}
