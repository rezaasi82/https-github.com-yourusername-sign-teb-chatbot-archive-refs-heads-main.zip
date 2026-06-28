<?php

namespace Nobatyar\Admin;

use Nobatyar\Admin\Catalog\ProvidersPage;
use Nobatyar\Admin\Catalog\ServicesPage;
use Nobatyar\Admin\Dashboard\CalendarView;
use Nobatyar\Admin\Dashboard\ListView;
use Nobatyar\Admin\Packages\PackagesPage;
use Nobatyar\Admin\Reports\ReportGenerator;
use Nobatyar\Admin\Settings\SettingsPage;
use Nobatyar\Labels\TerminologyMap;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    public const MENU_SLUG          = 'nobatyar-booking';
    public const CALENDAR_SLUG      = 'nobatyar-booking-calendar';
    public const SERVICES_SLUG      = 'nobatyar-booking-services';
    public const PROVIDERS_SLUG     = 'nobatyar-booking-providers';
    public const REPORTS_SLUG       = 'nobatyar-booking-reports';
    public const SETTINGS_SLUG      = 'nobatyar-booking-settings';
    public const PACKAGES_SLUG      = 'nobatyar-booking-packages';

    private ListView $list_view;
    private CalendarView $calendar_view;
    private ServicesPage $services_page;
    private ProvidersPage $providers_page;
    private ReportGenerator $report_generator;
    private SettingsPage $settings_page;
    private PackagesPage $packages_page;

    public function __construct(
        ListView $list_view,
        CalendarView $calendar_view,
        ServicesPage $services_page,
        ProvidersPage $providers_page,
        ReportGenerator $report_generator,
        SettingsPage $settings_page,
        PackagesPage $packages_page
    ) {
        $this->list_view        = $list_view;
        $this->calendar_view    = $calendar_view;
        $this->services_page    = $services_page;
        $this->providers_page   = $providers_page;
        $this->report_generator = $report_generator;
        $this->settings_page    = $settings_page;
        $this->packages_page    = $packages_page;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action('admin_init', [$this->list_view, 'handle_actions']);
        add_action('admin_init', [$this->services_page, 'handle_submission']);
        add_action('admin_init', [$this->providers_page, 'handle_submission']);
        add_action('admin_init', [$this->settings_page, 'handle_submission']);
        add_action('admin_init', [$this->packages_page, 'handle_submission']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('نوبتیار', 'nobatyar-booking'),
            __('نوبتیار', 'nobatyar-booking'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_list'],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page(self::MENU_SLUG, __('نوبت‌ها', 'nobatyar-booking'), __('نوبت‌ها', 'nobatyar-booking'), 'manage_options', self::MENU_SLUG, [$this, 'render_list']);
        add_submenu_page(self::MENU_SLUG, __('تقویم', 'nobatyar-booking'), __('تقویم', 'nobatyar-booking'), 'manage_options', self::CALENDAR_SLUG, [$this, 'render_calendar']);
        add_submenu_page(self::MENU_SLUG, TerminologyMap::get('service'), TerminologyMap::get('service'), 'manage_options', self::SERVICES_SLUG, [$this, 'render_services']);
        add_submenu_page(self::MENU_SLUG, TerminologyMap::get('provider'), TerminologyMap::get('provider'), 'manage_options', self::PROVIDERS_SLUG, [$this, 'render_providers']);
        add_submenu_page(self::MENU_SLUG, __('گزارش‌ها', 'nobatyar-booking'), __('گزارش‌ها', 'nobatyar-booking'), 'manage_options', self::REPORTS_SLUG, [$this, 'render_reports']);
        add_submenu_page(self::MENU_SLUG, __('پکیج‌ها', 'nobatyar-booking'), __('پکیج‌ها', 'nobatyar-booking'), 'manage_options', self::PACKAGES_SLUG, [$this, 'render_packages']);
        add_submenu_page(self::MENU_SLUG, __('تنظیمات', 'nobatyar-booking'), __('تنظیمات', 'nobatyar-booking'), 'manage_options', self::SETTINGS_SLUG, [$this, 'render_settings']);
    }

    public function render_list(): void
    {
        echo $this->list_view->render($this->filters_from_request());
    }

    public function render_calendar(): void
    {
        $year  = isset($_GET['nby_year']) ? absint($_GET['nby_year']) : null;
        $month = isset($_GET['nby_month']) ? absint($_GET['nby_month']) : null;

        echo $this->calendar_view->render($year ?: null, $month ?: null);
    }

    public function render_services(): void
    {
        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : null;

        echo $this->services_page->render($editing_id ?: null);
    }

    public function render_providers(): void
    {
        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : null;

        echo $this->providers_page->render($editing_id ?: null);
    }

    public function render_reports(): void
    {
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : current_time('Y-m-d') . ' 00:00:00';
        $date_to   = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : current_time('Y-m-d') . ' 23:59:59';

        echo $this->report_generator->render($date_from, $date_to);
    }

    public function render_settings(): void
    {
        $step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'terminology';

        echo $this->settings_page->render($step);
    }

    public function render_packages(): void
    {
        $editing_id = isset($_GET['edit']) ? absint($_GET['edit']) : null;

        echo $this->packages_page->render($editing_id ?: null);
    }

    /**
     * Loads admin CSS only on the plugin's own pages - mirrors
     * BookingShortcode's conditional front-end loading so installing
     * Nobatyar never slows down unrelated admin screens.
     */
    public function maybe_enqueue_assets(string $hook): void
    {
        if (false === strpos($hook, self::MENU_SLUG)) {
            return;
        }

        wp_enqueue_style('nobatyar-admin', NOBATYAR_PLUGIN_URL . 'assets/css/admin.css', [], NOBATYAR_VERSION);
    }

    private function filters_from_request(): array
    {
        $filters = [];

        foreach (['status', 'date_from', 'date_to'] as $key) {
            if (! empty($_GET[$key])) {
                $filters[$key] = sanitize_text_field($_GET[$key]);
            }
        }

        if (! empty($_GET['provider_id'])) {
            $filters['provider_id'] = absint($_GET['provider_id']);
        }

        return $filters;
    }
}
