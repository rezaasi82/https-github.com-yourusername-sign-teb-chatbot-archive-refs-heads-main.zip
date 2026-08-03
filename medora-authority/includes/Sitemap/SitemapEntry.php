<?php

declare(strict_types=1);

namespace Medora\Authority\Sitemap;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One URL in an AI sitemap, carrying the annotations a plain XML sitemap
 * cannot express.
 *
 * A standard sitemap tells a crawler *that* a URL exists. An AI sitemap tells
 * it what the URL is about, how authoritative the site considers it and which
 * entities it covers — so a retriever can prioritise without fetching.
 */
final class SitemapEntry
{
    /** @param list<string> $entities */
    public function __construct(
        public readonly string $url,
        public readonly string $title = '',
        public readonly string $lastModified = '',
        public readonly float $priority = 0.5,
        public readonly string $changeFrequency = 'monthly',
        public readonly string $summary = '',
        public readonly array $entities = [],
        public readonly float $authorityScore = 0.0,
        public readonly string $entityType = '',
        public readonly int $objectId = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url'              => $this->url,
            'title'            => $this->title,
            'last_modified'    => $this->lastModified,
            'priority'         => round($this->priority, 2),
            'change_frequency' => $this->changeFrequency,
            'summary'          => $this->summary,
            'entities'         => $this->entities,
            'authority_score'  => $this->authorityScore,
            'entity_type'      => $this->entityType,
        ];
    }
}
