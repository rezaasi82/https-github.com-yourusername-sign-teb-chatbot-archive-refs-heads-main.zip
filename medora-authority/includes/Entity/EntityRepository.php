<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Data access for entities and their occurrences.
 *
 * All writes go through `upsert()`, which is keyed on the deterministic entity
 * uid — so re-running extraction over a site converges instead of duplicating.
 */
final class EntityRepository
{
    /**
     * Insert or update by uid and return the persisted entity.
     */
    public function upsert(Entity $entity): Entity
    {
        global $wpdb;

        $row   = $entity->toRow();
        $uid   = (string) $row['entity_uid'];
        $table = Tables::name(Tables::ENTITIES);
        $now   = current_time('mysql', true);

        $existing = $this->findByUid($uid);

        if ($existing !== null) {
            // Never let a later, weaker extraction blank out a richer record:
            // descriptions and sameAs links only ever grow.
            $update = [
                'name'            => $row['name'],
                'canonical_name'  => $row['canonical_name'],
                'entity_type'     => $row['entity_type'],
                'description'     => $row['description'] !== '' ? $row['description'] : $existing->description,
                'permalink'       => $row['permalink'] !== '' ? $row['permalink'] : $existing->permalink,
                'same_as'         => wp_json_encode(array_values(array_unique(array_merge($existing->sameAs, $entity->sameAs)))),
                'meta'            => $row['meta'],
                'authority_score' => $row['authority_score'],
                'confidence'      => max((float) $row['confidence'], $existing->confidence),
                'occurrences'     => max((int) $row['occurrences'], $existing->occurrences),
                'is_primary'      => $row['is_primary'] ?: ($existing->isPrimary ? 1 : 0),
                'updated_at'      => $now,
            ];

            if ($entity->objectId > 0) {
                $update['object_type'] = $row['object_type'];
                $update['object_id']   = $row['object_id'];
            }

            $wpdb->update($table, $update, ['id' => $existing->id]);

            return $this->find($existing->id) ?? $existing;
        }

        $wpdb->insert($table, $row + ['created_at' => $now, 'updated_at' => $now]);

        return $entity->withId((int) $wpdb->insert_id, $uid);
    }

    public function find(int $id): ?Entity
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITIES);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return $row === null ? null : Entity::fromRow($row);
    }

    public function findByUid(string $uid): ?Entity
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITIES);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE entity_uid = %s", $uid), ARRAY_A);

        return $row === null ? null : Entity::fromRow($row);
    }

    public function findByName(string $name, ?string $type = null): ?Entity
    {
        global $wpdb;

        $table     = Tables::name(Tables::ENTITIES);
        $canonical = \Medora\Authority\Support\Text::normalize($name);

        if ($type !== null) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE canonical_name = %s AND entity_type = %s", $canonical, $type),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE canonical_name = %s ORDER BY authority_score DESC", $canonical),
                ARRAY_A
            );
        }

        return $row === null ? null : Entity::fromRow($row);
    }

    /**
     * Paginated, filterable listing for the entity explorer.
     *
     * @param array{type?: string, search?: string, min_score?: float, orderby?: string, per_page?: int, page?: int} $args
     * @return array{items: list<Entity>, total: int}
     */
    public function query(array $args = []): array
    {
        global $wpdb;

        $table   = Tables::name(Tables::ENTITIES);
        $where   = ['1=1'];
        $params  = [];

        if (! empty($args['type'])) {
            $where[]  = 'entity_type = %s';
            $params[] = (string) $args['type'];
        }

        if (! empty($args['search'])) {
            $where[]  = 'canonical_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like(\Medora\Authority\Support\Text::normalize((string) $args['search'])) . '%';
        }

        if (isset($args['min_score'])) {
            $where[]  = 'authority_score >= %f';
            $params[] = (float) $args['min_score'];
        }

        // Whitelist ordering columns: this segment is interpolated, so it must
        // never come from raw input.
        $orderby = match ($args['orderby'] ?? 'authority') {
            'name'        => 'canonical_name ASC',
            'occurrences' => 'occurrences DESC',
            'recent'      => 'updated_at DESC',
            default       => 'authority_score DESC',
        };

        $perPage = max(1, min(200, (int) ($args['per_page'] ?? 50)));
        $offset  = max(0, ((int) ($args['page'] ?? 1) - 1) * $perPage);
        $clause  = implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clause is built from constants only.
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";
        $total    = (int) $wpdb->get_var($params === [] ? $countSql : $wpdb->prepare($countSql, ...$params));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clause and orderby are whitelisted.
        $sql  = "SELECT * FROM {$table} WHERE {$clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...[...$params, $perPage, $offset]), ARRAY_A) ?: [];

        return [
            'items' => array_map(static fn (array $row): Entity => Entity::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    /**
     * Entities attached to a piece of content, most salient first.
     *
     * @return list<array{entity: Entity, salience: float, occurrences: int}>
     */
    public function forObject(string $objectType, int $objectId, int $limit = 50): array
    {
        global $wpdb;

        $entities = Tables::name(Tables::ENTITIES);
        $index    = Tables::name(Tables::ENTITY_INDEX);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are constants.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, i.salience, i.occurrences AS object_occurrences
                 FROM {$index} i
                 INNER JOIN {$entities} e ON e.id = i.entity_id
                 WHERE i.object_type = %s AND i.object_id = %d
                 ORDER BY i.salience DESC
                 LIMIT %d",
                $objectType,
                $objectId,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): array => [
            'entity'      => Entity::fromRow($row),
            'salience'    => (float) $row['salience'],
            'occurrences' => (int) $row['object_occurrences'],
        ], $rows);
    }

    /** Attach an entity to a piece of content. */
    public function link(int $entityId, string $objectType, int $objectId, int $occurrences, float $salience): void
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITY_INDEX);

        // A unique key on (entity, object) makes this an idempotent upsert.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (entity_id, object_type, object_id, occurrences, salience, first_seen_at)
                 VALUES (%d, %s, %d, %d, %f, %s)
                 ON DUPLICATE KEY UPDATE occurrences = VALUES(occurrences), salience = VALUES(salience)",
                $entityId,
                $objectType,
                $objectId,
                $occurrences,
                round($salience, 4),
                current_time('mysql', true)
            )
        );
    }

    /** Remove every entity link for a piece of content, before re-indexing it. */
    public function unlinkObject(string $objectType, int $objectId): void
    {
        global $wpdb;

        $wpdb->delete(
            Tables::name(Tables::ENTITY_INDEX),
            ['object_type' => $objectType, 'object_id' => $objectId],
            ['%s', '%d']
        );
    }

    public function updateAuthorityScore(int $entityId, float $score): void
    {
        global $wpdb;

        $wpdb->update(
            Tables::name(Tables::ENTITIES),
            ['authority_score' => round($score, 2), 'updated_at' => current_time('mysql', true)],
            ['id' => $entityId],
            ['%f', '%s'],
            ['%d']
        );
    }

    /** How many distinct pages mention this entity. */
    public function documentFrequency(int $entityId): int
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITY_INDEX);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE entity_id = %d", $entityId)
        );
    }

    public function count(?string $type = null): int
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITIES);

        if ($type === null) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE entity_type = %s", $type));
    }

    /** @return array<string, int> type => count */
    public function countsByType(): array
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITIES);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results("SELECT entity_type, COUNT(*) AS total FROM {$table} GROUP BY entity_type", ARRAY_A) ?: [];

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['entity_type']] = (int) $row['total'];
        }

        arsort($counts);

        return $counts;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $wpdb->delete(Tables::name(Tables::ENTITY_INDEX), ['entity_id' => $id], ['%d']);

        $relations = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query($wpdb->prepare("DELETE FROM {$relations} WHERE subject_id = %d OR object_id = %d", $id, $id));

        return (bool) $wpdb->delete(Tables::name(Tables::ENTITIES), ['id' => $id], ['%d']);
    }
}
