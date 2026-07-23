<?php

namespace QRCODR\Codes;

if (!defined('ABSPATH')) {
    exit;
}

class CodeRepository
{
    private $table;
    private $scans_table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'qrcodr_codes';
        $this->scans_table = $wpdb->prefix . 'qrcodr_scans';
    }

    public function find_by_short_code($short_code)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is not user input
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE short_code = %s",
            $short_code
        ));
    }

    public function find_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    public function all_with_scan_counts()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, table names only
        return $wpdb->get_results(
            "SELECT c.*, COUNT(s.id) AS scan_count
             FROM {$this->table} c
             LEFT JOIN {$this->scans_table} s ON s.code_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );
    }

    public function insert($title, $destination_url, $style)
    {
        global $wpdb;
        $now = current_time('mysql');

        $wpdb->insert(
            $this->table,
            array(
                'short_code' => $this->generate_unique_short_code(),
                'title' => $title,
                'destination_url' => $destination_url,
                'style_json' => wp_json_encode($style),
                'status' => 'active',
                'created_by' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        return $wpdb->insert_id;
    }

    public function update($id, $title, $destination_url, $style, $status)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            array(
                'title' => $title,
                'destination_url' => $destination_url,
                'style_json' => wp_json_encode($style),
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
    }

    public function delete($id)
    {
        global $wpdb;
        $wpdb->delete($this->scans_table, array('code_id' => $id), array('%d'));
        return $wpdb->delete($this->table, array('id' => $id), array('%d'));
    }

    private function generate_unique_short_code()
    {
        do {
            $short_code = substr(wp_generate_password(10, false, false), 0, 7);
        } while ($this->find_by_short_code($short_code));

        return $short_code;
    }
}
