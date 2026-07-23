<?php

namespace QRCODR\Core;

use QRCODR\Redirect\RedirectController;
use QRCODR\Admin\Menu;
use QRCODR\Frontend\Shortcode;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public static function boot()
    {
        load_plugin_textdomain('qrcodr', false, dirname(plugin_basename(QRCODR_PLUGIN_FILE)) . '/languages');

        self::maybe_upgrade();

        add_filter('query_vars', array(RedirectController::class, 'register_query_var'));
        add_action('init', array(RedirectController::class, 'register_rewrite_rule'));
        add_action('template_redirect', array(RedirectController::class, 'handle_request'));

        Shortcode::init();

        if (is_admin()) {
            Menu::init();
        }
    }

    private static function maybe_upgrade()
    {
        if (get_option('qrcodr_version') !== QRCODR_VERSION) {
            Activator::create_tables();
            update_option('qrcodr_version', QRCODR_VERSION);
        }
    }
}
