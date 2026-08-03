<?php

declare(strict_types=1);

namespace Medora\Authority\Sitemap;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Serialises sitemap entries to XML, JSON or Markdown.
 *
 * XML for classic crawlers, JSON for programmatic consumers, Markdown because
 * several AI crawlers handle it better than XML and it is what a human reviewing
 * the output actually wants to read.
 */
final class SitemapRenderer
{
    public const FORMAT_XML      = 'xml';
    public const FORMAT_JSON     = 'json';
    public const FORMAT_MARKDOWN = 'md';

    /** @return array<string, string> format => MIME type */
    public static function contentTypes(): array
    {
        return [
            self::FORMAT_XML      => 'application/xml; charset=utf-8',
            self::FORMAT_JSON     => 'application/json; charset=utf-8',
            self::FORMAT_MARKDOWN => 'text/markdown; charset=utf-8',
        ];
    }

    /** @param list<SitemapEntry> $entries */
    public function render(array $entries, string $format, string $type): string
    {
        return match ($format) {
            self::FORMAT_JSON     => $this->json($entries, $type),
            self::FORMAT_MARKDOWN => $this->markdown($entries, $type),
            default               => $this->xml($entries),
        };
    }

    /** @param list<SitemapEntry> $entries */
    private function xml(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"',
            '        xmlns:medora="https://medora.ai/schema/sitemap/1.0">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . esc_url($entry->url) . '</loc>';

            if ($entry->lastModified !== '') {
                $lines[] = '    <lastmod>' . esc_html($entry->lastModified) . '</lastmod>';
            }

            $lines[] = '    <changefreq>' . esc_html($entry->changeFrequency) . '</changefreq>';
            $lines[] = '    <priority>' . esc_html(number_format($entry->priority, 2, '.', '')) . '</priority>';

            // Namespaced extensions: standards-compliant consumers ignore what
            // they do not recognise, so this stays a valid sitemap.
            if ($entry->summary !== '') {
                $lines[] = '    <medora:summary>' . esc_html($entry->summary) . '</medora:summary>';
            }

            if ($entry->authorityScore > 0) {
                $lines[] = '    <medora:authorityScore>' . esc_html((string) $entry->authorityScore) . '</medora:authorityScore>';
            }

            if ($entry->entityType !== '') {
                $lines[] = '    <medora:entityType>' . esc_html($entry->entityType) . '</medora:entityType>';
            }

            foreach ($entry->entities as $entity) {
                $lines[] = '    <medora:entity>' . esc_html($entity) . '</medora:entity>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }

    /** @param list<SitemapEntry> $entries */
    private function json(array $entries, string $type): string
    {
        return (string) wp_json_encode(
            [
                'version'     => '1.0',
                'type'        => $type,
                'site'        => home_url('/'),
                'generated'   => gmdate('c'),
                'entry_count' => count($entries),
                'entries'     => array_map(static fn (SitemapEntry $e): array => $e->toArray(), $entries),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    /** @param list<SitemapEntry> $entries */
    private function markdown(array $entries, string $type): string
    {
        $lines = [
            sprintf('# %s — %s sitemap', get_bloginfo('name'), $type),
            '',
            sprintf('%d URLs. Generated %s.', count($entries), gmdate('c')),
            '',
        ];

        // Highest authority first: a model reading top-down meets the strongest
        // pages before its context budget runs out.
        $sorted = $entries;
        usort($sorted, static fn (SitemapEntry $a, SitemapEntry $b): int => $b->priority <=> $a->priority);

        foreach ($sorted as $entry) {
            $title = $entry->title !== '' ? $entry->title : $entry->url;

            $lines[] = sprintf('- [%s](%s)', str_replace([']', '['], '', $title), $entry->url);

            if ($entry->summary !== '') {
                $lines[] = '  ' . $entry->summary;
            }

            if ($entry->entities !== []) {
                $lines[] = '  Entities: ' . implode(', ', array_slice($entry->entities, 0, 6));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The index that points at each variant.
     *
     * @param list<string> $types
     */
    public function index(array $types): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($types as $type) {
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>' . esc_url(home_url('/medora-sitemap-' . $type . '.xml')) . '</loc>';
            $lines[] = '    <lastmod>' . esc_html(gmdate('c')) . '</lastmod>';
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode("\n", $lines);
    }
}
