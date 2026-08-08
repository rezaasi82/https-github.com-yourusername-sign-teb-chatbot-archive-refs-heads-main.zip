<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Helpers\Asset;
use SignTeb\VideoHub\Rest\RestNamespace;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the "ویدئو هاب" menu and wires the admin screens.
 */
class AdminMenu
{
    public const SLUG = 'stvh-hub';

    private Settings $settings;
    private DashboardPage $dashboard;
    private SettingsPage $settings_page;
    private AnalyticsPage $analytics;

    public function __construct(?Settings $settings = null)
    {
        $this->settings      = $settings ?? new Settings();
        $this->dashboard     = new DashboardPage($this->settings);
        $this->settings_page = new SettingsPage($this->settings);
        $this->analytics     = new AnalyticsPage($this->settings);
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->settings_page, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        (new VideoMetaBox($this->settings))->register();
    }

    public function menu(): void
    {
        $cap = 'manage_options';

        add_menu_page(
            __('ویدئو هاب ساین‌طب', 'signteb-video-hub'),
            __('ویدئو هاب', 'signteb-video-hub'),
            $cap,
            self::SLUG,
            [$this->dashboard, 'render'],
            'dashicons-video-alt3',
            56
        );

        add_submenu_page(
            self::SLUG,
            __('داشبورد', 'signteb-video-hub'),
            __('داشبورد', 'signteb-video-hub'),
            $cap,
            self::SLUG,
            [$this->dashboard, 'render']
        );

        // The video list/editor screens attach here via show_in_menu on the
        // post type, so only the plugin-specific pages are declared.
        add_submenu_page(
            self::SLUG,
            __('آمار و تحلیل', 'signteb-video-hub'),
            __('آمار و تحلیل', 'signteb-video-hub'),
            $cap,
            'stvh-analytics',
            [$this->analytics, 'render']
        );

        add_submenu_page(
            self::SLUG,
            __('تنظیمات', 'signteb-video-hub'),
            __('تنظیمات', 'signteb-video-hub'),
            $cap,
            'stvh-settings',
            [$this->settings_page, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        $is_plugin_screen = str_contains($hook, self::SLUG)
            || str_contains($hook, 'stvh-')
            || $this->is_video_screen();

        if (! $is_plugin_screen) {
            return;
        }

        wp_enqueue_style('stvh-admin', Asset::url('assets/css/admin.css'), [], Asset::version('assets/css/admin.css'));
        wp_enqueue_script('stvh-admin', Asset::url('assets/js/admin.js'), [], Asset::version('assets/js/admin.js'), true);

        wp_localize_script('stvh-admin', 'STVH_ADMIN', [
            'restUrl' => esc_url_raw(rest_url(RestNamespace::NAME . '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'working' => __('در حال انجام…', 'signteb-video-hub'),
                'failed'  => __('عملیات ناموفق بود.', 'signteb-video-hub'),
                'confirm' => __('مطمئن هستید؟', 'signteb-video-hub'),
                'wholeChannel' => __('کل کانال', 'signteb-video-hub'),
            ],
        ]);
    }

    private function is_video_screen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen !== null && $screen->post_type === \SignTeb\VideoHub\Core\PostType::POST_TYPE;
    }
}
