<?php

namespace Nobatyar\Provider;

if (! defined('ABSPATH')) {
    exit;
}

class ProviderRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_providers';
    }

    private function pivot_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_provider_services';
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

        $sql .= ' ORDER BY sort_order ASC, name ASC';

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get_service_ids_for_provider(int $provider_id): array
    {
        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT service_id FROM {$this->pivot_table()} WHERE provider_id = %d",
                $provider_id
            )
        );

        return array_map('intval', $ids);
    }

    public function provider_offers_service(int $provider_id, int $service_id): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->pivot_table()} WHERE provider_id = %d AND service_id = %d",
                $provider_id,
                $service_id
            )
        );

        return $count > 0;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert($this->table(), [
            'user_id'        => $data['user_id'] ?: null,
            'name'           => $data['name'],
            'label_override' => $data['label_override'] ?: null,
            'license_field'  => $data['license_field'] ?: null,
            'is_active'      => $data['is_active'] ? 1 : 0,
            'sort_order'     => $data['sort_order'],
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'user_id'        => $data['user_id'] ?: null,
                'name'           => $data['name'],
                'label_override' => $data['label_override'] ?: null,
                'license_field'  => $data['license_field'] ?: null,
                'is_active'      => $data['is_active'] ? 1 : 0,
                'sort_order'     => $data['sort_order'],
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );

        return false !== $updated;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $wpdb->delete($this->pivot_table(), ['provider_id' => $id], ['%d']);

        return false !== $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }

    /**
     * Replaces the provider's full set of offered services in one go, since
     * the admin form submits the complete checked-service list each time
     * rather than incremental add/remove calls.
     */
    public function sync_services(int $provider_id, array $service_ids): void
    {
        global $wpdb;

        $wpdb->delete($this->pivot_table(), ['provider_id' => $provider_id], ['%d']);

        foreach (array_unique(array_map('intval', $service_ids)) as $service_id) {
            $wpdb->insert($this->pivot_table(), [
                'provider_id' => $provider_id,
                'service_id'  => $service_id,
            ], ['%d', '%d']);
        }
    }
}
