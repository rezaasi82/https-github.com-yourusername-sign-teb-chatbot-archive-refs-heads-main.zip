<?php

declare(strict_types=1);

namespace Medora\Authority\Schema;

use Medora\Authority\Core\Options;
use Medora\Authority\Support\ContentLanguage;
use Medora\Authority\Entity\Entity;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Everything a schema node needs to describe the current request.
 *
 * Passing one context object rather than a growing parameter list means adding
 * a new signal (say, the review date) does not change every node's signature.
 */
final class SchemaContext
{
    /**
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     */
    public function __construct(
        public readonly Options $options,
        public readonly ?WP_Post $post = null,
        public readonly array $entities = [],
        public readonly bool $isSingular = false,
        public readonly bool $isFront = false,
    ) {
    }

    /** Stable `@id` for a node on the current page. */
    public function id(string $fragment): string
    {
        $base = $this->post instanceof WP_Post ? (string) get_permalink($this->post) : home_url('/');

        return rtrim($base, '/') . '/#' . $fragment;
    }

    public function siteId(string $fragment): string
    {
        return rtrim(home_url('/'), '/') . '/#' . $fragment;
    }

    /**
     * The BCP-47 tag for whatever this context describes.
     *
     * Lives here rather than on each node so `inLanguage` is derived once, the
     * same way, everywhere — and so a per-post language from a multilingual
     * plugin reaches the page node as well as the site node.
     */
    public function language(): string
    {
        return (new ContentLanguage($this->options))->tag($this->post);
    }

    public function organizationName(): string
    {
        $name = $this->options->getString('organization_name');

        return $name !== '' ? $name : (string) get_bloginfo('name');
    }

    public function isMedical(): bool
    {
        return $this->options->getString('site_mode') === 'medical';
    }

    /** The entity the current page is primarily about. */
    public function primaryEntity(): ?Entity
    {
        return $this->entities[0]['entity'] ?? null;
    }
}
