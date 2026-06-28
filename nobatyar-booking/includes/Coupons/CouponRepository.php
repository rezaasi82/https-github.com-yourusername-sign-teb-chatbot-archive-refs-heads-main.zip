<?php

namespace Nobatyar\Coupons;

if (! defined('ABSPATH')) {
    exit;
}

class CouponRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_coupons';
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

    public function find_by_code(string $code): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE code = %s", $code),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function all(): array
    {
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY created_at DESC", ARRAY_A);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert($this->table(), [
            'code'           => $data['code'],
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'service_id'     => $data['service_id'] !== null ? (int) $data['service_id'] : null,
            'max_uses'       => $data['max_uses'] !== null ? (int) $data['max_uses'] : null,
            'used_count'     => 0,
            'valid_from'     => $data['valid_from'] ?? null,
            'valid_until'    => $data['valid_until'] ?? null,
            'min_amount'     => $data['min_amount'] !== null ? $data['min_amount'] : null,
            'is_active'      => $data['is_active'] ? 1 : 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s', '%f', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'code'           => $data['code'],
                'discount_type'  => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'service_id'     => $data['service_id'] !== null ? (int) $data['service_id'] : null,
                'max_uses'       => $data['max_uses'] !== null ? (int) $data['max_uses'] : null,
                'valid_from'     => $data['valid_from'] ?? null,
                'valid_until'    => $data['valid_until'] ?? null,
                'min_amount'     => $data['min_amount'] !== null ? $data['min_amount'] : null,
                'is_active'      => $data['is_active'] ? 1 : 0,
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%f', '%d', '%d', '%s', '%s', '%f', '%d', '%s'],
            ['%d']
        );

        return false !== $updated;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }

    /**
     * Guarded raw UPDATE rather than wpdb::update() since the WHERE clause
     * needs an upper-bound usage check, not just an equality match (mirrors
     * PackageRepository::decrement_purchase_sessions()'s same pattern).
     * Unlimited coupons (max_uses NULL) always pass the bound check.
     */
    public function increment_used_count(int $id): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET used_count = used_count + 1, updated_at = %s
             WHERE id = %d AND (max_uses IS NULL OR used_count < max_uses)",
            current_time('mysql'),
            $id
        ));
    }
}
