<?php
/**
 * \Medora\Core\Plugin — main orchestrator. Wires WordPress hooks to subsystems.
 *
 * The plugin is fully standalone: it never assumes any other plugin or theme
 * is present, and reads all clinic content from its own settings.
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
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
        \Medora\Core\Activator::maybe_upgrade();

        // REST transport (preferred).
        (new \Medora\Rest\ChatController())->register_routes();
        (new \Medora\Rest\ExportController())->register_routes();

        // admin-ajax fallback for hosts that block the REST API.
        (new \Medora\Ajax\ChatAjaxHandler())->register();

        // Export module: cron retries + automatic lead-event triggers.
        (new \Medora\Export\ExportManager())->register();

        // Level 2 telemetry spine (opt-in; no-op when disabled).
        (new \Medora\Cloud\CloudClient())->register();

        // Background jobs + daily analytics rollup (cron workers).
        (new \Medora\Jobs\Rollup())->register();
        (new \Medora\Jobs\JobQueue())->register();

        // Security audit trail (event listeners + admin viewer).
        (new \Medora\Security\AuditLog())->register();

        // Inline chat via [medora_chat] — registered on both front-end and
        // admin so the shortcode resolves in the block editor preview too.
        (new \Medora\Frontend\ChatShortcode())->register();

        // Instant lead alerts to the clinic's Bale / Telegram (fires on the
        // front-end lead-detection hook + the admin test endpoint).
        (new \Medora\Notifications\MessengerNotifier())->register();

        if (is_admin()) {
            // The parent menu MUST register before any submenu page: WordPress
            // derives each admin page's hookname from the parent menu present
            // at registration time, so a submenu added before its parent ends
            // up unroutable ("Sorry, you are not allowed…" on every click).
            (new \Medora\Admin\AdminMenu())->register();
            (new \Medora\Admin\ChatNotifier())->register();
            (new \Medora\Admin\PremiumDashboard())->register();
            (new \Medora\Admin\CrmBoard())->register();
            (new \Medora\Admin\BranchesPage())->register();
            (new \Medora\Seo\SeoPage())->register();
            (new \Medora\Ajax\ExportAjaxHandler())->register();
            (new \Medora\Crm\LeadCrm())->register();
        } else {
            (new \Medora\Frontend\Widget())->register();
        }
    }
}
