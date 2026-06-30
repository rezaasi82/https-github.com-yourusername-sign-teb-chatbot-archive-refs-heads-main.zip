<?php

namespace STMC_Chat\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 autoloader for the STMC_Chat\ namespace mapped to includes/.
 */
class Autoloader
{
    private const NAMESPACE_PREFIX = 'STMC_Chat\\';

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
        $file           = STMC_CHAT_DIR . 'includes/' . $relative_path;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
