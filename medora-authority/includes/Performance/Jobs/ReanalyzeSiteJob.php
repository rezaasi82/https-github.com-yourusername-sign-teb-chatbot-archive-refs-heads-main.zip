<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Performance\JobQueue;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Re-score the whole site, a page at a time.
 *
 * Needed whenever the scoring configuration changes rather than the content:
 * an upgrade that adds a dimension, a module toggle that adds or removes one,
 * a switch between general and medical mode. Until every page has been
 * re-scored, the site report is ranking scores computed under two different
 * configurations against each other.
 *
 * Paged rather than done in one pass. "Queue a job for every published post"
 * is fine on a fifty-page site and inserts fifty thousand rows in one request
 * on a large one; this walks the archive in batches and re-queues itself,
 * so the work is bounded however big the site is.
 */
final class ReanalyzeSiteJob implements JobInterface
{
    private const BATCH = 100;

    public function handle(array $payload, Container $container): void
    {
        $offset = max(0, (int) ($payload['offset'] ?? 0));
        $queue  = $container->get(JobQueue::class);

        $postIds = get_posts([
            'post_type'        => 'any',
            'post_status'      => 'publish',
            'posts_per_page'   => self::BATCH,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => true,
        ]);

        foreach ($postIds as $postId) {
            // `push()` collapses duplicates, so a page already waiting to be
            // analysed for an unrelated reason is not queued twice.
            $queue->push(AnalyzePostJob::class, ['post_id' => (int) $postId]);
        }

        if (count($postIds) < self::BATCH) {
            return;
        }

        // Ordered by ID and paged by offset, so a post published while the
        // sweep is running can shift the window. That is acceptable: the miss
        // is one page, and it will be analysed on publish anyway.
        $queue->push(
            self::class,
            ['offset' => $offset + self::BATCH],
            0,
            'maintenance'
        );
    }
}
