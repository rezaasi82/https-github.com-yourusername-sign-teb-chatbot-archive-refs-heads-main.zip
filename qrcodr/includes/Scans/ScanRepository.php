<?php

namespace QRCODR\Scans;

if (!defined('ABSPATH')) {
    exit;
}

class ScanRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'qrcodr_scans';
    }

    public function total_scans($code_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE code_id = %d",
            $code_id
        ));
    }

    public function unique_scans($code_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_hash) FROM {$this->table} WHERE code_id = %d AND ip_hash IS NOT NULL",
            $code_id
        ));
    }

    public function breakdown_by($code_id, $column)
    {
        global $wpdb;
        $allowed = array('device_type', 'browser', 'os');
        if (!in_array($column, $allowed, true)) {
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $column is validated against an allow-list above
        return $wpdb->get_results($wpdb->prepare(
            "SELECT {$column} AS label, COUNT(*) AS total
             FROM {$this->table}
             WHERE code_id = %d
             GROUP BY {$column}
             ORDER BY total DESC",
            $code_id
        ));
    }

    public function daily_trend($code_id, $days = 30)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(scanned_at) AS day, COUNT(*) AS total
             FROM {$this->table}
             WHERE code_id = %d AND scanned_at >= DATE_SUB(%s, INTERVAL %d DAY)
             GROUP BY DATE(scanned_at)
             ORDER BY day ASC",
            $code_id,
            current_time('mysql'),
            $days
        ));
    }

    public function recent($code_id, $limit = 20)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT scanned_at, device_type, browser, os, referrer
             FROM {$this->table}
             WHERE code_id = %d
             ORDER BY scanned_at DESC
             LIMIT %d",
            $code_id,
            $limit
        ));
    }
}
