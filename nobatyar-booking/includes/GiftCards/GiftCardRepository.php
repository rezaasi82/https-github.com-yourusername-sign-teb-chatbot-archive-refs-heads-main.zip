<?php

namespace Nobatyar\GiftCards;

if (! defined('ABSPATH')) {
    exit;
}

class GiftCardRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_gift_cards';
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
            'code'              => $data['code'],
            'initial_balance'   => $data['initial_balance'],
            'remaining_balance' => $data['initial_balance'],
            'expires_at'        => $data['expires_at'] ?? null,
            'is_active'         => $data['is_active'] ? 1 : 0,
            'recipient_name'    => $data['recipient_name'] ?? null,
            'recipient_email'   => $data['recipient_email'] ?? null,
            'note'              => $data['note'] ?? null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ], ['%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Balance is deliberately not editable here — only via the guarded
     * deduct_balance() below — so an admin edit can never undo or desync a
     * redemption already locked into a booking row.
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table(),
            [
                'code'            => $data['code'],
                'expires_at'      => $data['expires_at'] ?? null,
                'is_active'       => $data['is_active'] ? 1 : 0,
                'recipient_name'  => $data['recipient_name'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'note'            => $data['note'] ?? null,
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s'],
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
     * needs a lower-bound balance check, not just an equality match (mirrors
     * CouponRepository::increment_used_count() and
     * PackageRepository::decrement_purchase_sessions()'s same pattern). The
     * balance can never go negative even under concurrent redemption attempts.
     */
    public function deduct_balance(int $id, float $amount): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET remaining_balance = remaining_balance - %f, updated_at = %s
             WHERE id = %d AND remaining_balance >= %f",
            $amount,
            current_time('mysql'),
            $id,
            $amount
        ));
    }
}
