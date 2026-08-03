<?php

declare(strict_types=1);

namespace Medora\Authority\Security;

use Medora\Authority\Core\Tables;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Append-only audit trail for changes made through Medora.
 *
 * There is deliberately no update or targeted delete method: an audit log that
 * can be edited is not an audit log. Rows leave only through the retention
 * pruner, which removes by age and nothing else.
 */
final class AuditLogRepository
{
    /** @param array<string, mixed> $context */
    public function record(string $action, string $objectType = '', int $objectId = 0, array $context = [], string $ipHash = ''): void
    {
        global $wpdb;

        $wpdb->insert(
            Tables::name(Tables::AUDIT_LOG),
            [
                'user_id'     => get_current_user_id(),
                'action'      => substr($action, 0, 64),
                'object_type' => substr($objectType, 0, 32),
                'object_id'   => $objectId,
                'ip_hash'     => substr($ipHash, 0, 64),
                'context'     => Arr::toJson($this->redact($context)),
                'created_at'  => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * @return list<array{
     *     id: int, user_id: int, user_name: string, action: string,
     *     object_type: string, object_id: int, context: array<string, mixed>, created_at: string
     * }>
     */
    public function recent(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        $table = Tables::name(Tables::AUDIT_LOG);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
                max(1, min(500, $limit)),
                max(0, $offset)
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static function (array $row): array {
            $user = get_userdata((int) $row['user_id']);

            return [
                'id'          => (int) $row['id'],
                'user_id'     => (int) $row['user_id'],
                'user_name'   => $user !== false ? $user->display_name : __('System', 'medora-authority'),
                'action'      => (string) $row['action'],
                'object_type' => (string) $row['object_type'],
                'object_id'   => (int) $row['object_id'],
                'context'     => Arr::fromJson($row['context']),
                'created_at'  => (string) $row['created_at'],
            ];
        }, $rows);
    }

    public function count(): int
    {
        global $wpdb;

        $table = Tables::name(Tables::AUDIT_LOG);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public function prune(int $retentionDays): int
    {
        global $wpdb;

        $table  = Tables::name(Tables::AUDIT_LOG);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff));
    }

    /**
     * Strip anything that looks like a secret before it is written.
     *
     * Audit context is assembled from request payloads, so without this a
     * settings update would happily persist an API key in plain text.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $sensitive = ['api_key', 'key', 'token', 'secret', 'password', 'license_key', 'embedding_api_key'];
        $clean     = [];

        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);

            foreach ($sensitive as $needle) {
                if (str_contains($lower, $needle)) {
                    $clean[$key] = '[redacted]';
                    continue 2;
                }
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }
}
