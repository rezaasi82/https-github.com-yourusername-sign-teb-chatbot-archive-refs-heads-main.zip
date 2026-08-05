<?php
/**
 * PSR-4 style autoloader for the Pezhkam namespace.
 *
 * @package Pezhkam
 */

namespace Pezhkam;

if (! defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    private const PREFIX = 'Pezhkam\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Resolve Pezhkam\Sub\ClassName to includes/sub/class-class-name.php.
     */
    public static function load(string $class): void
    {
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        $parts = explode('\\', substr($class, strlen(self::PREFIX)));
        $short = (string) array_pop($parts);
        $dir   = implode('/', array_map('strtolower', $parts));
        $slug  = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));

        $base       = PZK_DIR . 'includes/' . ($dir !== '' ? $dir . '/' : '');
        $candidates = [$base . 'class-' . $slug . '.php'];

        if (substr($slug, -10) === '-interface') {
            $candidates[] = $base . 'interface-' . substr($slug, 0, -10) . '.php';
        }

        foreach ($candidates as $file) {
            if (is_readable($file)) {
                require_once $file;
                return;
            }
        }
    }
}
