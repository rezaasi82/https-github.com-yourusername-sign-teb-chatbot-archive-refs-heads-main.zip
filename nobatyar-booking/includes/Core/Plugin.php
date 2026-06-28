<?php

namespace Nobatyar\Core;

use Nobatyar\Admin\AdminMenu;
use Nobatyar\Admin\Catalog\ProvidersPage;
use Nobatyar\Admin\Catalog\ServicesPage;
use Nobatyar\Admin\Dashboard\CalendarView;
use Nobatyar\Admin\Dashboard\ListView;
use Nobatyar\Admin\Packages\PackagesPage;
use Nobatyar\Admin\Reports\ReportGenerator;
use Nobatyar\Admin\Settings\SettingsPage;
use Nobatyar\Admin\Coupons\CouponsPage;
use Nobatyar\Booking\BookingEngine;
use Nobatyar\Booking\BookingRepository;
use Nobatyar\Booking\SlotCalculator;
use Nobatyar\Coupons\CouponEngine;
use Nobatyar\Coupons\CouponRepository;
use Nobatyar\Frontend\Shortcode\BookingShortcode;
use Nobatyar\Frontend\Shortcode\PackagesShortcode;
use Nobatyar\License\GracePeriodHandler;
use Nobatyar\License\LicenseManager;
use Nobatyar\Notifications\EmailNotifier;
use Nobatyar\Notifications\NotificationDispatcher;
use Nobatyar\Notifications\SmsLogRepository;
use Nobatyar\Packages\PackageEngine;
use Nobatyar\Packages\PackageRepository;
use Nobatyar\Payment\PaymentEngine;
use Nobatyar\Payment\TransactionRepository;
use Nobatyar\Provider\AvailabilityManager;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Rest\Controllers\AvailabilityController;
use Nobatyar\Rest\Controllers\BookingController;
use Nobatyar\Rest\Controllers\CouponController;
use Nobatyar\Rest\Controllers\LicenseController;
use Nobatyar\Rest\Controllers\PackageController;
use Nobatyar\Rest\Controllers\PaymentController;
use Nobatyar\Service\ServiceRepository;

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
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        $this->booking_shortcode()->register();
        $this->packages_shortcode()->register();
        $this->notification_dispatcher()->register();
        $this->license_manager()->register();
        $this->admin_menu()->register();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'nobatyar-booking',
            false,
            dirname(plugin_basename(NOBATYAR_PLUGIN_FILE)) . '/languages'
        );
    }

    public function register_rest_routes(): void
    {
        $booking_repository  = new BookingRepository();
        $provider_repository = new ProviderRepository();
        $service_repository  = new ServiceRepository();
        $package_repository  = new PackageRepository();
        $coupon_repository   = new CouponRepository();

        $coupon_engine   = new CouponEngine($coupon_repository, $this->license_manager());
        $booking_engine  = new BookingEngine($booking_repository, $provider_repository, $service_repository, $this->license_manager(), $coupon_engine);
        $slot_calculator = new SlotCalculator(new AvailabilityManager(), $booking_repository);
        $payment_engine  = new PaymentEngine(new TransactionRepository(), $booking_repository, $service_repository, $coupon_repository);
        $package_engine  = new PackageEngine($package_repository, $booking_engine, $booking_repository, $service_repository, $this->license_manager());

        (new BookingController($booking_engine, $booking_repository))->register_routes();
        (new AvailabilityController($slot_calculator, $service_repository))->register_routes();
        (new PaymentController($payment_engine))->register_routes();
        (new LicenseController($this->license_manager()))->register_routes();
        (new PackageController($package_engine, $package_repository))->register_routes();
        (new CouponController($coupon_engine))->register_routes();
    }

    private function booking_shortcode(): BookingShortcode
    {
        return new BookingShortcode(new ProviderRepository(), new ServiceRepository(), $this->license_manager());
    }

    private function packages_shortcode(): PackagesShortcode
    {
        return new PackagesShortcode(new PackageRepository(), new ServiceRepository(), $this->license_manager());
    }

    private function notification_dispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher(
            new BookingRepository(),
            new ProviderRepository(),
            new ServiceRepository(),
            new SmsLogRepository(),
            new EmailNotifier()
        );
    }

    private function license_manager(): LicenseManager
    {
        return new LicenseManager(new GracePeriodHandler());
    }

    private function admin_menu(): AdminMenu
    {
        $booking_repository  = new BookingRepository();
        $provider_repository = new ProviderRepository();
        $service_repository  = new ServiceRepository();
        $package_repository  = new PackageRepository();
        $coupon_repository   = new CouponRepository();

        $coupon_engine  = new CouponEngine($coupon_repository, $this->license_manager());
        $booking_engine = new BookingEngine($booking_repository, $provider_repository, $service_repository, $this->license_manager(), $coupon_engine);

        $list_view = new ListView(
            $booking_repository,
            $provider_repository,
            $service_repository,
            $booking_engine
        );

        $package_engine = new PackageEngine($package_repository, $booking_engine, $booking_repository, $service_repository, $this->license_manager());

        return new AdminMenu(
            $list_view,
            new CalendarView($booking_repository),
            new ServicesPage($service_repository, $this->license_manager()),
            new ProvidersPage($provider_repository, $service_repository),
            new ReportGenerator($booking_repository, new TransactionRepository()),
            new SettingsPage($this->license_manager()),
            new PackagesPage($package_engine, $package_repository, $service_repository, $this->license_manager()),
            new CouponsPage($coupon_engine, $coupon_repository, $service_repository, $this->license_manager())
        );
    }
}
