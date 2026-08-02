<?php

declare(strict_types=1);

namespace Medora\Authority\Vector;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Support\Chunker;
use Medora\Authority\Vector\Providers\HashingEmbeddingProvider;
use Medora\Authority\Vector\Providers\OpenAiEmbeddingProvider;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vector Engine — embeddings, semantic search and RAG-ready retrieval.
 */
final class VectorModule extends AbstractModule
{
    public function id(): string
    {
        return 'vector';
    }

    public function title(): string
    {
        return __('Vector Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Embed your content for semantic search, related-content and retrieval-augmented answers.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['performance'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(Chunker::class, static fn (): Chunker => new Chunker());
        $container->singleton(VectorRepository::class, static fn (): VectorRepository => new VectorRepository());

        $container->singleton(
            EmbeddingProviderInterface::class,
            static function (Container $c): EmbeddingProviderInterface {
                $options  = $c->get(Options::class);
                $selected = $options->getString('embedding_provider', 'hashing');

                /**
                 * Register embedding providers.
                 *
                 * @param array<string, EmbeddingProviderInterface> $providers
                 */
                $providers = (array) apply_filters('medora_embedding_providers', [
                    'hashing' => new HashingEmbeddingProvider($options->getInt('embedding_dimensions', 512)),
                    'openai'  => new OpenAiEmbeddingProvider($options),
                ]);

                $provider = $providers[$selected] ?? null;

                // Falling back rather than throwing keeps semantic features
                // alive when a customer's API key expires.
                if (! $provider instanceof EmbeddingProviderInterface || ! $provider->isAvailable()) {
                    $provider = $providers['hashing'] ?? new HashingEmbeddingProvider();
                }

                return $provider;
            }
        );

        $container->singleton(
            VectorIndex::class,
            static fn (Container $c): VectorIndex => new VectorIndex(
                $c->get(VectorRepository::class),
                $c->get(EmbeddingProviderInterface::class),
                $c->get(Chunker::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        add_action('deleted_post', static function (int $postId) use ($container): void {
            $container->get(VectorRepository::class)->deleteObject('post', $postId);
        });

        // Switching provider changes the vector space entirely; keeping the old
        // vectors would silently return nonsense similarity scores.
        add_action('medora_settings_updated', static function (array $settings) use ($container): void {
            $active = $container->get(EmbeddingProviderInterface::class)->id();

            if (($settings['embedding_provider'] ?? $active) !== $active) {
                $container->get(VectorRepository::class)->truncate();
            }
        });
    }
}
