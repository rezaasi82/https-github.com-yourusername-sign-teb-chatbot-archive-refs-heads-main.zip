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
                'provider_id'      => $data['provider_id'],
                'service_id'       => $data['service_id'],
                'customer_name'    => $data['customer_name'],
                'customer_phone'   => $data['customer_phone'],
                'customer_email'   => $data['customer_email'] ?? null,
                'booking_datetime' => $data['booking_datetime'],
                'status'           => $data['status'] ?? BookingStatus::PENDING,
                'notes'            => $data['notes'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
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
     */
    public function has_conflict(int $provider_id, string $start, string $end, ?int $exclude_booking_id = null): bool
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->table()} b
                INNER JOIN {$this->services_table()} s ON b.service_id = s.id
                WHERE b.provider_id = %d
                AND b.status IN ('" . implode("','", BookingStatus::ACTIVE) . "')
                AND b.booking_datetime < %s
                AND DATE_ADD(b.booking_datetime, INTERVAL (s.duration_minutes + s.buffer_minutes) MINUTE) > %s";

        $params = [$provider_id, $end, $start];

        if ($exclude_booking_id !== null) {
            $sql      .= ' AND b.id != %d';
            $params[] = $exclude_booking_id;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $params));

        return $count > 0;
    }

    /**
     * Active bookings for a provider on a given date, with each booking's
     * occupied minutes (duration + buffer) resolved for slot-availability math.
     */
    public function get_active_bookings_for_provider_on_date(int $provider_id, string $date): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT b.booking_datetime, (s.duration_minutes + s.buffer_minutes) AS occupied_minutes
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
}
