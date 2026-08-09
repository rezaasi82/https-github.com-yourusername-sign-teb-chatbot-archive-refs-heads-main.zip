<?php
/**
 * Repository for individual chat messages.
 *
 * @package Medora
 */

namespace Medora\Database;

if (! defined('ABSPATH')) {
    exit;
}

class MessageRepository
{
    public function add(int $conversation_id, string $role, string $content, bool $flagged = false, ?int $tokens = null): int
    {
        global $wpdb;
        $table = \Medora\Database\Schema::messages_table();
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
     * Recent turns oldest-first, capped for prompt budget.
     *
     * The window is taken from the newest end, so on a long conversation it can
     * open on an assistant turn — which the Anthropic Messages API rejects
     * ("first message must use the user role"), turning every further message
     * into the generic fallback reply. Leading assistant turns are therefore
     * dropped and consecutive same-role turns merged before the list is handed
     * to a provider.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function history(int $conversation_id, int $limit = 12): array
    {
        global $wpdb;
        $table = \Medora\Database\Schema::messages_table();

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

        $turns = array_map(
            static fn($r) => ['role' => $r->role, 'content' => $r->content],
            $rows
        );

        return self::normalise($turns);
    }

    /**
     * Drop leading assistant turns and merge consecutive same-role turns, so
     * the list always starts with a user turn and strictly alternates.
     *
     * @param array<int,array{role:string,content:string}> $turns
     * @return array<int,array{role:string,content:string}>
     */
    private static function normalise(array $turns): array
    {
        while ($turns !== [] && $turns[0]['role'] !== 'user') {
            array_shift($turns);
        }

        $out = [];
        foreach ($turns as $turn) {
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['role'] === $turn['role']) {
                $out[$last]['content'] .= "\n" . $turn['content'];
                continue;
            }
            $out[] = $turn;
        }

        return $out;
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
        $table = \Medora\Database\Schema::messages_table();
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
        $table = \Medora\Database\Schema::messages_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC",
                $conversation_id
            )
        ) ?: [];
    }
}
