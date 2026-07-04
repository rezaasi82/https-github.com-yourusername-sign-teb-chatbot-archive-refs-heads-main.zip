<?php
/**
 * SWC_Schema — owns table names and the dbDelta schema.
 *
 * Repositories depend only on this. Tables are created on activation and on
 * in-place upgrade, and dropped only on uninstall.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Schema
{
    public const DB_VERSION = '1.0.0';

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

    /**
     * Create / update tables via dbDelta. Safe to run repeatedly.
     */
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $conversations   = self::conversations_table();
        $messages        = self::messages_table();

        $sql_conversations = "CREATE TABLE {$conversations} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            visitor_ip VARCHAR(45) DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            language VARCHAR(8) DEFAULT 'fa',
            page_url TEXT DEFAULT NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            cta_type VARCHAR(32) DEFAULT NULL,
            is_lead TINYINT(1) NOT NULL DEFAULT 0,
            message_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_session (session_id),
            KEY idx_is_lead (is_lead),
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

        dbDelta($sql_conversations);
        dbDelta($sql_messages);

        update_option('swc_db_version', self::DB_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;
        $conversations = self::conversations_table();
        $messages      = self::messages_table();
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$messages}");
        $wpdb->query("DROP TABLE IF EXISTS {$conversations}");
        // phpcs:enable
        delete_option('swc_db_version');
    }
}
