<?php

namespace Nobatyar\Core;

if (! defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        self::check_requirements();
        self::create_tables();
        self::schedule_cron_events();

        update_option('nobatyar_db_version', NOBATYAR_DB_VERSION);
    }

    private static function check_requirements(): void
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(NOBATYAR_PLUGIN_FILE));
            wp_die(esc_html__('نوبتیار نیاز به PHP نسخه ۷.۴ یا بالاتر دارد.', 'nobatyar-booking'));
        }

        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(plugin_basename(NOBATYAR_PLUGIN_FILE));
            wp_die(esc_html__('نوبتیار نیاز به وردپرس نسخه ۵.۸ یا بالاتر دارد.', 'nobatyar-booking'));
        }
    }

    /**
     * Public so Migrator can re-run the (now-extended) table definitions on
     * upgrade — dbDelta() only adds missing columns/tables, it never drops
     * or truncates existing data, so this is safe to call on a site that
     * already has live bookings.
     */
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix           = $wpdb->prefix . 'nby_';

        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}providers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            label_override VARCHAR(50) NULL,
            license_field VARCHAR(100) NULL,
            avatar_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}services (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 30,
            buffer_minutes INT NOT NULL DEFAULT 0,
            price DECIMAL(12,2) NULL,
            deposit_amount DECIMAL(12,2) NULL,
            capacity_min INT UNSIGNED NOT NULL DEFAULT 1,
            capacity_max INT UNSIGNED NOT NULL DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}provider_services (
            provider_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (provider_id, service_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}availability (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NOT NULL,
            weekday TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            INDEX idx_provider (provider_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}availability_exceptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NULL,
            date DATE NOT NULL,
            is_full_day TINYINT(1) DEFAULT 1,
            start_time TIME NULL,
            end_time TIME NULL
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}bookings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(191) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_email VARCHAR(191) NULL,
            booking_datetime DATETIME NOT NULL,
            status ENUM('pending','confirmed','done','cancelled','no_show') DEFAULT 'pending',
            notes TEXT NULL,
            reminder_sent_at DATETIME NULL,
            recurrence_group_id BIGINT UNSIGNED NULL,
            recurrence_index INT UNSIGNED NULL,
            recurrence_total INT UNSIGNED NULL,
            package_purchase_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_provider_datetime (provider_id, booking_datetime),
            INDEX idx_status (status),
            INDEX idx_recurrence_group (recurrence_group_id),
            INDEX idx_package_purchase (package_purchase_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}packages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            session_count INT UNSIGNED NOT NULL DEFAULT 1,
            price DECIMAL(12,2) NOT NULL,
            validity_days INT UNSIGNED NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_service (service_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}package_purchases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            package_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(191) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_email VARCHAR(191) NULL,
            sessions_total INT UNSIGNED NOT NULL,
            sessions_remaining INT UNSIGNED NOT NULL,
            purchased_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_package (package_id),
            INDEX idx_customer_phone (customer_phone)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}sms_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NULL,
            provider_name VARCHAR(50) NOT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            response_payload TEXT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            gateway VARCHAR(30) NOT NULL,
            authority VARCHAR(100) NULL,
            amount DECIMAL(12,2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            raw_response TEXT NULL,
            created_at DATETIME NOT NULL,
            verified_at DATETIME NULL
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}license (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(64) NOT NULL,
            tier VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            last_validated_at DATETIME NULL,
            expires_at DATE NULL,
            domain_hash VARCHAR(64) NULL,
            UNIQUE KEY uniq_key (license_key)
        ) {$charset_collate};";

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    private static function schedule_cron_events(): void
    {
        if (! wp_next_scheduled('nobatyar_license_check')) {
            wp_schedule_event(time(), 'daily', 'nobatyar_license_check');
        }

        if (! wp_next_scheduled('nobatyar_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'nobatyar_send_reminders');
        }
    }
}
