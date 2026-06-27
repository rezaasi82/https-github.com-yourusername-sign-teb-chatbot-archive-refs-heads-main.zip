<?php

namespace Nobatyar\Booking;

use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;
use Nobatyar\Provider\ProviderRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class BookingEngine
{
    private BookingRepository $booking_repository;
    private ProviderRepository $provider_repository;
    private ServiceRepository $service_repository;
    private LicenseManager $license_manager;

    public function __construct(
        BookingRepository $booking_repository,
        ProviderRepository $provider_repository,
        ServiceRepository $service_repository,
        LicenseManager $license_manager
    ) {
        $this->booking_repository  = $booking_repository;
        $this->provider_repository = $provider_repository;
        $this->service_repository  = $service_repository;
        $this->license_manager     = $license_manager;
    }

    /**
     * capacity_max > 1 (Group Booking) is Business-tier — a site that set it
     * while on Business and then downgraded/lapsed must not keep accepting
     * extra attendees at write time, even though the stored column still
     * says otherwise.
     */
    private function effective_capacity_max(array $service): int
    {
        $capacity_max = max(1, (int) ($service['capacity_max'] ?? 1));

        if ($capacity_max > 1 && ! $this->license_manager->is_tier_available(LicenseTier::BUSINESS)) {
            return 1;
        }

        return $capacity_max;
    }

    /**
     * @param array $args provider_id, service_id, booking_datetime ('Y-m-d H:i:s'),
     *                     customer_name, customer_phone, customer_email?, notes?
     * @return int|\WP_Error booking id on success
     */
    public function book(array $args)
    {
        $provider_id = (int) ($args['provider_id'] ?? 0);
        $service_id  = (int) ($args['service_id'] ?? 0);

        $provider = $this->provider_repository->find($provider_id);

        if (! $provider || ! (int) $provider['is_active']) {
            return new \WP_Error('nobatyar_invalid_provider', __('سرویس‌دهنده انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        $service = $this->service_repository->find($service_id);

        if (! $service || ! (int) $service['is_active']) {
            return new \WP_Error('nobatyar_invalid_service', __('خدمت انتخاب‌شده معتبر نیست.', 'nobatyar-booking'), ['status' => 404]);
        }

        if (! $this->provider_repository->provider_offers_service($provider_id, $service_id)) {
            return new \WP_Error('nobatyar_service_not_offered', __('این سرویس‌دهنده این خدمت را ارائه نمی‌دهد.', 'nobatyar-booking'), ['status' => 422]);
        }

        $timezone = wp_timezone();
        $start    = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $args['booking_datetime'] ?? '', $timezone);

        if (! $start) {
            return new \WP_Error('nobatyar_invalid_datetime', __('تاریخ و ساعت نوبت نامعتبر است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if ($start < new \DateTimeImmutable('now', $timezone)) {
            return new \WP_Error('nobatyar_past_datetime', __('امکان رزرو در زمان گذشته وجود ندارد.', 'nobatyar-booking'), ['status' => 422]);
        }

        $slot_minutes = (int) $service['duration_minutes'] + (int) $service['buffer_minutes'];
        $end          = $start->modify("+{$slot_minutes} minutes");

        $capacity_max = $this->effective_capacity_max($service);

        if ($this->booking_repository->has_conflict($provider_id, $service_id, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $capacity_max)) {
            return new \WP_Error('nobatyar_booking_conflict', __('این بازه زمانی برای سرویس‌دهنده انتخاب‌شده قبلاً رزرو شده است.', 'nobatyar-booking'), ['status' => 409]);
        }

        $booking_id = $this->booking_repository->create([
            'provider_id'      => $provider_id,
            'service_id'       => $service_id,
            'customer_name'    => $args['customer_name'],
            'customer_phone'   => $args['customer_phone'],
            'customer_email'   => $args['customer_email'] ?? null,
            'booking_datetime' => $start->format('Y-m-d H:i:s'),
            'notes'            => $args['notes'] ?? null,
        ]);

        do_action('nobatyar_booking_created', $booking_id);

        return $booking_id;
    }

    /**
     * @return true|\WP_Error
     */
    public function change_status(int $booking_id, string $new_status)
    {
        if (! BookingStatus::is_valid($new_status)) {
            return new \WP_Error('nobatyar_invalid_status', __('وضعیت نامعتبر است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $booking = $this->booking_repository->find($booking_id);

        if (! $booking) {
            return new \WP_Error('nobatyar_booking_not_found', __('نوبت یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $old_status = $booking['status'];

        if ($old_status === $new_status) {
            return true;
        }

        $this->booking_repository->update_status($booking_id, $new_status);

        do_action('nobatyar_booking_status_changed', $booking_id, $old_status, $new_status);

        return true;
    }
}
