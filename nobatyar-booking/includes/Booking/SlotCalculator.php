<?php

namespace Nobatyar\Booking;

use Nobatyar\Provider\AvailabilityManager;
use Nobatyar\Service\Service;

if (! defined('ABSPATH')) {
    exit;
}

class SlotCalculator
{
    /**
     * Step between candidate slot start times. Kept independent of service
     * duration so a 60-minute service can still start at 11:15, 11:30, etc.
     * — not just on the hour (the known Bookly limitation we're avoiding).
     */
    private const DEFAULT_GRANULARITY_MINUTES = 15;

    private AvailabilityManager $availability_manager;
    private BookingRepository $booking_repository;

    public function __construct(AvailabilityManager $availability_manager, BookingRepository $booking_repository)
    {
        $this->availability_manager = $availability_manager;
        $this->booking_repository   = $booking_repository;
    }

    public function get_available_slots(int $provider_id, Service $service, string $date): array
    {
        $periods = $this->availability_manager->get_working_periods($provider_id, $date);

        if (empty($periods)) {
            return apply_filters('nobatyar_available_slots', [], $provider_id, $date);
        }

        $timezone     = wp_timezone();
        $granularity  = max(1, (int) apply_filters('nobatyar_slot_granularity_minutes', self::DEFAULT_GRANULARITY_MINUTES, $provider_id, $service->id));
        $slot_minutes = $service->duration_minutes + $service->buffer_minutes;
        $occupied     = $this->get_occupied_intervals($provider_id, $date, $timezone);
        $now          = new \DateTimeImmutable('now', $timezone);

        $slots = [];

        foreach ($periods as $period) {
            $period_start = new \DateTimeImmutable("{$date} {$period['start']}", $timezone);
            $period_end   = new \DateTimeImmutable("{$date} {$period['end']}", $timezone);
            $cursor       = $period_start;

            while (true) {
                $slot_end = $cursor->modify("+{$slot_minutes} minutes");

                if ($slot_end > $period_end) {
                    break;
                }

                if ($cursor >= $now) {
                    $remaining = $this->remaining_capacity($cursor, $slot_end, $occupied, $service);

                    if ($remaining > 0) {
                        $slot = [
                            'start' => $cursor->format('Y-m-d H:i:s'),
                            'end'   => $slot_end->format('Y-m-d H:i:s'),
                        ];

                        if ($service->is_group_bookable()) {
                            $slot['capacity_remaining'] = $remaining;
                            $slot['capacity_max']       = $service->capacity_max;
                        }

                        $slots[] = $slot;
                    }
                }

                $cursor = $cursor->modify("+{$granularity} minutes");
            }
        }

        return apply_filters('nobatyar_available_slots', $slots, $provider_id, $date);
    }

    private function get_occupied_intervals(int $provider_id, string $date, \DateTimeZone $timezone): array
    {
        $rows      = $this->booking_repository->get_active_bookings_for_provider_on_date($provider_id, $date);
        $intervals = [];

        foreach ($rows as $row) {
            $start = new \DateTimeImmutable($row['booking_datetime'], $timezone);
            $end   = $start->modify('+' . (int) $row['occupied_minutes'] . ' minutes');

            $intervals[] = [
                'start'      => $start,
                'end'        => $end,
                'service_id' => (int) $row['service_id'],
            ];
        }

        return $intervals;
    }

    /**
     * How many more attendees a candidate [start, end) slot can take for
     * $service. Any overlapping interval that ISN'T the exact same
     * (service_id, start) as the candidate fully blocks the slot (returns
     * 0) — only bookings at the identical group slot count against
     * capacity instead of blocking outright.
     */
    private function remaining_capacity(\DateTimeImmutable $start, \DateTimeImmutable $end, array $intervals, Service $service): int
    {
        $identical_count = 0;

        foreach ($intervals as $interval) {
            if ($start >= $interval['end'] || $end <= $interval['start']) {
                continue;
            }

            $is_identical_slot = $interval['service_id'] === $service->id && $interval['start']->format('Y-m-d H:i:s') === $start->format('Y-m-d H:i:s');

            if (! $is_identical_slot) {
                return 0;
            }

            $identical_count++;
        }

        return max(0, $service->capacity_max - $identical_count);
    }
}
