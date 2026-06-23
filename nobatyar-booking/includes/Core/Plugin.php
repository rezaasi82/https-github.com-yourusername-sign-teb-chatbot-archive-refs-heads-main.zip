<?php

namespace Nobatyar\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
        add_action('init', [$this, 'load_textdomain']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'nobatyar-booking',
            false,
            dirname(plugin_basename(NOBATYAR_PLUGIN_FILE)) . '/languages'
        );
    }
}
