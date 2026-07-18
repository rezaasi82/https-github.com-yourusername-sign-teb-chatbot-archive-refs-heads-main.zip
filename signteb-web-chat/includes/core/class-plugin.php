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
        (new SWC_Export_Controller())->register_routes();

        // admin-ajax fallback for hosts that block the REST API.
        (new SWC_Chat_Ajax_Handler())->register();

        // Export module: cron retries + automatic lead-event triggers.
        (new SWC_Export_Manager())->register();

        // Level 2 telemetry spine (opt-in; no-op when disabled).
        (new SWC_Cloud_Client())->register();

        // Background jobs + daily analytics rollup (cron workers).
        (new SWC_Rollup())->register();
        (new SWC_Job_Queue())->register();

        // Security audit trail (event listeners + admin viewer).
        (new SWC_Audit_Log())->register();

        // Inline chat via [medora_chat] — registered on both front-end and
        // admin so the shortcode resolves in the block editor preview too.
        (new SWC_Chat_Shortcode())->register();

        // Instant lead alerts to the clinic's Bale / Telegram (fires on the
        // front-end lead-detection hook + the admin test endpoint).
        (new SWC_Messenger_Notifier())->register();

        if (is_admin()) {
            // The parent menu MUST register before any submenu page: WordPress
            // derives each admin page's hookname from the parent menu present
            // at registration time, so a submenu added before its parent ends
            // up unroutable ("Sorry, you are not allowed…" on every click).
            (new SWC_Admin_Menu())->register();
            (new SWC_Chat_Notifier())->register();
            (new SWC_Premium_Dashboard())->register();
            (new SWC_Crm_Board())->register();
            (new SWC_Branches_Page())->register();
            (new SWC_Seo_Page())->register();
            (new SWC_Export_Ajax_Handler())->register();
            (new SWC_Lead_CRM())->register();
        } else {
            (new SWC_Widget())->register();
        }
    }
}
