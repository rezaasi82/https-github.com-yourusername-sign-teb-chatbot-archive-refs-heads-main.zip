<?php

namespace Nobatyar\Payment;

if (! defined('ABSPATH')) {
    exit;
}

class TransactionRepository
{
    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'nby_transactions';
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

    public function find_by_authority(string $authority): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE authority = %s", $authority),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->table(),
            [
                'booking_id' => $data['booking_id'],
                'gateway'    => $data['gateway'],
                'amount'     => $data['amount'],
                'status'     => $data['status'],
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%f', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function set_authority(int $id, string $authority): void
    {
        global $wpdb;

        $wpdb->update($this->table(), ['authority' => $authority], ['id' => $id], ['%s'], ['%d']);
    }

    public function update_status(int $id, string $status, ?string $raw_response = null): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table(),
            ['status' => $status, 'raw_response' => $raw_response],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function mark_verified(int $id, string $status, ?string $raw_response = null): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table(),
            ['status' => $status, 'raw_response' => $raw_response, 'verified_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function sum_successful_amount_between(string $date_from, string $date_to): float
    {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->table()} WHERE status = %s AND created_at BETWEEN %s AND %s",
            TransactionStatus::SUCCESS,
            $date_from,
            $date_to
        ));

        return (float) ($value ?? 0);
    }
}
