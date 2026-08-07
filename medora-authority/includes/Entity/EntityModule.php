<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Extractors\AuthorExtractor;
use Medora\Authority\Entity\Extractors\DictionaryExtractor;
use Medora\Authority\Entity\Extractors\HeuristicExtractor;
use Medora\Authority\Entity\Extractors\TaxonomyExtractor;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\Jobs\IndexPostJob;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Entity Intelligence.
 *
 * Owns entity extraction, storage and per-entity authority scoring. Indexing
 * runs on the queue rather than inline on `save_post`: on a large site the
 * extraction pass is measured in hundreds of milliseconds, and no editor
 * should pay that on publish.
 */
final class EntityModule extends AbstractModule
{
    public function id(): string
    {
        return 'entity';
    }

    public function title(): string
    {
        return __('Entity Intelligence', 'medora-authority');
    }

    public function description(): string
    {
        return __('Detect the people, places, conditions and concepts your content covers, and score your authority on each.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['performance'];
    }

    public function register(Container $container): void
    {
        $container->singleton(EntityRepository::class, static fn (): EntityRepository => new EntityRepository());

        $container->singleton(
            SuppressionList::class,
            static fn (Container $c): SuppressionList => new SuppressionList($c->get(Options::class))
        );

        $container->singleton(
            EntityExtractor::class,
            static function (Container $c): EntityExtractor {
                $extractor = new EntityExtractor(
                    $c->get(EntityRepository::class),
                    $c->get(SuppressionList::class)
                );

                $extractor->addExtractor(new TaxonomyExtractor());
                $extractor->addExtractor(new AuthorExtractor($c->get(Options::class)));
                $extractor->addExtractor(new DictionaryExtractor());
                $extractor->addExtractor(new HeuristicExtractor());

                /**
                 * Register additional entity extractors.
                 *
                 * @param EntityExtractor $extractor
                 */
                do_action('medora_register_entity_extractors', $extractor);

                return $extractor;
            }
        );

        $container->singleton(
            EntityAuthorityScorer::class,
            static fn (Container $c): EntityAuthorityScorer => new EntityAuthorityScorer($c->get(EntityRepository::class))
        );
    }

    public function boot(Container $container): void
    {
        add_action('save_post', function (int $postId, WP_Post $post, bool $update) use ($container): void {
            $this->queueIndex($container, $postId, $post);
        }, 20, 3);

        add_action('deleted_post', static function (int $postId) use ($container): void {
            $container->get(EntityRepository::class)->unlinkObject('post', $postId);
        });

        add_action(\Medora\Authority\Core\Cron::DAILY_ANALYSIS, static function () use ($container): void {
            $container->get(EntityAuthorityScorer::class)->rescoreBatch(500);
        });
    }

    private function queueIndex(Container $container, int $postId, WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if ($post->post_status !== 'publish' || ! is_post_type_viewable($post->post_type)) {
            return;
        }

        $container->get(JobQueue::class)->push(IndexPostJob::class, ['post_id' => $postId]);
    }
}
