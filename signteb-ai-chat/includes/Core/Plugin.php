<?php

namespace STMC_Chat\Core;

use STMC_Chat\Admin\AdminMenu;
use STMC_Chat\Ajax\ChatAjaxHandler;
use STMC_Chat\Frontend\Widget;
use STMC_Chat\Rest\ChatController;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Main orchestrator. Wires WordPress hooks to the plugin subsystems.
 *
 * Decoupled-but-integratable: the chatbot works standalone and only
 * binds to SignTeb Medical Core when that plugin is detected.
 */
class Plugin
{
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('signteb-ai-chat', false, dirname(STMC_CHAT_BASENAME) . '/languages');

        // Run a lightweight schema check in case the plugin was updated.
        Activator::maybe_upgrade();

        // REST API (preferred) — registered on rest_api_init.
        (new ChatController())->register_routes();

        // admin-ajax fallback for cheap hosts that block the REST API.
        (new ChatAjaxHandler())->register();

        if (is_admin()) {
            (new AdminMenu())->register();
        } else {
            (new Widget())->register();
        }
    }
}
