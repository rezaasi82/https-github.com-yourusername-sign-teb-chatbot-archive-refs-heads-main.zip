<?php

namespace QRCODR\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(array(self::class, 'load'));
    }

    public static function load($class)
    {
        if (strpos($class, 'QRCODR\\') !== 0) {
            return;
        }

        $relative = substr($class, strlen('QRCODR\\'));
        $path = QRCODR_PLUGIN_PATH . 'includes/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    }
}
