<?php

declare(strict_types=1);

namespace Medora\Authority\Linking;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use Medora\Authority\Security\AuditLogRepository;
use Medora\Authority\Support\Hash;
use Medora\Authority\Vector\VectorIndex;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Internal Linking AI.
 *
 * Suggests by default. It will apply a link, but only one at a time, only to a
 * target it suggested, only wrapping words already in the prose, and only when
 * an editor asks — see {@see LinkApplier}. There is deliberately no "apply
 * all": bulk automatic injection produces unnatural anchor text and silently
 * changes published pages, which is an editorial problem generally and a
 * compliance problem on regulated sites.
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

        $container->singleton(AnchorWrapper::class, static fn (): AnchorWrapper => new AnchorWrapper());

        $container->singleton(
            LinkApplier::class,
            static fn (Container $c): LinkApplier => new LinkApplier(
                $c->get(LinkSuggestionEngine::class),
                $c->get(AuditLogRepository::class),
                $c->get(Hash::class),
                $c->get(AnchorWrapper::class)
            )
        );
    }
}
