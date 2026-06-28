<?php

namespace Nobatyar\Frontend\Shortcode;

use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Packages\PackageRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class PackagesShortcode
{
    public const SHORTCODE_TAG = 'nobatyar_packages';

    private PackageRepository $package_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(PackageRepository $package_repository, ServiceRepository $service_repository, LicenseManager $license_manager)
    {
        $this->package_repository = $package_repository;
        $this->service_repository = $service_repository;
        $this->license_manager    = $license_manager;
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Mirrors BookingShortcode::maybe_enqueue_assets() — JS/CSS only loads
     * on pages that actually contain this shortcode.
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
            'nobatyar-packages-form',
            NOBATYAR_PLUGIN_URL . 'assets/js/packages-form.js',
            [],
            NOBATYAR_VERSION,
            true
        );

        wp_localize_script('nobatyar-packages-form', 'nobatyarPackages', [
            'restUrl' => esc_url_raw(rest_url('nobatyar/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    private function is_packages_enabled(): bool
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
        if (! $this->is_packages_enabled()) {
            return '';
        }

        $packages = $this->package_repository->all();
        $services = [];

        foreach ($this->service_repository->all() as $service) {
            $services[(int) $service['id']] = $service;
        }

        ob_start();
        include NOBATYAR_PLUGIN_DIR . 'templates/packages-list.php';

        return ob_get_clean();
    }
}
