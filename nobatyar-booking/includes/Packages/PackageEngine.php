<?php

namespace Nobatyar\Packages;

use Nobatyar\Booking\BookingEngine;
use Nobatyar\Booking\BookingRepository;
use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class PackageEngine
{
    private PackageRepository $package_repository;
    private BookingEngine $booking_engine;
    private BookingRepository $booking_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(
        PackageRepository $package_repository,
        BookingEngine $booking_engine,
        BookingRepository $booking_repository,
        ServiceRepository $service_repository,
        LicenseManager $license_manager
    ) {
        $this->package_repository = $package_repository;
        $this->booking_engine      = $booking_engine;
        $this->booking_repository  = $booking_repository;
        $this->service_repository  = $service_repository;
        $this->license_manager     = $license_manager;
    }

    /**
     * Packages (definitions, purchases, and redemption) are a Business-tier
     * feature end to end — unlike Service CRUD, there is no free-tier
     * equivalent to fall back to, so every write path is rejected outright
     * rather than silently clamped.
     */
    private function require_business_tier(): ?\WP_Error
    {
        if (! $this->license_manager->is_tier_available(LicenseTier::BUSINESS)) {
            return new \WP_Error('nobatyar_feature_locked', __('پکیج‌های نشست ویژگی پلن Business است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return null;
    }

    /**
     * @param array $args service_id, name, session_count, price, validity_days?, is_active?
     * @return int|\WP_Error package id on success
     */
    public function create_package(array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $service = $this->service_repository->find((int) ($args['service_id'] ?? 0));

        if (! $service) {
            return new \WP_Error('nobatyar_invalid_service', __('خدمت انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        return $this->package_repository->create([
            'service_id'    => (int) $args['service_id'],
            'name'          => $args['name'],
            'session_count' => (int) ($args['session_count'] ?? 1),
            'price'         => (float) ($args['price'] ?? 0),
            'validity_days' => isset($args['validity_days']) && $args['validity_days'] !== '' ? (int) $args['validity_days'] : null,
            'is_active'     => $args['is_active'] ?? true,
        ]);
    }

    /**
     * @return true|\WP_Error
     */
    public function update_package(int $id, array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $package = $this->package_repository->find($id);

        if (! $package) {
            return new \WP_Error('nobatyar_package_not_found', __('پکیج یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $service = $this->service_repository->find((int) ($args['service_id'] ?? 0));

        if (! $service) {
            return new \WP_Error('nobatyar_invalid_service', __('خدمت انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $this->package_repository->update($id, [
            'service_id'    => (int) $args['service_id'],
            'name'          => $args['name'],
            'session_count' => (int) ($args['session_count'] ?? 1),
            'price'         => (float) ($args['price'] ?? 0),
            'validity_days' => isset($args['validity_days']) && $args['validity_days'] !== '' ? (int) $args['validity_days'] : null,
            'is_active'     => $args['is_active'] ?? true,
        ]);

        return true;
    }

    /**
     * @return true|\WP_Error
     */
    public function delete_package(int $id)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $this->package_repository->delete($id);

        return true;
    }

    /**
     * Records a customer's purchase of a package definition — a snapshot of
     * sessions_total/price at this moment, so later admin edits to the
     * definition never retroactively change a purchase already sold.
     *
     * @param array $args package_id, customer_name, customer_phone, customer_email?
     * @return int|\WP_Error purchase id on success
     */
    public function purchase(array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $package = $this->package_repository->find((int) ($args['package_id'] ?? 0));

        if (! $package || ! (int) $package['is_active']) {
            return new \WP_Error('nobatyar_invalid_package', __('پکیج انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $expires_at = null;

        if (! empty($package['validity_days'])) {
            $expires_at = (new \DateTimeImmutable('now', wp_timezone()))
                ->modify('+' . (int) $package['validity_days'] . ' days')
                ->format('Y-m-d H:i:s');
        }

        $purchase_id = $this->package_repository->create_purchase([
            'package_id'     => (int) $package['id'],
            'customer_name'  => $args['customer_name'],
            'customer_phone' => $args['customer_phone'],
            'customer_email' => $args['customer_email'] ?? null,
            'sessions_total' => (int) $package['session_count'],
            'expires_at'     => $expires_at,
        ]);

        do_action('nobatyar_package_purchased', $purchase_id, (int) $package['id']);

        return $purchase_id;
    }

    /**
     * Redeems one session credit from an existing purchase against a new
     * booking. The service is always derived from the package's own
     * definition — never from caller input — so a credit can never be spent
     * against a different service than the one it was sold for.
     *
     * @param array $args package_purchase_id, provider_id, booking_datetime,
     *                     customer_name, customer_phone, customer_email?, notes?
     * @return int|\WP_Error booking id on success
     */
    public function redeem(array $args)
    {
        $locked = $this->require_business_tier();

        if ($locked) {
            return $locked;
        }

        $purchase = $this->package_repository->find_purchase((int) ($args['package_purchase_id'] ?? 0));

        if (! $purchase) {
            return new \WP_Error('nobatyar_purchase_not_found', __('خرید پکیج یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        if ((int) $purchase['sessions_remaining'] <= 0) {
            return new \WP_Error('nobatyar_no_sessions_remaining', __('اعتبار نشست‌های این پکیج به پایان رسیده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if (! empty($purchase['expires_at']) && $purchase['expires_at'] < current_time('mysql')) {
            return new \WP_Error('nobatyar_package_expired', __('اعتبار زمانی این پکیج به پایان رسیده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $package = $this->package_repository->find((int) $purchase['package_id']);

        if (! $package) {
            return new \WP_Error('nobatyar_invalid_package', __('پکیج انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $booking_id = $this->booking_engine->book([
            'provider_id'      => (int) ($args['provider_id'] ?? 0),
            'service_id'       => (int) $package['service_id'],
            'booking_datetime' => $args['booking_datetime'] ?? '',
            'customer_name'    => $args['customer_name'],
            'customer_phone'   => $args['customer_phone'],
            'customer_email'   => $args['customer_email'] ?? null,
            'notes'            => $args['notes'] ?? null,
        ]);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        $this->booking_repository->set_package_purchase_id($booking_id, (int) $purchase['id']);
        $this->package_repository->decrement_purchase_sessions((int) $purchase['id']);

        do_action('nobatyar_package_redeemed', $booking_id, (int) $purchase['id']);

        return $booking_id;
    }

    /**
     * Pure read lookup — deliberately NOT gated, since a customer simply
     * checking their own remaining credits isn't a write/opt-in action.
     */
    public function find_active_purchases_by_phone(string $phone): array
    {
        return $this->package_repository->find_active_purchases_by_phone($phone);
    }
}
