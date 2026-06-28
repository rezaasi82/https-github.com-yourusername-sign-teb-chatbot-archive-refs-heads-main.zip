<?php

namespace Nobatyar\Frontend\Shortcode;

use Nobatyar\Booking\RecurrenceFrequency;
use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class BookingShortcode
{
    public const SHORTCODE_TAG = 'nobatyar_booking';

    private ProviderRepository $provider_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(ProviderRepository $provider_repository, ServiceRepository $service_repository, LicenseManager $license_manager)
    {
        $this->provider_repository = $provider_repository;
        $this->service_repository  = $service_repository;
        $this->license_manager     = $license_manager;
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Loads the booking form's JS/CSS only on pages that actually contain the
     * shortcode — avoids the page-speed hit competitor plugins take from
     * loading their assets sitewide.
     */
    public function maybe_enqueue_assets(): void
    {
        if (! $this->current_page_has_shortcode()) {
            return;
        }

        wp_enqueue_style(
            'nobatyar-booking-form',
            NOBATYAR_PLUGIN_URL . 'assets/css/booking-form.css',
            [],
            NOBATYAR_VERSION
        );

        wp_enqueue_script(
            'nobatyar-jalali-datepicker',
            NOBATYAR_PLUGIN_URL . 'assets/js/jalali-datepicker.js',
            [],
            NOBATYAR_VERSION,
            true
        );

        wp_enqueue_script(
            'nobatyar-booking-form',
            NOBATYAR_PLUGIN_URL . 'assets/js/booking-form.js',
            [],
            NOBATYAR_VERSION,
            true
        );

        wp_localize_script('nobatyar-booking-form', 'nobatyarBooking', [
            'restUrl'         => esc_url_raw(rest_url('nobatyar/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'recurringEnabled' => $this->is_recurring_enabled(),
        ]);
    }

    private function is_recurring_enabled(): bool
    {
        return $this->license_manager->is_tier_available(LicenseTier::BUSINESS);
    }

    /**
     * Same Business-tier gate as recurring, kept as its own method (mirrors
     * PackageEngine/PackagesShortcode each defining their own check) since
     * the two features are independent and could diverge later.
     */
    private function is_packages_redeem_enabled(): bool
    {
        return $this->license_manager->is_tier_available(LicenseTier::BUSINESS);
    }

    private function current_page_has_shortcode(): bool
    {
        global $post;

        return $post instanceof \WP_Post && has_shortcode($post->post_content, self::SHORTCODE_TAG);
    }

    public function render(): string
    {
        $providers         = $this->provider_repository->all();
        $services          = $this->service_repository->all();
        $recurring_enabled = $this->is_recurring_enabled();
        $recurrence_frequencies = RecurrenceFrequency::all();
        $packages_redeem_enabled = $this->is_packages_redeem_enabled();

        ob_start();
        include NOBATYAR_PLUGIN_DIR . 'templates/booking-form.php';

        return ob_get_clean();
    }
}
