<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Vector\VectorIndex;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class EmbedPostJob implements JobInterface
{
    public function handle(array $payload, Container $container): void
    {
        $postId = (int) ($payload['post_id'] ?? 0);
        $post   = get_post($postId);

        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }

        $container->get(VectorIndex::class)->indexPost($post);
    }
}
