<?php

declare(strict_types=1);

namespace Medora\Authority\Security;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Cron;
use Medora\Authority\Core\Options;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Hash;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Security — audit logging and configuration scanning.
 *
 * Cannot be disabled: a customer turning off their own audit trail is not a
 * feature, and the compliance story depends on the log being continuous.
 */
final class SecurityModule extends AbstractModule
{
    public function id(): string
    {
        return 'security';
    }

    public function title(): string
    {
        return __('Security', 'medora-authority');
    }

    public function description(): string
    {
        return __('Audit trail, configuration scanning and capability enforcement.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->singleton(AuditLogRepository::class, static fn (): AuditLogRepository => new AuditLogRepository());
    }

    public function boot(Container $container): void
    {
        $audit = $container->get(AuditLogRepository::class);
        $hash  = $container->get(Hash::class);

        add_action('medora_settings_updated', static function (array $settings) use ($audit, $hash): void {
            $audit->record('settings.updated', 'option', 0, ['keys' => array_keys($settings)], $hash->visitorIp());
        });

        add_action('medora_module_toggled', static function (string $id, bool $enabled) use ($audit, $hash): void {
            $audit->record('module.toggled', 'module', 0, ['module' => $id, 'enabled' => $enabled], $hash->visitorIp());
        }, 10, 2);

        add_action('medora_license_status_changed', static function (string $status) use ($audit, $hash): void {
            $audit->record('license.status_changed', 'license', 0, ['status' => $status], $hash->visitorIp());
        });

        add_action('medora_crawler_policy_changed', static function (string $slug, ?string $decision) use ($audit, $hash): void {
            $audit->record('crawler.policy_changed', 'crawler', 0, ['crawler' => $slug, 'decision' => $decision], $hash->visitorIp());
        }, 10, 2);

        add_action(Cron::PRUNE_LOGS, static function () use ($container, $audit): void {
            // Audit rows are kept at least twice as long as operational logs;
            // they are the record you need after an incident, not during it.
            $retention = $container->get(Options::class)->getInt('retention_days', 180);
            $audit->prune(max(90, $retention * 2));
        });
    }
}
