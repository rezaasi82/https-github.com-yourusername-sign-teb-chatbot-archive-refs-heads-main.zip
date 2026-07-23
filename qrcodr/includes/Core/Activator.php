<?php

namespace QRCODR\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate()
    {
        self::create_tables();
        add_option('qrcodr_version', QRCODR_VERSION);
        add_option('qrcodr_delete_data_on_uninstall', '0');

        \QRCODR\Redirect\RedirectController::register_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $codes_table = $wpdb->prefix . 'qrcodr_codes';
        $scans_table = $wpdb->prefix . 'qrcodr_scans';

        $sql_codes = "CREATE TABLE {$codes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            short_code VARCHAR(20) NOT NULL,
            title VARCHAR(191) NOT NULL,
            destination_url TEXT NOT NULL,
            style_json LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY short_code (short_code),
            KEY status (status)
        ) {$charset_collate};";

        $sql_scans = "CREATE TABLE {$scans_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code_id BIGINT UNSIGNED NOT NULL,
            scanned_at DATETIME NOT NULL,
            ip_hash CHAR(64) NULL,
            device_type VARCHAR(20) NULL,
            browser VARCHAR(40) NULL,
            os VARCHAR(40) NULL,
            referrer VARCHAR(500) NULL,
            PRIMARY KEY  (id),
            KEY code_datetime (code_id, scanned_at)
        ) {$charset_collate};";

        dbDelta($sql_codes);
        dbDelta($sql_scans);
    }
}
