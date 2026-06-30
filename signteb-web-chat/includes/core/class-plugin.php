<?php
/**
 * SWC_Plugin — main orchestrator. Wires WordPress hooks to subsystems.
 *
 * The plugin is fully standalone: it never assumes any other plugin or theme
 * is present, and reads all clinic content from its own settings.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Plugin
{
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('signteb-web-chat', false, dirname(SWC_BASENAME) . '/languages');

        // Lightweight schema check in case the plugin was updated in place.
        SWC_Activator::maybe_upgrade();

        // REST transport (preferred).
        (new SWC_Chat_Controller())->register_routes();

        // admin-ajax fallback for hosts that block the REST API.
        (new SWC_Chat_Ajax_Handler())->register();

        if (is_admin()) {
            (new SWC_Admin_Menu())->register();
        } else {
            (new SWC_Widget())->register();
        }
    }
}
