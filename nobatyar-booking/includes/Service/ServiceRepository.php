<?php

namespace Nobatyar\Service;

if (! defined('ABSPATH')) {
    exit;
}

class ServiceRepository
{
    private function table(): string
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
            'name'             => $data['name'],
            'duration_minutes' => $data['duration_minutes'],
            'buffer_minutes'   => $data['buffer_minutes'],
            'price'            => $data['price'],
            'deposit_amount'   => $data['deposit_amount'],
            'capacity_min'     => max(1, (int) ($data['capacity_min'] ?? 1)),
            'capacity_max'     => max(1, (int) ($data['capacity_max'] ?? 1)),
            'is_active'        => $data['is_active'] ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ], ['%s', '%d', '%d', '%f', '%f', '%d', '%d', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'name'             => $data['name'],
                'duration_minutes' => $data['duration_minutes'],
                'buffer_minutes'   => $data['buffer_minutes'],
                'price'            => $data['price'],
                'deposit_amount'   => $data['deposit_amount'],
                'capacity_min'     => max(1, (int) ($data['capacity_min'] ?? 1)),
                'capacity_max'     => max(1, (int) ($data['capacity_max'] ?? 1)),
                'is_active'        => $data['is_active'] ? 1 : 0,
                'updated_at'       => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%f', '%f', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        return false !== $updated;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }
}
