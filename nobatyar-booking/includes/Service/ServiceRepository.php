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
}
