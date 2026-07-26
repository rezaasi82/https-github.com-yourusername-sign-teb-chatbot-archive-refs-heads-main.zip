<?php
/**
 * Security & admin event trail.
 *
 * Records who did what (settings changes, exports, branch
 * edits, denied/locked-out attempts, integrity anomalies) with IP and time.
 * Self-registering: hooks existing plugin actions and adds an admin viewer.
 *
 * @package Pazira
 */

namespace Pazira\Security;

if (! defined('ABSPATH')) {
    exit;
}

class AuditLog
{
    private const PAGE = 'pzr-security';

    private const SEVERITIES = ['info', 'warning', 'critical'];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);

        // Hook existing plugin events into the trail.
        add_action('pzr_pdf_generated', static function ($lead_id): void {
            self::record('export_pdf', ['object' => 'lead#' . (int) $lead_id, 'severity' => 'info']);
        });
    }

    /**
     * @param array{object?:string,severity?:string,detail?:string} $args
     */
    public static function record(string $action, array $args = []): void
    {
        global $wpdb;
        $severity = in_array($args['severity'] ?? 'info', self::SEVERITIES, true) ? $args['severity'] : 'info';

        $wpdb->insert(
            \Pazira\Database\Schema::audit_logs_table(),
            [
                'user_id'    => get_current_user_id() ?: null,
                'action'     => substr($action, 0, 48),
                'object'     => isset($args['object']) ? substr((string) $args['object'], 0, 64) : null,
                'severity'   => $severity,
                'ip'         => class_exists('\Pazira\Security\Security') ? \Pazira\Security\Security::ip() : null,
                'detail'     => isset($args['detail']) ? substr((string) $args['detail'], 0, 255) : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Cheap probabilistic retention (~90 days).
        if (wp_rand(1, 50) === 1) {
            $table = \Pazira\Database\Schema::audit_logs_table();

            $wpdb->query("DELETE FROM {$table} WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY");
        }
    }

    /** @return array<int,object> */
    public static function recent(int $limit = 100): array
    {
        global $wpdb;
        $table = \Pazira\Database\Schema::audit_logs_table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit)
        ) ?: [];
    }

    public function menu(): void
    {
        add_submenu_page(
            'pzr-chat',
            __('لاگ امنیت', 'pazira'),
            __('لاگ امنیت', 'pazira'),
            'manage_options',
            self::PAGE,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $rows = self::recent(200);
        include PZR_DIR . 'includes/admin/views/audit-log.php';
    }
}
