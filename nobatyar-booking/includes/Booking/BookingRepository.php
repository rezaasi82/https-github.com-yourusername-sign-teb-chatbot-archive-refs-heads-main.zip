<?php

namespace Nobatyar\Booking;

if (! defined('ABSPATH')) {
    exit;
}

class BookingRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_bookings';
    }

    private function services_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_services';
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function all(array $filters = []): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        if (! empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }

        if (! empty($filters['provider_id'])) {
            $where[]  = 'provider_id = %d';
            $params[] = (int) $filters['provider_id'];
        }

        if (! empty($filters['date_from'])) {
            $where[]  = 'booking_datetime >= %s';
            $params[] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[]  = 'booking_datetime <= %s';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT * FROM {$this->table()} WHERE " . implode(' AND ', $where) . ' ORDER BY booking_datetime ASC';

        if ($params) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert(
            $this->table(),
            [
                'provider_id'         => $data['provider_id'],
                'service_id'          => $data['service_id'],
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'customer_email'      => $data['customer_email'] ?? null,
                'booking_datetime'    => $data['booking_datetime'],
                'status'              => $data['status'] ?? BookingStatus::PENDING,
                'notes'               => $data['notes'] ?? null,
                'recurrence_group_id' => $data['recurrence_group_id'] ?? null,
                'recurrence_index'    => $data['recurrence_index'] ?? null,
                'recurrence_total'    => $data['recurrence_total'] ?? null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * The first occurrence of a recurring series doesn't know its own id
     * until after insert, so its recurrence_group_id (= its own id) is set
     * via a follow-up update rather than threaded through create().
     */
    public function set_recurrence_group_id(int $id, int $group_id): void
    {
        global $wpdb;

        $wpdb->update($this->table(), ['recurrence_group_id' => $group_id], ['id' => $id], ['%d'], ['%d']);
    }

    /**
     * Threaded through as a follow-up update (mirrors set_recurrence_group_id)
     * since PackageEngine::redeem() only learns the purchase id after
     * BookingEngine::book() has already created the row.
     */
    public function set_package_purchase_id(int $booking_id, int $purchase_id): void
    {
        global $wpdb;

        $wpdb->update($this->table(), ['package_purchase_id' => $purchase_id], ['id' => $booking_id], ['%d'], ['%d']);
    }

    public function find_by_recurrence_group(int $group_id): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE recurrence_group_id = %d ORDER BY recurrence_index ASC", $group_id),
            ARRAY_A
        );
    }

    /**
     * Cancels every still-active occurrence of a recurring series from
     * $from_index onward (inclusive) — used by the admin "cancel this and
     * future occurrences" action. Past/already-resolved occurrences before
     * $from_index are left untouched.
     */
    public function cancel_future_occurrences(int $group_id, int $from_index): int
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->table()}
            WHERE recurrence_group_id = %d
            AND recurrence_index >= %d
            AND status IN ('" . implode("','", BookingStatus::ACTIVE) . "')",
            $group_id,
            $from_index
        ));

        foreach ($ids as $id) {
            $this->update_status((int) $id, BookingStatus::CANCELLED);
        }

        return count($ids);
    }

    public function update_status(int $id, string $status): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return false !== $updated;
    }

    /**
     * An active (pending/confirmed) booking for the provider conflicts with the
     * requested window when its [start, end) interval overlaps [start, end).
     * Booking end times aren't stored, so they're derived from the service's
     * duration + buffer at query time.
     *
     * Group Booking exception: bookings at the exact identical
     * (service_id, booking_datetime) as the requested slot don't block on
     * their own — they only count against $capacity_max, since they
     * represent other attendees of the same group slot, not a different
     * occupant of the provider's time. Any other overlapping booking (a
     * different service, or a partially-overlapping time) still fully
     * blocks regardless of capacity.
     */
    public function has_conflict(int $provider_id, int $service_id, string $start, string $end, int $capacity_max = 1, ?int $exclude_booking_id = null): bool
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table()} b
                INNER JOIN {$this->services_table()} s ON b.service_id = s.id
                WHERE b.provider_id = %d
                AND b.status IN ('" . implode("','", BookingStatus::ACTIVE) . "')
                AND b.booking_datetime < %s
                AND DATE_ADD(b.booking_datetime, INTERVAL (s.duration_minutes + s.buffer_minutes) MINUTE) > %s
                AND NOT (b.service_id = %d AND b.booking_datetime = %s)";

        $params = [$provider_id, $end, $start, $service_id, $start];

        if ($exclude_booking_id !== null) {
            $sql      .= ' AND b.id != %d';
            $params[] = $exclude_booking_id;
        }

        $blocking_count = (int) $wpdb->get_var($wpdb->prepare($sql, $params));

        if ($blocking_count > 0) {
            return true;
        }

        return $this->count_active_at_exact_slot($provider_id, $service_id, $start, $exclude_booking_id) >= max(1, $capacity_max);
    }

    /**
     * Active bookings already occupying the exact same (provider, service,
     * start time) — i.e. other attendees of the same group slot.
     */
    private function count_active_at_exact_slot(int $provider_id, int $service_id, string $start, ?int $exclude_booking_id = null): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table()}
                WHERE provider_id = %d
                AND service_id = %d
                AND booking_datetime = %s
                AND status IN ('" . implode("','", BookingStatus::ACTIVE) . "')";

        $params = [$provider_id, $service_id, $start];

        if ($exclude_booking_id !== null) {
            $sql      .= ' AND id != %d';
            $params[] = $exclude_booking_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * Active bookings for a provider on a given date, with each booking's
     * occupied minutes (duration + buffer) resolved for slot-availability math.
     */
    public function get_active_bookings_for_provider_on_date(int $provider_id, string $date): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT b.service_id, b.booking_datetime, (s.duration_minutes + s.buffer_minutes) AS occupied_minutes
            FROM {$this->table()} b
            INNER JOIN {$this->services_table()} s ON b.service_id = s.id
            WHERE b.provider_id = %d
            AND b.status IN ('" . implode("','", BookingStatus::ACTIVE) . "')
            AND DATE(b.booking_datetime) = %s",
            $provider_id,
            $date
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Active bookings starting within the next $hours_before hours that
     * haven't had a reminder sent yet - used by the hourly reminder cron.
     */
    public function get_due_reminders(int $hours_before): array
    {
        global $wpdb;

        $now    = current_time('mysql');
        $cutoff = (new \DateTimeImmutable($now))->modify("+{$hours_before} hours")->format('Y-m-d H:i:s');

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table()}
            WHERE status IN ('" . implode("','", BookingStatus::ACTIVE) . "')
            AND reminder_sent_at IS NULL
            AND booking_datetime BETWEEN %s AND %s",
            $now,
            $cutoff
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function mark_reminder_sent(int $id): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table(),
            ['reminder_sent_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }
}
