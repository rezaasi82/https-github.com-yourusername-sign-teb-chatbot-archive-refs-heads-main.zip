<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Prompt\PromptContextBuilder;
use Medora\Authority\Score\AuthorityScoreCalculator;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recomputes the AI Authority Score for a post and refreshes its prompt pack.
 */
final class AnalyzePostJob implements JobInterface
{
    public function handle(array $payload, Container $container): void
    {
        $postId = (int) ($payload['post_id'] ?? 0);
        $post   = get_post($postId);

        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }

        // The prompt pack is regenerated first because several scorers grade
        // the quality of exactly that artefact.
        $container->get(PromptContextBuilder::class)->build($post, true);
        $container->get(AuthorityScoreCalculator::class)->analyze($post, true);
    }
}
