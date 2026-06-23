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
}
