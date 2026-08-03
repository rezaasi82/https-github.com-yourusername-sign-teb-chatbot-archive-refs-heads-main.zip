<?php

declare(strict_types=1);

namespace Medora\Authority\Graph;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Persistence for the triple store backing the knowledge graph.
 */
final class RelationRepository
{
    public function relate(int $subjectId, string $predicate, int $objectId, float $weight = 1.0, string $source = 'inferred'): void
    {
        if ($subjectId === $objectId || $subjectId <= 0 || $objectId <= 0) {
            return;
        }

        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (subject_id, predicate, object_id, weight, source, created_at)
                 VALUES (%d, %s, %d, %f, %s, %s)
                 ON DUPLICATE KEY UPDATE weight = GREATEST(weight, VALUES(weight)), source = VALUES(source)",
                $subjectId,
                $predicate,
                $objectId,
                round($weight, 4),
                $source,
                current_time('mysql', true)
            )
        );
    }

    /**
     * Every edge touching an entity, in both directions.
     *
     * @return list<array{
     *     id: int, subject_id: int, predicate: string, object_id: int,
     *     weight: float, direction: string, other_id: int
     * }>
     */
    public function forEntity(int $entityId, int $limit = 100): array
    {
        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE subject_id = %d OR object_id = %d
                 ORDER BY weight DESC
                 LIMIT %d",
                $entityId,
                $entityId,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static function (array $row) use ($entityId): array {
            $subject = (int) $row['subject_id'];

            return [
                'id'         => (int) $row['id'],
                'subject_id' => $subject,
                'predicate'  => (string) $row['predicate'],
                'object_id'  => (int) $row['object_id'],
                'weight'     => (float) $row['weight'],
                'direction'  => $subject === $entityId ? 'outgoing' : 'incoming',
                'other_id'   => $subject === $entityId ? (int) $row['object_id'] : $subject,
            ];
        }, $rows);
    }

    /**
     * Whole-graph edge list for export and visualisation.
     *
     * @return list<array{subject_id: int, predicate: string, object_id: int, weight: float}>
     */
    public function all(int $limit = 5000): array
    {
        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT subject_id, predicate, object_id, weight FROM {$table} ORDER BY weight DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'subject_id' => (int) $row['subject_id'],
            'predicate'  => (string) $row['predicate'],
            'object_id'  => (int) $row['object_id'],
            'weight'     => (float) $row['weight'],
        ], $rows);
    }

    public function count(): int
    {
        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /** Degree (edge count) for each entity, highest first. */
    public function degrees(int $limit = 50): array
    {
        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // Counting both directions in one pass with a UNION ALL is markedly
        // cheaper than two queries plus a merge in PHP.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT entity_id, COUNT(*) AS degree FROM (
                    SELECT subject_id AS entity_id FROM {$table}
                    UNION ALL
                    SELECT object_id AS entity_id FROM {$table}
                 ) AS edges
                 GROUP BY entity_id
                 ORDER BY degree DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'entity_id' => (int) $row['entity_id'],
            'degree'    => (int) $row['degree'],
        ], $rows);
    }

    /** Remove every edge originating from a source, before a rebuild. */
    public function deleteBySource(string $source): int
    {
        global $wpdb;

        return (int) $wpdb->delete(Tables::name(Tables::RELATIONS), ['source' => $source], ['%s']);
    }
}
