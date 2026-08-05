<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Llm\Providers\AnthropicProvider;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\Jobs\GeneratePackJob;
use Medora\Authority\Security\AuditLogRepository;
use Medora\Authority\Support\ContentLanguage;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Writer — the generative layer.
 *
 * Off by default, and off in a meaningful sense: with the module disabled, or
 * with no API key, nothing in the plugin makes an outbound model call and every
 * summary on the site remains the publisher's own sentences. That is the
 * default because it is the safe answer for a health publisher, not because
 * generation is an afterthought.
 *
 * When it is on, the guarantee is narrower than "we asked the model nicely":
 * every generated field is checked against the page it came from and discarded
 * if it introduces vocabulary or figures the page does not contain. See
 * {@see Grounding}.
 */
final class LlmModule extends AbstractModule
{
    public function id(): string
    {
        return 'llm';
    }

    public function title(): string
    {
        return __('AI Writer', 'medora-authority');
    }

    public function description(): string
    {
        return __('Rewrite page summaries and canonical answers with a model, keeping only what the page itself supports.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['prompt', 'performance'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::AGENCY;
    }

    public function enabledByDefault(): bool
    {
        // Outbound calls to a third party, billed to the customer, over their
        // published content. That is an opt-in, always.
        return false;
    }

    public function register(Container $container): void
    {
        $container->singleton(
            AnthropicProvider::class,
            static fn (Container $c): AnthropicProvider => new AnthropicProvider($c->get(Options::class))
        );

        $container->singleton(LlmProviderInterface::class, static function (Container $c): LlmProviderInterface {
            /**
             * Filter the active text-generation provider.
             *
             * Swapping in a self-hosted model is one binding, because nothing
             * above this line knows which provider it is talking to.
             *
             * @param LlmProviderInterface $provider
             */
            return apply_filters('medora_llm_provider', $c->get(AnthropicProvider::class));
        });

        $container->singleton(
            PackGenerator::class,
            static fn (Container $c): PackGenerator => new PackGenerator(
                $c->get(LlmProviderInterface::class),
                $c->get(AuditLogRepository::class),
                $c->get(ContentLanguage::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        add_action('medora_prompt_pack_saved', static function (array $pack, WP_Post $post, string $hash) use ($container): void {
            $options = $container->get(Options::class);

            if (! $options->getBool('llm_enabled')) {
                return;
            }

            if (! $container->get(LlmProviderInterface::class)->isAvailable()) {
                return;
            }

            if (get_post_meta($post->ID, GeneratePackJob::ATTEMPT_META, true) === $hash) {
                return;
            }

            $container->get(JobQueue::class)->push(
                GeneratePackJob::class,
                ['post_id' => $post->ID, 'hash' => $hash],
                0,
                // Tagged into its own queue. One worker still drains them all,
                // but the queue breakdown is what tells an operator that a
                // stalled backlog is the model provider and not the site.
                'llm'
            );
        }, 10, 3);
    }
}
