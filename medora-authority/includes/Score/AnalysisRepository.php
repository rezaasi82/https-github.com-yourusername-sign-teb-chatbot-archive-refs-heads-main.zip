<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

use Medora\Authority\Core\Tables;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

final class AnalysisRepository
{
    /** @param array<string, mixed> $result */
    public function save(string $objectType, int $objectId, float $overall, array $result, string $contentHash): void
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (object_type, object_id, overall_score, components, deductions, content_hash, analyzed_at)
                 VALUES (%s, %d, %f, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    overall_score = VALUES(overall_score),
                    components = VALUES(components),
                    deductions = VALUES(deductions),
                    content_hash = VALUES(content_hash),
                    analyzed_at = VALUES(analyzed_at)",
                $objectType,
                $objectId,
                round($overall, 2),
                Arr::toJson((array) ($result['components'] ?? [])),
                Arr::toJson((array) ($result['deductions'] ?? [])),
                $contentHash,
                current_time('mysql', true)
            )
        );
    }

    /**
     * @return array{result: array<string, mixed>, content_hash: string}|null
     */
    public function find(string $objectType, int $objectId): ?array
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

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

        $overall = (float) $row['overall_score'];

        return [
            'content_hash' => (string) $row['content_hash'],
            'result'       => [
                'post_id'     => (int) $row['object_id'],
                'overall'     => $overall,
                'grade'       => AuthorityScoreCalculator::gradeFor($overall),
                'components'  => Arr::fromJson($row['components']),
                'deductions'  => Arr::fromJson($row['deductions']),
                'analyzed_at' => (string) $row['analyzed_at'],
            ],
        ];
    }

    public function averageScore(): float
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (float) $wpdb->get_var("SELECT AVG(overall_score) FROM {$table}");
    }

    public function count(): int
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Grade buckets, for the dashboard histogram.
     *
     * @return array<string, int>
     */
    public function distribution(): array
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // Bucketing in SQL avoids pulling every row into PHP just to count.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN overall_score >= 90 THEN 'A'
                    WHEN overall_score >= 80 THEN 'B'
                    WHEN overall_score >= 65 THEN 'C'
                    WHEN overall_score >= 50 THEN 'D'
                    ELSE 'F'
                END AS grade,
                COUNT(*) AS total
             FROM {$table}
             GROUP BY grade",
            ARRAY_A
        ) ?: [];

        $distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

        foreach ($rows as $row) {
            $distribution[(string) $row['grade']] = (int) $row['total'];
        }

        return $distribution;
    }

    /**
     * Lowest-scoring pages — the work queue.
     *
     * @return list<array{object_id: int, score: float, title: string, url: string, analyzed_at: string}>
     */
    public function weakest(int $limit = 10): array
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, overall_score, analyzed_at
                 FROM {$table}
                 WHERE object_type = 'post'
                 ORDER BY overall_score ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];

        $results = [];

        foreach ($rows as $row) {
            $postId = (int) $row['object_id'];
            $post   = get_post($postId);

            if ($post === null) {
                continue;
            }

            $results[] = [
                'object_id'   => $postId,
                'score'       => (float) $row['overall_score'],
                'title'       => get_the_title($post),
                'url'         => (string) get_permalink($post),
                'analyzed_at' => (string) $row['analyzed_at'],
            ];
        }

        return $results;
    }

    /**
     * The deductions that recur most across the site — where a single fix
     * moves the most pages.
     *
     * @return list<array{code: string, label: string, count: int, recommendation: string}>
     */
    public function topIssues(int $limit = 8): array
    {
        global $wpdb;

        $table = Tables::name(Tables::ANALYSIS);

        // Deductions are JSON, so aggregation happens in PHP. Bounded to the
        // 500 most recent analyses to keep memory predictable on large sites.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        $rows = $wpdb->get_col(
            "SELECT deductions FROM {$table} ORDER BY analyzed_at DESC LIMIT 500"
        ) ?: [];

        $tally = [];

        foreach ($rows as $json) {
            foreach (Arr::fromJson($json) as $deduction) {
                $code = (string) ($deduction['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                $tally[$code] ??= [
                    'code'           => $code,
                    'label'          => (string) ($deduction['label'] ?? $code),
                    'count'          => 0,
                    'recommendation' => (string) ($deduction['recommendation'] ?? ''),
                ];

                $tally[$code]['count']++;
            }
        }

        usort($tally, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice(array_values($tally), 0, $limit);
    }

    public function delete(string $objectType, int $objectId): void
    {
        global $wpdb;

        $wpdb->delete(
            Tables::name(Tables::ANALYSIS),
            ['object_type' => $objectType, 'object_id' => $objectId],
            ['%s', '%d']
        );
    }
}
