<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Llm\PackGenerator;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Performance\JobInterface;
use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The generative pass over a page's prompt pack.
 *
 * Queued rather than inline for three reasons: an API call has no place in the
 * request that saved a post, a failure here must not cost the page its
 * extractive pack, and the queue already owns the back-off that a rate-limited
 * provider needs.
 *
 * Idempotent by content hash. `_medora_llm_pack_hash` records the version of
 * the content that was *attempted*, not the version that was adopted — so a
 * page whose generated text keeps failing the grounding check is tried once
 * per edit, not on every queue tick.
 */
final class GeneratePackJob implements JobInterface
{
    public const ATTEMPT_META = '_medora_llm_pack_hash';

    public function handle(array $payload, Container $container): void
    {
        $postId = (int) ($payload['post_id'] ?? 0);
        $post   = get_post($postId);

        if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
            return;
        }

        if (! $container->get(ModuleRegistry::class)->isBooted('llm')) {
            return;
        }

        $hash = Text::hash($post->post_title . "\n" . $post->post_content);

        // The post was edited between queueing and running. The save that
        // caused the edit queued its own job, so this one has nothing to do.
        if ($hash !== (string) ($payload['hash'] ?? $hash)) {
            return;
        }

        if (get_post_meta($postId, self::ATTEMPT_META, true) === $hash) {
            return;
        }

        $packs = $container->get(PromptPackRepository::class);
        $pack  = $packs->find('post', $postId);

        if ($pack === null || $pack['content_hash'] !== $hash) {
            return;
        }

        // Throws on transport and API failures, which is what lets the queue
        // back off and retry. The mark below is therefore only reached on a
        // completed call — including one whose output was rejected by the
        // grounding check, which is a finished answer, not a failure to retry.
        $enhanced = $container->get(PackGenerator::class)->enhance($pack, $post);

        update_post_meta($postId, self::ATTEMPT_META, $hash);

        if ($enhanced === $pack) {
            return;
        }

        $packs->save('post', $postId, $enhanced, $hash);

        // Kept in step with the pack: schema, llms.txt and the sitemap all read
        // the summary from meta rather than joining the packs table.
        update_post_meta($postId, '_medora_ai_summary', $enhanced['summary']);
    }
}
