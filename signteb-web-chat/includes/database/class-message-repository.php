<?php
/**
 * Repository for individual chat messages.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Database;

if (! defined('ABSPATH')) {
    exit;
}

class MessageRepository
{
    public function add(int $conversation_id, string $role, string $content, bool $flagged = false, ?int $tokens = null): int
    {
        global $wpdb;
        $table = \SignTeb\WebChat\Database\Schema::messages_table();
        $wpdb->insert(
            $table,
            [
                'conversation_id' => $conversation_id,
                'role'            => $role,
                'content'         => $content,
                'flagged'         => $flagged ? 1 : 0,
                'tokens'          => $tokens,
                'created_at'      => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s']
        );
        return (int) $wpdb->insert_id;
    }

    /**
     * Recent turns oldest-first, capped for prompt budget. Excludes the latest
     * pending user turn so the provider can append it as the final message.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function history(int $conversation_id, int $limit = 12): array
    {
        global $wpdb;
        $table = \SignTeb\WebChat\Database\Schema::messages_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content FROM {$table}
                 WHERE conversation_id = %d AND role IN ('user','assistant')
                 ORDER BY id DESC LIMIT %d",
                $conversation_id,
                $limit
            )
        ) ?: [];

        $rows = array_reverse($rows);

        return array_map(
            static fn($r) => ['role' => $r->role, 'content' => $r->content],
            $rows
        );
    }

    /**
     * All visitor (user) message texts, oldest-first. Used for lead scoring
     * and the auto-summary.
     *
     * @return array<int,string>
     */
    public function user_texts(int $conversation_id): array
    {
        global $wpdb;
        $table = \SignTeb\WebChat\Database\Schema::messages_table();
        $rows  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT content FROM {$table} WHERE conversation_id = %d AND role = 'user' ORDER BY id ASC",
                $conversation_id
            )
        ) ?: [];
        return array_map('strval', $rows);
    }

    /** @return array<int,object> */
    public function for_conversation(int $conversation_id): array
    {
        global $wpdb;
        $table = \SignTeb\WebChat\Database\Schema::messages_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC",
                $conversation_id
            )
        ) ?: [];
    }
}
