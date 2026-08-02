<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\EntityExtractor;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Performance\JobQueue;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Re-indexes a post's entities, then chains the work that depends on them.
 *
 * The chain — entities, then embeddings, then analysis — is explicit rather
 * than implicit ordering inside one job, so a failure in embedding generation
 * (which may call a remote API) never rolls back a successful entity pass.
 */
final class IndexPostJob implements JobInterface
{
    public function handle(array $payload, Container $container): void
    {
        $postId = (int) ($payload['post_id'] ?? 0);
        $post   = get_post($postId);

        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }

        $container->get(EntityExtractor::class)->indexPost($post);

        $queue = $container->get(JobQueue::class);
        $queue->push(EmbedPostJob::class, ['post_id' => $postId]);
        $queue->push(AnalyzePostJob::class, ['post_id' => $postId], 30);
    }
}
