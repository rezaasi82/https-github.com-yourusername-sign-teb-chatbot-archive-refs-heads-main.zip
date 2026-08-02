<?php

declare(strict_types=1);

namespace Medora\Authority\Prompt;

use Medora\Authority\Core\Tables;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

final class PromptPackRepository
{
    /** @param array<string, mixed> $pack */
    public function save(string $objectType, int $objectId, array $pack, string $contentHash): void
    {
        global $wpdb;

        $table = Tables::name(Tables::PROMPT_PACKS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (object_type, object_id, summary, canonical_answer, questions, facts, context_window, content_hash, generated_at)
                 VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    summary = VALUES(summary),
                    canonical_answer = VALUES(canonical_answer),
                    questions = VALUES(questions),
                    facts = VALUES(facts),
                    context_window = VALUES(context_window),
                    content_hash = VALUES(content_hash),
                    generated_at = VALUES(generated_at)",
                $objectType,
                $objectId,
                (string) ($pack['summary'] ?? ''),
                (string) ($pack['canonical_answer'] ?? ''),
                Arr::toJson((array) ($pack['questions'] ?? [])),
                Arr::toJson((array) ($pack['facts'] ?? [])),
                (string) ($pack['context_window'] ?? ''),
                $contentHash,
                current_time('mysql', true)
            )
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $objectType, int $objectId): ?array
    {
        global $wpdb;

        $table = Tables::name(Tables::PROMPT_PACKS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d",
                $objectType,
                $objectId
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        return [
            'summary'          => (string) $row['summary'],
            'canonical_answer' => (string) $row['canonical_answer'],
            'questions'        => Arr::fromJson($row['questions']),
            'facts'            => Arr::fromJson($row['facts']),
            'context_window'   => (string) $row['context_window'],
            'content_hash'     => (string) $row['content_hash'],
            'generated_at'     => (string) $row['generated_at'],
        ];
    }

    public function delete(string $objectType, int $objectId): void
    {
        global $wpdb;

        $wpdb->delete(
            Tables::name(Tables::PROMPT_PACKS),
            ['object_type' => $objectType, 'object_id' => $objectId],
            ['%s', '%d']
        );
    }

    public function count(): int
    {
        global $wpdb;

        $table = Tables::name(Tables::PROMPT_PACKS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
