<?php
/**
 * Owns table names and the dbDelta schema.
 *
 * Repositories depend only on this. dbDelta adds any new columns on upgrade,
 * so bumping DB_VERSION is enough to migrate an existing install in place.
 *
 * @package Pazira
 */

namespace Pazira\Database;

if (! defined('ABSPATH')) {
    exit;
}

class Schema
{
    public const DB_VERSION = '3.4.0';

    public static function conversations_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_conversations';
    }

    public static function messages_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_messages';
    }

    public static function events_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_events';
    }

    public static function sync_logs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_sync_logs';
    }

    public static function analytics_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_analytics';
    }

    public static function jobs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_jobs';
    }

    public static function branches_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_branches';
    }

    public static function audit_logs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pzr_audit_logs';
    }

    /**
     * Create / update tables via dbDelta. Safe to run repeatedly; on upgrade
     * dbDelta adds the new patient/lead/summary columns and the events table.
     */
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $conversations   = self::conversations_table();
        $messages        = self::messages_table();
        $events          = self::events_table();

        $sql_conversations = "CREATE TABLE {$conversations} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            patient_name VARCHAR(120) DEFAULT NULL,
            patient_phone VARCHAR(32) DEFAULT NULL,
            visitor_ip VARCHAR(45) DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            language VARCHAR(8) DEFAULT 'fa',
            page_url TEXT DEFAULT NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            cta_type VARCHAR(32) DEFAULT NULL,
            is_lead TINYINT(1) NOT NULL DEFAULT 0,
            lead_score VARCHAR(8) DEFAULT NULL,
            booking_status VARCHAR(16) NOT NULL DEFAULT 'none',
            summary LONGTEXT DEFAULT NULL,
            pdf_url VARCHAR(255) DEFAULT NULL,
            email VARCHAR(190) DEFAULT NULL,
            lead_status VARCHAR(20) NOT NULL DEFAULT 'new',
            notes LONGTEXT DEFAULT NULL,
            tags VARCHAR(255) DEFAULT NULL,
            branch_id BIGINT UNSIGNED DEFAULT NULL,
            message_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_session (session_id),
            KEY idx_is_lead (is_lead),
            KEY idx_lead_score (lead_score),
            KEY idx_lead_status (lead_status),
            KEY idx_phone (patient_phone),
            KEY idx_created (created_at),
            KEY idx_lead_created (is_lead, created_at),
            KEY idx_status_created (lead_status, created_at),
            KEY idx_branch (branch_id)
        ) {$charset_collate};";

        $sql_messages = "CREATE TABLE {$messages} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED NOT NULL,
            role ENUM('user','assistant','system') NOT NULL,
            content LONGTEXT NOT NULL,
            flagged TINYINT(1) NOT NULL DEFAULT 0,
            tokens INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_conversation (conversation_id),
            KEY idx_role (role)
        ) {$charset_collate};";

        $sql_events = "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_type (type),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        $sync_logs = self::sync_logs_table();
        $sql_sync  = "CREATE TABLE {$sync_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL,
            event VARCHAR(32) NOT NULL DEFAULT 'manual',
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            response LONGTEXT DEFAULT NULL,
            duration_ms INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_lead (lead_id),
            KEY idx_provider (provider),
            KEY idx_status (status)
        ) {$charset_collate};";

        $analytics = self::analytics_table();
        $sql_analytics = "CREATE TABLE {$analytics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            day DATE NOT NULL,
            metric VARCHAR(32) NOT NULL,
            value BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_day_metric (day, metric),
            KEY idx_metric (metric)
        ) {$charset_collate};";

        $jobs     = self::jobs_table();
        $sql_jobs = "CREATE TABLE {$jobs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(32) NOT NULL,
            payload TEXT DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'queued',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status)
        ) {$charset_collate};";

        $branches     = self::branches_table();
        $sql_branches = "CREATE TABLE {$branches} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            doctor VARCHAR(190) DEFAULT NULL,
            phone VARCHAR(32) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $audit     = self::audit_logs_table();
        $sql_audit = "CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(48) NOT NULL,
            object VARCHAR(64) DEFAULT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'info',
            ip VARCHAR(45) DEFAULT NULL,
            detail VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_action (action),
            KEY idx_severity (severity),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_events);
        dbDelta($sql_sync);
        dbDelta($sql_analytics);
        dbDelta($sql_jobs);
        dbDelta($sql_branches);
        dbDelta($sql_audit);

        update_option('pzr_db_version', self::DB_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;
        $conversations = self::conversations_table();
        $messages      = self::messages_table();
        $events        = self::events_table();
        $sync_logs     = self::sync_logs_table();
        $analytics     = self::analytics_table();
        $jobs          = self::jobs_table();
        $branches      = self::branches_table();
        $audit         = self::audit_logs_table();
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$audit}");
        $wpdb->query("DROP TABLE IF EXISTS {$branches}");
        $wpdb->query("DROP TABLE IF EXISTS {$jobs}");
        $wpdb->query("DROP TABLE IF EXISTS {$analytics}");
        $wpdb->query("DROP TABLE IF EXISTS {$sync_logs}");
        $wpdb->query("DROP TABLE IF EXISTS {$events}");
        $wpdb->query("DROP TABLE IF EXISTS {$messages}");
        $wpdb->query("DROP TABLE IF EXISTS {$conversations}");
        // phpcs:enable
        delete_option('pzr_db_version');
    }
}
