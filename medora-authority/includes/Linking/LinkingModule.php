<?php

declare(strict_types=1);

namespace Medora\Authority\Linking;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Vector\VectorIndex;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Internal Linking AI.
 *
 * Suggests only — it never rewrites content. Automatic link injection produces
 * unnatural anchor text and silently changes published pages, which is both an
 * editorial and a compliance problem on regulated sites.
 */
final class LinkingModule extends AbstractModule
{
    public function id(): string
    {
        return 'linking';
    }

    public function title(): string
    {
        return __('Internal Linking AI', 'medora-authority');
    }

    public function description(): string
    {
        return __('Suggest context-aware internal links from semantic similarity and shared entities.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['vector', 'entity'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(
            LinkSuggestionEngine::class,
            static fn (Container $c): LinkSuggestionEngine => new LinkSuggestionEngine(
                $c->get(VectorIndex::class),
                $c->get(EntityRepository::class)
            )
        );
    }
}
