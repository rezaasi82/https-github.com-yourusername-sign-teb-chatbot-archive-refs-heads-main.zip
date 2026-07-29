<?php
/**
 * Main orchestrator. Wires WordPress hooks to subsystems.
 *
 * The plugin is fully standalone: it never assumes any other plugin or theme
 * is present, and reads all clinic content from its own settings.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private bool $booted = false;

    public static function start(): void
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        $instance->boot();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('signteb-web-chat', false, dirname(SWC_BASENAME) . '/languages');

        // Lightweight schema check in case the plugin was updated in place.
        \SignTeb\WebChat\Core\Activator::maybe_upgrade();

        // REST transport (preferred).
        (new \SignTeb\WebChat\Rest\ChatController())->register_routes();
        (new \SignTeb\WebChat\Rest\ExportController())->register_routes();

        // admin-ajax fallback for hosts that block the REST API.
        (new \SignTeb\WebChat\Ajax\ChatAjaxHandler())->register();

        // Export module: cron retries + automatic lead-event triggers.
        (new \SignTeb\WebChat\Export\ExportManager())->register();

        // Level 2 telemetry spine (opt-in; no-op when disabled).
        (new \SignTeb\WebChat\Cloud\CloudClient())->register();

        // Background jobs + daily analytics rollup (cron workers).
        (new \SignTeb\WebChat\Jobs\Rollup())->register();
        (new \SignTeb\WebChat\Jobs\JobQueue())->register();

        // Security audit trail (event listeners + admin viewer).
        (new \SignTeb\WebChat\Security\AuditLog())->register();

        // Inline chat via [medora_chat] — registered on both front-end and
        // admin so the shortcode resolves in the block editor preview too.
        (new \SignTeb\WebChat\Frontend\ChatShortcode())->register();

        // Instant lead alerts to the clinic's Bale / Telegram (fires on the
        // front-end lead-detection hook + the admin test endpoint).
        (new \SignTeb\WebChat\Notifications\MessengerNotifier())->register();

        if (is_admin()) {
            // The parent menu MUST register before any submenu page: WordPress
            // derives each admin page's hookname from the parent menu present
            // at registration time, so a submenu added before its parent ends
            // up unroutable ("Sorry, you are not allowed…" on every click).
            (new \SignTeb\WebChat\Admin\AdminMenu())->register();
            (new \SignTeb\WebChat\Admin\ChatNotifier())->register();
            (new \SignTeb\WebChat\Admin\PremiumDashboard())->register();
            (new \SignTeb\WebChat\Admin\CrmBoard())->register();
            (new \SignTeb\WebChat\Admin\BranchesPage())->register();
            (new \SignTeb\WebChat\Seo\SeoPage())->register();
            (new \SignTeb\WebChat\Ajax\ExportAjaxHandler())->register();
            (new \SignTeb\WebChat\Crm\LeadCrm())->register();
        } else {
            (new \SignTeb\WebChat\Frontend\Widget())->register();
        }
    }
}
