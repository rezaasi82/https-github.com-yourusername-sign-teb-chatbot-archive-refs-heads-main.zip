<?php

namespace SignTeb\VideoHub\Db;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Owns the table names and the dbDelta schema. Repositories depend on this
 * only — nothing else builds a table name by hand.
 *
 * Video records themselves live in a custom post type; these tables hold the
 * high-volume, non-editorial data: analytics events, sync history, and the
 * AI work queue.
 */
class Schema
{
    public const DB_VERSION = '1.0.0';

    public static function analytics_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'stvh_analytics';
    }

    public static function sync_log_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'stvh_sync_log';
    }

    public static function ai_queue_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'stvh_ai_queue';
    }

    /**
     * Create / update tables via dbDelta. Safe to run repeatedly.
     */
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $analytics       = self::analytics_table();
        $sync_log        = self::sync_log_table();
        $ai_queue        = self::ai_queue_table();

        // event: impression = card rendered, click = card opened,
        // play = player started, heartbeat = +N seconds watched.
        $sql_analytics = "CREATE TABLE {$analytics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id BIGINT UNSIGNED NOT NULL,
            event ENUM('impression','click','play','heartbeat') NOT NULL,
            seconds INT UNSIGNED NOT NULL DEFAULT 0,
            session_hash CHAR(32) DEFAULT NULL,
            referer VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_video_event (video_id, event),
            KEY idx_created (created_at),
            KEY idx_session (session_hash)
        ) {$charset_collate};";

        $sql_sync_log = "CREATE TABLE {$sync_log} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(32) NOT NULL,
            status ENUM('success','partial','failed') NOT NULL DEFAULT 'success',
            imported INT UNSIGNED NOT NULL DEFAULT 0,
            updated INT UNSIGNED NOT NULL DEFAULT 0,
            skipped INT UNSIGNED NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_source (source),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        $sql_ai_queue = "CREATE TABLE {$ai_queue} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id BIGINT UNSIGNED NOT NULL,
            task VARCHAR(24) NOT NULL,
            status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_job (video_id, task),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql_analytics);
        dbDelta($sql_sync_log);
        dbDelta($sql_ai_queue);

        update_option('stvh_db_version', self::DB_VERSION, false);
    }

    public static function needs_upgrade(): bool
    {
        return get_option('stvh_db_version') !== self::DB_VERSION;
    }

    /**
     * Called from uninstall.php only.
     */
    public static function uninstall(): void
    {
        global $wpdb;
        foreach ([self::analytics_table(), self::sync_log_table(), self::ai_queue_table()] as $table) {
            // Table names come from $wpdb->prefix and cannot be parameterized.
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL
        }
        delete_option('stvh_db_version');
    }
}
