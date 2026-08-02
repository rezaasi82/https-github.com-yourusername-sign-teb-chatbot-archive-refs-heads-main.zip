<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

use Medora\Authority\Core\Tables;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

final class CitationRepository
{
    public function save(string $objectType, int $objectId, Citation $citation, array $raw = []): int
    {
        global $wpdb;

        $existing = $this->findDuplicate($objectType, $objectId, $citation);

        $data = [
            'object_type'    => $objectType,
            'object_id'      => $objectId,
            'doi'            => $citation->doi,
            'pmid'           => $citation->pmid,
            'title'          => $citation->title,
            'authors'        => Arr::toJson($citation->authors),
            'container'      => mb_substr($citation->container, 0, 191),
            'published_year' => $citation->year,
            'url'            => mb_substr($citation->url, 0, 255),
            'evidence_level' => $citation->evidenceLevel,
            'quality_score'  => round($citation->qualityScore, 2),
            'raw'            => Arr::toJson($raw),
        ];

        if ($existing > 0) {
            $wpdb->update(Tables::name(Tables::CITATIONS), $data, ['id' => $existing], null, ['%d']);

            return $existing;
        }

        $wpdb->insert(Tables::name(Tables::CITATIONS), $data + ['created_at' => current_time('mysql', true)]);

        return (int) $wpdb->insert_id;
    }

    /** @return list<Citation> */
    public function forObject(string $objectType, int $objectId): array
    {
        global $wpdb;

        $table = Tables::name(Tables::CITATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY quality_score DESC, published_year DESC",
                $objectType,
                $objectId
            ),
            ARRAY_A
        ) ?: [];

        return array_map(static fn (array $row): Citation => Citation::fromRow($row), $rows);
    }

    public function countForObject(string $objectType, int $objectId): int
    {
        global $wpdb;

        $table = Tables::name(Tables::CITATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_type = %s AND object_id = %d",
                $objectType,
                $objectId
            )
        );
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(Tables::name(Tables::CITATIONS), ['id' => $id], ['%d']);
    }

    public function deleteForObject(string $objectType, int $objectId): void
    {
        global $wpdb;

        $wpdb->delete(
            Tables::name(Tables::CITATIONS),
            ['object_type' => $objectType, 'object_id' => $objectId],
            ['%s', '%d']
        );
    }

    /** @return array{total: int, with_doi: int, average_quality: float} */
    public function stats(): array
    {
        global $wpdb;

        $table = Tables::name(Tables::CITATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $row = $wpdb->get_row(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN doi <> '' THEN 1 ELSE 0 END) AS with_doi,
                    AVG(quality_score) AS average_quality
             FROM {$table}",
            ARRAY_A
        );

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'with_doi'        => (int) ($row['with_doi'] ?? 0),
            'average_quality' => round((float) ($row['average_quality'] ?? 0), 1),
        ];
    }

    private function findDuplicate(string $objectType, int $objectId, Citation $citation): int
    {
        global $wpdb;

        $table = Tables::name(Tables::CITATIONS);

        // Identity is the persistent identifier when there is one; only fall
        // back to title matching for references that have neither.
        if ($citation->doi !== '') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE object_type = %s AND object_id = %d AND doi = %s LIMIT 1",
                $objectType,
                $objectId,
                $citation->doi
            ));
        }

        if ($citation->pmid !== '') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE object_type = %s AND object_id = %d AND pmid = %s LIMIT 1",
                $objectType,
                $objectId,
                $citation->pmid
            ));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE object_type = %s AND object_id = %d AND title = %s LIMIT 1",
            $objectType,
            $objectId,
            $citation->title
        ));
    }
}
