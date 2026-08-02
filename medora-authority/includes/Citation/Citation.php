<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A bibliographic reference attached to a piece of content.
 */
final class Citation
{
    /** @param list<string> $authors */
    public function __construct(
        public readonly string $title,
        public readonly array $authors = [],
        public readonly string $container = '',
        public readonly int $year = 0,
        public readonly string $doi = '',
        public readonly string $pmid = '',
        public readonly string $url = '',
        public readonly string $evidenceLevel = '',
        public readonly float $qualityScore = 0.0,
        public readonly int $id = 0,
        public readonly int $objectId = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            title: (string) ($row['title'] ?? ''),
            authors: array_values(array_map('strval', Arr::fromJson($row['authors'] ?? null))),
            container: (string) ($row['container'] ?? ''),
            year: (int) ($row['published_year'] ?? 0),
            doi: (string) ($row['doi'] ?? ''),
            pmid: (string) ($row['pmid'] ?? ''),
            url: (string) ($row['url'] ?? ''),
            evidenceLevel: (string) ($row['evidence_level'] ?? ''),
            qualityScore: (float) ($row['quality_score'] ?? 0),
            id: (int) ($row['id'] ?? 0),
            objectId: (int) ($row['object_id'] ?? 0),
        );
    }

    public function canonicalUrl(): string
    {
        if ($this->doi !== '') {
            return 'https://doi.org/' . ltrim($this->doi, '/');
        }

        if ($this->pmid !== '') {
            return 'https://pubmed.ncbi.nlm.nih.gov/' . $this->pmid . '/';
        }

        return $this->url;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'authors'        => $this->authors,
            'container'      => $this->container,
            'year'           => $this->year,
            'doi'            => $this->doi,
            'pmid'           => $this->pmid,
            'url'            => $this->canonicalUrl(),
            'evidence_level' => $this->evidenceLevel,
            'quality_score'  => $this->qualityScore,
        ];
    }

    /** Schema.org node, so citations travel with the article's JSON-LD. */
    public function toSchemaNode(): array
    {
        return Arr::compact([
            '@type'           => 'ScholarlyArticle',
            'name'            => $this->title,
            'author'          => array_map(
                static fn (string $name): array => ['@type' => 'Person', 'name' => $name],
                $this->authors
            ),
            'isPartOf'        => $this->container === '' ? null : ['@type' => 'Periodical', 'name' => $this->container],
            'datePublished'   => $this->year > 0 ? (string) $this->year : '',
            'identifier'      => $this->doi !== '' ? 'doi:' . $this->doi : ($this->pmid !== '' ? 'pmid:' . $this->pmid : ''),
            'url'             => $this->canonicalUrl(),
        ]);
    }
}
