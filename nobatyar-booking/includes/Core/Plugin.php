<?php

namespace Nobatyar\Core;

use Nobatyar\Booking\BookingEngine;
use Nobatyar\Booking\BookingRepository;
use Nobatyar\Booking\SlotCalculator;
use Nobatyar\Frontend\Shortcode\BookingShortcode;
use Nobatyar\Notifications\EmailNotifier;
use Nobatyar\Notifications\NotificationDispatcher;
use Nobatyar\Notifications\SmsLogRepository;
use Nobatyar\Payment\PaymentEngine;
use Nobatyar\Payment\TransactionRepository;
use Nobatyar\Provider\AvailabilityManager;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Rest\Controllers\AvailabilityController;
use Nobatyar\Rest\Controllers\BookingController;
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
        $this->notification_dispatcher()->register();
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

        $booking_engine = new BookingEngine($booking_repository, $provider_repository, $service_repository);
        $slot_calculator = new SlotCalculator(new AvailabilityManager(), $booking_repository);
        $payment_engine = new PaymentEngine(new TransactionRepository(), $booking_repository, $service_repository);

        (new BookingController($booking_engine, $booking_repository))->register_routes();
        (new AvailabilityController($slot_calculator, $service_repository))->register_routes();
        (new PaymentController($payment_engine))->register_routes();
    }

    private function booking_shortcode(): BookingShortcode
    {
        return new BookingShortcode(new ProviderRepository(), new ServiceRepository());
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
}
