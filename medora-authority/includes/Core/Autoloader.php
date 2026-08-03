<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 autoloader for the `Medora\Authority\` namespace.
 *
 * Registered as a fallback so a Composer classmap, when present, always wins.
 */
final class Autoloader
{
    private const PREFIX = 'Medora\\Authority\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load'], true, false);
    }

    public static function load(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file     = MEDORA_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
