<?php

namespace Nobatyar\Packages;

if (! defined('ABSPATH')) {
    exit;
}

class PackageRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_packages';
    }

    private function purchases_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_package_purchases';
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

    public function all(bool $active_only = true): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table()}";

        if ($active_only) {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY name ASC';

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert($this->table(), [
            'service_id'     => (int) $data['service_id'],
            'name'           => $data['name'],
            'session_count'  => max(1, (int) $data['session_count']),
            'price'          => $data['price'],
            'validity_days'  => $data['validity_days'] !== null ? (int) $data['validity_days'] : null,
            'is_active'      => $data['is_active'] ? 1 : 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['%d', '%s', '%d', '%f', '%d', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'service_id'    => (int) $data['service_id'],
                'name'          => $data['name'],
                'session_count' => max(1, (int) $data['session_count']),
                'price'         => $data['price'],
                'validity_days' => $data['validity_days'] !== null ? (int) $data['validity_days'] : null,
                'is_active'     => $data['is_active'] ? 1 : 0,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%d', '%f', '%d', '%d', '%s'],
            ['%d']
        );

        return false !== $updated;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }

    public function find_purchase(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->purchases_table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function create_purchase(array $data): int
    {
        global $wpdb;

        $now           = current_time('mysql');
        $sessions_total = max(1, (int) $data['sessions_total']);

        $wpdb->insert($this->purchases_table(), [
            'package_id'         => (int) $data['package_id'],
            'customer_name'      => $data['customer_name'],
            'customer_phone'     => $data['customer_phone'],
            'customer_email'     => $data['customer_email'] ?? null,
            'sessions_total'     => $sessions_total,
            'sessions_remaining' => $sessions_total,
            'purchased_at'       => $now,
            'expires_at'         => $data['expires_at'] ?? null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ], ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Guarded raw UPDATE rather than wpdb::update() since the WHERE clause
     * needs sessions_remaining > 0, not just an equality match (mirrors the
     * other follow-up-update methods on BookingRepository, which are also
     * void-returning).
     */
    public function decrement_purchase_sessions(int $id): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->purchases_table()} SET sessions_remaining = sessions_remaining - 1, updated_at = %s WHERE id = %d AND sessions_remaining > 0",
            current_time('mysql'),
            $id
        ));
    }

    public function find_active_purchases_by_phone(string $phone): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pp.*, p.name AS package_name, p.service_id AS service_id
                 FROM {$this->purchases_table()} pp
                 INNER JOIN {$this->table()} p ON p.id = pp.package_id
                 WHERE pp.customer_phone = %s
                   AND pp.sessions_remaining > 0
                   AND (pp.expires_at IS NULL OR pp.expires_at >= %s)
                 ORDER BY pp.purchased_at ASC",
                $phone,
                current_time('mysql')
            ),
            ARRAY_A
        );
    }

    public function all_purchases(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT pp.*, p.name AS package_name
             FROM {$this->purchases_table()} pp
             INNER JOIN {$this->table()} p ON p.id = pp.package_id
             ORDER BY pp.purchased_at DESC",
            ARRAY_A
        );
    }
}
