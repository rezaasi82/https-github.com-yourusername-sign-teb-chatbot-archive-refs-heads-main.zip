<?php
/**
 * SWC_Schema — owns table names and the dbDelta schema.
 *
 * Repositories depend only on this. dbDelta adds any new columns on upgrade,
 * so bumping DB_VERSION is enough to migrate an existing install in place.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Schema
{
    public const DB_VERSION = '2.0.0';

    public static function conversations_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'swc_conversations';
    }

    public static function messages_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'swc_messages';
    }

    public static function events_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'swc_events';
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
            message_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_session (session_id),
            KEY idx_is_lead (is_lead),
            KEY idx_lead_score (lead_score),
            KEY idx_phone (patient_phone),
            KEY idx_created (created_at)
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

        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_events);

        update_option('swc_db_version', self::DB_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;
        $conversations = self::conversations_table();
        $messages      = self::messages_table();
        $events        = self::events_table();
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$events}");
        $wpdb->query("DROP TABLE IF EXISTS {$messages}");
        $wpdb->query("DROP TABLE IF EXISTS {$conversations}");
        // phpcs:enable
        delete_option('swc_db_version');
    }
}
