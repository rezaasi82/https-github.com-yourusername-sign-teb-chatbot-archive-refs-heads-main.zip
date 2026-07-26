<?php
/**
 * Clinics / doctors / branches (multi-clinic support).
 *
 * @package Medora
 */

namespace Medora\Database;

if (! defined('ABSPATH')) {
    exit;
}

class BranchRepository
{
    /** @return array<int,object> */
    public function all(): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::branches_table();

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC") ?: [];
    }

    public function get(int $id): ?object
    {
        global $wpdb;
        $table = \Medora\Database\Schema::branches_table();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ?: null;
    }

    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        global $wpdb;
        $table = \Medora\Database\Schema::branches_table();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE id = %d", $id));
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(
            \Medora\Database\Schema::branches_table(),
            [
                'name'       => $data['name'],
                'doctor'     => $data['doctor'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'address'    => $data['address'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update(
            \Medora\Database\Schema::branches_table(),
            [
                'name'       => $data['name'],
                'doctor'     => $data['doctor'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'address'    => $data['address'] ?? null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Delete a branch and unassign its conversations (never delete leads).
     */
    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(\Medora\Database\Schema::branches_table(), ['id' => $id], ['%d']);
        $wpdb->update(
            \Medora\Database\Schema::conversations_table(),
            ['branch_id' => null],
            ['branch_id' => $id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Per-branch lead statistics in a single grouped query.
     *
     * @return array<int,array{total:int,leads:int}> branch_id => stats
     */
    public function lead_stats(): array
    {
        global $wpdb;
        $conv = \Medora\Database\Schema::conversations_table();

        $rows = $wpdb->get_results(
            "SELECT branch_id, COUNT(*) AS total, SUM(is_lead) AS leads
             FROM {$conv} GROUP BY branch_id"
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->branch_id] = ['total' => (int) $row->total, 'leads' => (int) $row->leads];
        }
        return $out;
    }
}
