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
    /** Hard ceiling on how many occurrences a single recurring series may create. */
    public const MAX_RECURRENCE_OCCURRENCES = 52;

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
     * @return array{0: array, 1: array}|\WP_Error [$provider, $service] on success
     */
    private function resolve_provider_and_service(int $provider_id, int $service_id)
    {
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

        return [$provider, $service];
    }

    /**
     * @return \DateTimeImmutable|\WP_Error
     */
    private function parse_booking_datetime(string $value)
    {
        $timezone = wp_timezone();
        $start    = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);

        if (! $start) {
            return new \WP_Error('nobatyar_invalid_datetime', __('تاریخ و ساعت نوبت نامعتبر است.', 'nobatyar-booking'), ['status' => 422]);
        }

        if ($start < new \DateTimeImmutable('now', $timezone)) {
            return new \WP_Error('nobatyar_past_datetime', __('امکان رزرو در زمان گذشته وجود ندارد.', 'nobatyar-booking'), ['status' => 422]);
        }

        return $start;
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

        $resolved = $this->resolve_provider_and_service($provider_id, $service_id);

        if (is_wp_error($resolved)) {
            return $resolved;
        }

        [, $service] = $resolved;

        $start = $this->parse_booking_datetime($args['booking_datetime'] ?? '');

        if (is_wp_error($start)) {
            return $start;
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
     * Creates a Business-tier-only recurring series: the same provider/service/
     * customer booked repeatedly at a fixed frequency. All-or-nothing — every
     * occurrence's slot is checked for conflicts first, and nothing is created
     * if even one occurrence would conflict, so a series never lands half-booked
     * with silently-skipped dates the customer didn't ask for.
     *
     * @param array $args same shape as book(), plus recurrence_frequency
     *                     (RecurrenceFrequency::WEEKLY|BIWEEKLY|MONTHLY) and
     *                     recurrence_occurrences (int, 2..MAX_RECURRENCE_OCCURRENCES)
     * @return int[]|\WP_Error created booking ids, in series order, on success
     */
    public function book_recurring(array $args)
    {
        if (! $this->license_manager->is_tier_available(LicenseTier::BUSINESS)) {
            return new \WP_Error('nobatyar_feature_locked', __('نوبت‌دهی تکرارشونده ویژگی پلن Business است.', 'nobatyar-booking'), ['status' => 403]);
        }

        $provider_id = (int) ($args['provider_id'] ?? 0);
        $service_id  = (int) ($args['service_id'] ?? 0);

        $resolved = $this->resolve_provider_and_service($provider_id, $service_id);

        if (is_wp_error($resolved)) {
            return $resolved;
        }

        [, $service] = $resolved;

        $frequency = (string) ($args['recurrence_frequency'] ?? '');

        if (! RecurrenceFrequency::is_valid($frequency)) {
            return new \WP_Error('nobatyar_invalid_recurrence_frequency', __('الگوی تکرار نامعتبر است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $occurrences = (int) ($args['recurrence_occurrences'] ?? 0);

        if ($occurrences < 2 || $occurrences > self::MAX_RECURRENCE_OCCURRENCES) {
            return new \WP_Error(
                'nobatyar_invalid_recurrence_occurrences',
                sprintf(__('تعداد نوبت‌های سری تکرارشونده باید بین ۲ تا %d باشد.', 'nobatyar-booking'), self::MAX_RECURRENCE_OCCURRENCES),
                ['status' => 422]
            );
        }

        $first_start = $this->parse_booking_datetime($args['booking_datetime'] ?? '');

        if (is_wp_error($first_start)) {
            return $first_start;
        }

        $slot_minutes = (int) $service['duration_minutes'] + (int) $service['buffer_minutes'];
        $capacity_max = $this->effective_capacity_max($service);

        $occurrence_starts = [];
        $cursor             = $first_start;

        for ($i = 0; $i < $occurrences; $i++) {
            $occurrence_starts[] = $cursor;
            $cursor               = RecurrenceFrequency::advance($cursor, $frequency);
        }

        foreach ($occurrence_starts as $index => $occurrence_start) {
            $occurrence_end = $occurrence_start->modify("+{$slot_minutes} minutes");

            if ($this->booking_repository->has_conflict($provider_id, $service_id, $occurrence_start->format('Y-m-d H:i:s'), $occurrence_end->format('Y-m-d H:i:s'), $capacity_max)) {
                return new \WP_Error(
                    'nobatyar_booking_conflict',
                    sprintf(__('نوبت شماره %1$d از سری تکرارشونده (%2$s) با نوبت دیگری تداخل دارد.', 'nobatyar-booking'), $index + 1, $occurrence_start->format('Y-m-d H:i')),
                    ['status' => 409]
                );
            }
        }

        $booking_ids = [];
        $group_id    = null;

        foreach ($occurrence_starts as $index => $occurrence_start) {
            $booking_id = $this->booking_repository->create([
                'provider_id'         => $provider_id,
                'service_id'          => $service_id,
                'customer_name'       => $args['customer_name'],
                'customer_phone'      => $args['customer_phone'],
                'customer_email'      => $args['customer_email'] ?? null,
                'booking_datetime'    => $occurrence_start->format('Y-m-d H:i:s'),
                'notes'               => $args['notes'] ?? null,
                'recurrence_group_id' => $group_id,
                'recurrence_index'    => $index + 1,
                'recurrence_total'    => $occurrences,
            ]);

            if ($group_id === null) {
                $group_id = $booking_id;
                $this->booking_repository->set_recurrence_group_id($booking_id, $group_id);
            }

            $booking_ids[] = $booking_id;

            do_action('nobatyar_booking_created', $booking_id);
        }

        do_action('nobatyar_recurring_booking_created', $group_id, $booking_ids);

        return $booking_ids;
    }

    /**
     * Cancels every still-active occurrence of a recurring series from the
     * given booking onward (inclusive) — "cancel this and all future
     * occurrences" without touching ones already done/cancelled/in the past.
     *
     * @return int|\WP_Error number of occurrences cancelled
     */
    public function cancel_series_from(int $booking_id)
    {
        $booking = $this->booking_repository->find($booking_id);

        if (! $booking) {
            return new \WP_Error('nobatyar_booking_not_found', __('نوبت یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        if (empty($booking['recurrence_group_id'])) {
            return new \WP_Error('nobatyar_not_recurring', __('این نوبت بخشی از یک سری تکرارشونده نیست.', 'nobatyar-booking'), ['status' => 422]);
        }

        $cancelled = $this->booking_repository->cancel_future_occurrences(
            (int) $booking['recurrence_group_id'],
            (int) $booking['recurrence_index']
        );

        do_action('nobatyar_recurring_series_cancelled', (int) $booking['recurrence_group_id'], $cancelled);

        return $cancelled;
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
