<?php

namespace Nobatyar\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    private const NAMESPACE_PREFIX = 'Nobatyar\\';
    private const BASE_DIR = NOBATYAR_PLUGIN_DIR . 'includes/';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (strpos($class, self::NAMESPACE_PREFIX) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen(self::NAMESPACE_PREFIX));
        $relative_path  = str_replace('\\', '/', $relative_class) . '.php';
        $file           = self::BASE_DIR . $relative_path;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
