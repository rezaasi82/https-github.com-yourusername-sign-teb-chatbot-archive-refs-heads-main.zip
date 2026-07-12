<?php
/**
 * SWC_Audit_Log — security & admin event trail.
 *
 * Records who did what (settings changes, license activation, exports, branch
 * edits, denied/locked-out attempts, integrity anomalies) with IP and time.
 * Self-registering: hooks existing plugin actions and adds an admin viewer.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Audit_Log
{
    private const PAGE = 'swc-security';

    private const SEVERITIES = ['info', 'warning', 'critical'];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20); // after the parent menu (priority 10).

        // Hook existing plugin events into the trail.
        add_action('swc_license_status_changed', static function ($status): void {
            self::record('license_change', ['object' => (string) $status, 'severity' => 'info']);
        });
        add_action('swc_pdf_generated', static function ($lead_id): void {
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
            SWC_Schema::audit_logs_table(),
            [
                'user_id'    => get_current_user_id() ?: null,
                'action'     => substr($action, 0, 48),
                'object'     => isset($args['object']) ? substr((string) $args['object'], 0, 64) : null,
                'severity'   => $severity,
                'ip'         => class_exists('SWC_Security') ? SWC_Security::ip() : null,
                'detail'     => isset($args['detail']) ? substr((string) $args['detail'], 0, 255) : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Cheap probabilistic retention (~90 days).
        if (wp_rand(1, 50) === 1) {
            $table = SWC_Schema::audit_logs_table();
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("DELETE FROM {$table} WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY");
        }
    }

    /** @return array<int,object> */
    public static function recent(int $limit = 100): array
    {
        global $wpdb;
        $table = SWC_Schema::audit_logs_table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit)
        ) ?: [];
    }

    public function menu(): void
    {
        add_submenu_page(
            'swc-chat',
            __('لاگ امنیت', 'signteb-web-chat'),
            __('لاگ امنیت', 'signteb-web-chat'),
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
        include SWC_DIR . 'includes/admin/views/audit-log.php';
    }
}
