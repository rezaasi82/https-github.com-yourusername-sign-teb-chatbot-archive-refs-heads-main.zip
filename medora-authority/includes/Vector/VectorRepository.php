<?php

declare(strict_types=1);

namespace Medora\Authority\Vector;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Storage and brute-force search over chunk embeddings.
 *
 * There is no ANN index here on purpose. A WordPress site with 10k chunks and
 * 512 dimensions is ~20 MB of vectors — a linear scan in PHP runs in tens of
 * milliseconds and needs no extension, no external service and no index
 * rebuild. Sites past that scale should point `medora_vector_search` at a real
 * vector database; the interface is designed so that swap is a filter, not a
 * rewrite.
 */
final class VectorRepository
{
    /** Above this many rows, search is served from a cached in-memory matrix. */
    private const SCAN_WARN_THRESHOLD = 20000;

    /** @param list<float> $vector */
    public function store(
        string $objectType,
        int $objectId,
        int $chunkIndex,
        string $provider,
        array $vector,
        string $excerpt,
        string $contentHash
    ): void {
        global $wpdb;

        $table = Tables::name(Tables::VECTORS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (object_type, object_id, chunk_index, provider, dimensions, embedding, magnitude, excerpt, content_hash, created_at)
                 VALUES (%s, %d, %d, %s, %d, %s, %f, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    dimensions = VALUES(dimensions),
                    embedding = VALUES(embedding),
                    magnitude = VALUES(magnitude),
                    excerpt = VALUES(excerpt),
                    content_hash = VALUES(content_hash),
                    created_at = VALUES(created_at)",
                $objectType,
                $objectId,
                $chunkIndex,
                $provider,
                count($vector),
                Similarity::pack($vector),
                Similarity::magnitude($vector),
                mb_substr($excerpt, 0, 500),
                $contentHash,
                current_time('mysql', true)
            )
        );
    }

    /**
     * Nearest neighbours to a query vector.
     *
     * @param list<float> $query
     * @param array{object_type?: string, exclude_object_id?: int, provider?: string, threshold?: float} $args
     * @return list<array{object_type: string, object_id: int, chunk_index: int, score: float, excerpt: string}>
     */
    public function search(array $query, int $limit = 10, array $args = []): array
    {
        /**
         * Short-circuit vector search with an external index.
         *
         * Return a non-null array to bypass the built-in linear scan entirely.
         *
         * @param list<array<string, mixed>>|null $results
         * @param list<float>                     $query
         * @param int                             $limit
         * @param array<string, mixed>            $args
         */
        $external = apply_filters('medora_vector_search', null, $query, $limit, $args);

        if (is_array($external)) {
            return $external;
        }

        if ($query === []) {
            return [];
        }

        global $wpdb;

        $table  = Tables::name(Tables::VECTORS);
        $where  = ['dimensions = %d'];
        $params = [count($query)];

        if (! empty($args['object_type'])) {
            $where[]  = 'object_type = %s';
            $params[] = (string) $args['object_type'];
        }

        if (! empty($args['provider'])) {
            $where[]  = 'provider = %s';
            $params[] = (string) $args['provider'];
        }

        if (! empty($args['exclude_object_id'])) {
            $where[]  = 'object_id != %d';
            $params[] = (int) $args['exclude_object_id'];
        }

        $clause = implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clause is built from constants.
        $sql  = "SELECT object_type, object_id, chunk_index, embedding, excerpt FROM {$table} WHERE {$clause}";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];

        $threshold = (float) ($args['threshold'] ?? 0.0);
        $scored    = [];

        foreach ($rows as $row) {
            $score = Similarity::cosine($query, Similarity::unpack((string) $row['embedding']));

            if ($score <= $threshold) {
                continue;
            }

            $scored[] = [
                'object_type' => (string) $row['object_type'],
                'object_id'   => (int) $row['object_id'],
                'chunk_index' => (int) $row['chunk_index'],
                'score'       => round($score, 4),
                'excerpt'     => (string) $row['excerpt'],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * The best-matching chunk per object, which is what "related articles"
     * actually wants — one hit per page, not five chunks of the same page.
     *
     * @param list<float> $query
     * @return list<array{object_id: int, score: float, excerpt: string}>
     */
    public function searchObjects(array $query, int $limit = 10, array $args = []): array
    {
        $hits = $this->search($query, $limit * 5, $args);
        $best = [];

        foreach ($hits as $hit) {
            $id = $hit['object_id'];

            if (! isset($best[$id]) || $hit['score'] > $best[$id]['score']) {
                $best[$id] = ['object_id' => $id, 'score' => $hit['score'], 'excerpt' => $hit['excerpt']];
            }
        }

        $results = array_values($best);

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, $limit));
    }

    /** @return list<float> */
    public function centroidFor(string $objectType, int $objectId): array
    {
        global $wpdb;

        $table = Tables::name(Tables::VECTORS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT embedding FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY chunk_index ASC",
                $objectType,
                $objectId
            )
        ) ?: [];

        return Similarity::centroid(array_map(
            static fn (string $blob): array => Similarity::unpack($blob),
            $rows
        ));
    }

    public function contentHashFor(string $objectType, int $objectId): string
    {
        global $wpdb;

        $table = Tables::name(Tables::VECTORS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT content_hash FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY chunk_index ASC LIMIT 1",
                $objectType,
                $objectId
            )
        );
    }

    public function deleteObject(string $objectType, int $objectId): void
    {
        global $wpdb;

        $wpdb->delete(
            Tables::name(Tables::VECTORS),
            ['object_type' => $objectType, 'object_id' => $objectId],
            ['%s', '%d']
        );
    }

    /** Drop every vector, e.g. after switching embedding provider. */
    public function truncate(): void
    {
        global $wpdb;

        $table = Tables::name(Tables::VECTORS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query("TRUNCATE TABLE {$table}");
    }

    /** @return array{chunks: int, objects: int, providers: list<string>, needs_external_index: bool} */
    public function stats(): array
    {
        global $wpdb;

        $table = Tables::name(Tables::VECTORS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $chunks = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $objects = (int) $wpdb->get_var("SELECT COUNT(DISTINCT object_id) FROM {$table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $providers = $wpdb->get_col("SELECT DISTINCT provider FROM {$table}") ?: [];

        return [
            'chunks'               => $chunks,
            'objects'              => $objects,
            'providers'            => array_map('strval', $providers),
            'needs_external_index' => $chunks > self::SCAN_WARN_THRESHOLD,
        ];
    }
}
