<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Medora ships its own capabilities rather than reusing `manage_options`, so
 * an agency can hand an editor access to the authority dashboard without
 * handing them the whole site.
 */
final class Capabilities
{
    public const VIEW_DASHBOARD  = 'medora_view_dashboard';
    public const MANAGE_SETTINGS = 'medora_manage_settings';
    public const MANAGE_ENTITIES = 'medora_manage_entities';
    public const RUN_ANALYSIS    = 'medora_run_analysis';
    public const VIEW_AUDIT_LOG  = 'medora_view_audit_log';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,
            self::MANAGE_SETTINGS,
            self::MANAGE_ENTITIES,
            self::RUN_ANALYSIS,
            self::VIEW_AUDIT_LOG,
        ];
    }

    public static function install(): void
    {
        $administrator = get_role('administrator');

        if ($administrator !== null) {
            foreach (self::all() as $capability) {
                $administrator->add_cap($capability);
            }
        }

        $editor = get_role('editor');

        if ($editor !== null) {
            $editor->add_cap(self::VIEW_DASHBOARD);
            $editor->add_cap(self::RUN_ANALYSIS);
            $editor->add_cap(self::MANAGE_ENTITIES);
        }
    }

    public static function remove(): void
    {
        foreach (wp_roles()->role_objects as $role) {
            foreach (self::all() as $capability) {
                $role->remove_cap($capability);
            }
        }
    }
}
