<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

use Medora\Authority\Support\Cache;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves a DOI or PubMed id into full bibliographic metadata.
 *
 * Both APIs are public, keyless and rate-limited by politeness rather than by
 * quota, so responses are cached for a month — bibliographic records for
 * published papers do not change.
 */
final class CrossRefResolver
{
    private const CACHE_TTL = MONTH_IN_SECONDS;

    public function __construct(private readonly Cache $cache)
    {
    }

    /**
     * Accepts a raw DOI, a doi.org URL, a PMID or a `PMID:12345` string.
     */
    public function resolve(string $identifier): ?Citation
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $doi  = $this->extractDoi($identifier);
        $pmid = $doi === '' ? $this->extractPmid($identifier) : '';

        if ($doi !== '') {
            return $this->cache->remember('doi_' . md5($doi), self::CACHE_TTL, fn (): ?Citation => $this->fromCrossRef($doi));
        }

        if ($pmid !== '') {
            return $this->cache->remember('pmid_' . $pmid, self::CACHE_TTL, fn (): ?Citation => $this->fromPubMed($pmid));
        }

        return null;
    }

    private function fromCrossRef(string $doi): ?Citation
    {
        $response = wp_remote_get(
            'https://api.crossref.org/works/' . rawurlencode($doi),
            [
                'timeout' => 15,
                // CrossRef asks for a contact address in the UA and gives
                // requests that include one priority routing.
                'headers' => ['User-Agent' => $this->userAgent()],
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $work = is_array($body) ? ($body['message'] ?? null) : null;

        if (! is_array($work)) {
            return null;
        }

        $authors = [];

        foreach ((array) ($work['author'] ?? []) as $author) {
            $name = trim(((string) ($author['given'] ?? '')) . ' ' . ((string) ($author['family'] ?? '')));

            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $year = (int) (($work['issued']['date-parts'][0][0] ?? 0));

        return new Citation(
            title: (string) (($work['title'][0] ?? '')),
            authors: $authors,
            container: (string) (($work['container-title'][0] ?? '')),
            year: $year,
            doi: (string) ($work['DOI'] ?? $doi),
            url: (string) ($work['URL'] ?? ''),
            evidenceLevel: EvidenceLevel::fromPublicationType((array) ($work['type'] ?? [])),
            qualityScore: (new CitationQualityScorer())->score(
                year: $year,
                hasDoi: true,
                container: (string) (($work['container-title'][0] ?? '')),
                citedBy: (int) ($work['is-referenced-by-count'] ?? 0)
            ),
        );
    }

    private function fromPubMed(string $pmid): ?Citation
    {
        $response = wp_remote_get(
            add_query_arg(
                ['db' => 'pubmed', 'id' => $pmid, 'retmode' => 'json'],
                'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi'
            ),
            ['timeout' => 15, 'headers' => ['User-Agent' => $this->userAgent()]]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body   = json_decode((string) wp_remote_retrieve_body($response), true);
        $record = is_array($body) ? ($body['result'][$pmid] ?? null) : null;

        if (! is_array($record)) {
            return null;
        }

        $authors = [];

        foreach ((array) ($record['authors'] ?? []) as $author) {
            $name = (string) ($author['name'] ?? '');

            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $year      = (int) substr((string) ($record['pubdate'] ?? ''), 0, 4);
        $container = (string) ($record['fulljournalname'] ?? $record['source'] ?? '');
        $types     = array_map('strval', (array) ($record['pubtype'] ?? []));

        return new Citation(
            title: (string) ($record['title'] ?? ''),
            authors: $authors,
            container: $container,
            year: $year,
            doi: (string) ($record['elocationid'] ?? ''),
            pmid: $pmid,
            url: 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/',
            evidenceLevel: EvidenceLevel::fromPublicationType($types),
            qualityScore: (new CitationQualityScorer())->score(
                year: $year,
                hasDoi: (string) ($record['elocationid'] ?? '') !== '',
                container: $container,
                citedBy: 0,
                evidenceLevel: EvidenceLevel::fromPublicationType($types)
            ),
        );
    }

    private function extractDoi(string $value): string
    {
        // DOIs always start with "10." followed by a registrant code.
        if (preg_match('#\b(10\.\d{4,9}/[-._;()/:a-z0-9]+)\b#i', $value, $match) === 1) {
            return rtrim($match[1], '.');
        }

        return '';
    }

    private function extractPmid(string $value): string
    {
        if (preg_match('/\bpmid:?\s*(\d{4,9})\b/i', $value, $match) === 1) {
            return $match[1];
        }

        if (preg_match('#pubmed\.ncbi\.nlm\.nih\.gov/(\d+)#i', $value, $match) === 1) {
            return $match[1];
        }

        return preg_match('/^\d{4,9}$/', $value) === 1 ? $value : '';
    }

    private function userAgent(): string
    {
        return sprintf(
            'MedoraAuthority/%s (+%s; mailto:%s)',
            MEDORA_VERSION,
            home_url('/'),
            (string) get_option('admin_email')
        );
    }
}
