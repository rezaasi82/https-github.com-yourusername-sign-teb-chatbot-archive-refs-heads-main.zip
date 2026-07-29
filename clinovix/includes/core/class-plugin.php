<?php
/**
 * Main orchestrator. Wires WordPress hooks to subsystems.
 *
 * The plugin is fully standalone: it never assumes any other plugin or theme
 * is present, and reads all clinic content from its own settings.
 *
 * @package Clinovix
 */

namespace Clinovix\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private const LICENSE_CLASS = 'RTL_License_0949e1086a8664d9';
    private const LICENSE_HASH  = '7a08b73e61f2c61ca287e1f88650085a47328a54';

    private bool $booted = false;

    /**
     * Single entry point for the activation gate.
     *
     * The gate that calls this lives in an encoded file, and this method
     * independently re-checks the purchase licence, so the plugin cannot be
     * started by simply replacing that file.
     */
    public static function start(): void
    {
        if (! self::licensed()) {
            return;
        }

        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        $instance->boot();
    }

    private static function licensed(): bool
    {
        $class = self::LICENSE_CLASS;
        if (! class_exists($class) || ! method_exists($class, 'isActive')) {
            return false;
        }

        $file = CLX_DIR . 'includes/' . $class . '.php';
        if (! is_readable($file) || ! hash_equals(self::LICENSE_HASH, (string) @sha1_file($file))) {
            return false;
        }

        return (new $class())->{'isActive'}() === true;
    }

    public function boot(): void
    {
        if ($this->booted || ! self::licensed()) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('clinovix', false, dirname(CLX_BASENAME) . '/languages');

        // Lightweight schema check in case the plugin was updated in place.
        \Clinovix\Core\Activator::maybe_upgrade();

        // REST transport (preferred).
        (new \Clinovix\Rest\ChatController())->register_routes();
        (new \Clinovix\Rest\ExportController())->register_routes();

        // admin-ajax fallback for hosts that block the REST API.
        (new \Clinovix\Ajax\ChatAjaxHandler())->register();

        // Export module: cron retries + automatic lead-event triggers.
        (new \Clinovix\Export\ExportManager())->register();

        // Level 2 telemetry spine (opt-in; no-op when disabled).
        (new \Clinovix\Cloud\CloudClient())->register();

        // Background jobs + daily analytics rollup (cron workers).
        (new \Clinovix\Jobs\Rollup())->register();
        (new \Clinovix\Jobs\JobQueue())->register();

        // Security audit trail (event listeners + admin viewer).
        (new \Clinovix\Security\AuditLog())->register();

        // Inline chat via [clinovix_chat] — registered on both front-end and
        // admin so the shortcode resolves in the block editor preview too.
        (new \Clinovix\Frontend\ChatShortcode())->register();

        // Instant lead alerts to the clinic's Bale / Telegram (fires on the
        // front-end lead-detection hook + the admin test endpoint).
        (new \Clinovix\Notifications\MessengerNotifier())->register();

        if (is_admin()) {
            // The parent menu MUST register before any submenu page: WordPress
            // derives each admin page's hookname from the parent menu present
            // at registration time, so a submenu added before its parent ends
            // up unroutable ("Sorry, you are not allowed…" on every click).
            (new \Clinovix\Admin\AdminMenu())->register();
            (new \Clinovix\Admin\ChatNotifier())->register();
            (new \Clinovix\Admin\PremiumDashboard())->register();
            (new \Clinovix\Admin\CrmBoard())->register();
            (new \Clinovix\Admin\BranchesPage())->register();
            (new \Clinovix\Seo\SeoPage())->register();
            (new \Clinovix\Ajax\ExportAjaxHandler())->register();
            (new \Clinovix\Crm\LeadCrm())->register();
        } else {
            (new \Clinovix\Frontend\Widget())->register();
        }
    }
}
