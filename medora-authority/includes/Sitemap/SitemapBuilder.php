<?php

declare(strict_types=1);

namespace Medora\Authority\Sitemap;

use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Score\AnalysisRepository;
use Medora\Authority\Support\Cache;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Collects the entries for each sitemap variant.
 *
 * Priority is computed from the AI Authority Score rather than hardcoded per
 * post type. That inverts the usual convention deliberately: a crawler with a
 * limited budget should be pointed at the pages most worth citing, not at
 * whichever post type an author declared important.
 */
final class SitemapBuilder
{
    public const TYPE_CONTENT   = 'content';
    public const TYPE_ENTITY    = 'entity';
    public const TYPE_KNOWLEDGE = 'knowledge';

    private const CACHE_TTL = 3 * HOUR_IN_SECONDS;
    private const PER_PAGE  = 500;

    public function __construct(
        private readonly EntityRepository $entities,
        private readonly AnalysisRepository $analysis,
        private readonly Cache $cache,
    ) {
    }

    /** @return list<string> */
    public function availableTypes(): array
    {
        return [self::TYPE_CONTENT, self::TYPE_ENTITY, self::TYPE_KNOWLEDGE];
    }

    /**
     * @return list<SitemapEntry>
     */
    public function entries(string $type): array
    {
        return $this->cache->remember(
            'sitemap_' . $type,
            self::CACHE_TTL,
            fn (): array => match ($type) {
                self::TYPE_ENTITY    => $this->entityEntries(),
                self::TYPE_KNOWLEDGE => $this->knowledgeEntries(),
                default              => $this->contentEntries(),
            }
        );
    }

    public function flush(): void
    {
        foreach ($this->availableTypes() as $type) {
            $this->cache->forget('sitemap_' . $type);
        }

        $this->cache->forget('sitemap_index');
    }

    /**
     * Every indexable page, annotated.
     *
     * @return list<SitemapEntry>
     */
    private function contentEntries(): array
    {
        $postTypes = array_values(get_post_types(['public' => true], 'names'));
        unset($postTypes[array_search('attachment', $postTypes, true)]);

        $posts = get_posts([
            'post_type'        => array_values($postTypes),
            'post_status'      => 'publish',
            'posts_per_page'   => self::PER_PAGE,
            'orderby'          => 'modified',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        $entries = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post || get_post_meta($post->ID, '_medora_noindex', true) === '1') {
                continue;
            }

            $entries[] = $this->entryForPost($post);
        }

        return $entries;
    }

    private function entryForPost(WP_Post $post): SitemapEntry
    {
        $analysis = $this->analysis->find('post', $post->ID);
        $score    = $analysis === null ? 0.0 : (float) $analysis['result']['overall'];
        $indexed  = $this->entities->forObject('post', $post->ID, 8);

        return new SitemapEntry(
            url: (string) get_permalink($post),
            title: get_the_title($post),
            lastModified: (string) get_post_modified_time('c', true, $post),
            // Score-derived, clamped to the 0.1–1.0 range the spec allows. An
            // unanalysed page defaults to 0.5 rather than being buried.
            priority: $score > 0 ? max(0.1, min(1.0, $score / 100)) : 0.5,
            changeFrequency: $this->changeFrequency($post),
            summary: Text::truncate((string) get_post_meta($post->ID, '_medora_ai_summary', true), 200),
            entities: array_map(static fn (array $row): string => $row['entity']->name, $indexed),
            authorityScore: $score,
            entityType: $indexed === [] ? '' : $indexed[0]['entity']->type,
            objectId: $post->ID,
        );
    }

    /**
     * Entity landing pages — the term archives where a subject is treated as a
     * first-class thing rather than mentioned in passing.
     *
     * @return list<SitemapEntry>
     */
    private function entityEntries(): array
    {
        $result  = $this->entities->query(['per_page' => 200, 'orderby' => 'authority']);
        $entries = [];

        foreach ($result['items'] as $entity) {
            if ($entity->permalink === '' || $entity->authorityScore < 10) {
                continue;
            }

            $entries[] = new SitemapEntry(
                url: $entity->permalink,
                title: $entity->name,
                priority: max(0.1, min(1.0, $entity->authorityScore / 100)),
                changeFrequency: 'weekly',
                summary: Text::truncate($entity->description, 200),
                authorityScore: $entity->authorityScore,
                entityType: $entity->type,
            );
        }

        return $entries;
    }

    /**
     * The machine-readable endpoints themselves, so a crawler that finds one
     * sitemap discovers the whole knowledge surface.
     *
     * @return list<SitemapEntry>
     */
    private function knowledgeEntries(): array
    {
        $now = gmdate('c');

        return [
            new SitemapEntry(
                url: home_url('/llms.txt'),
                title: __('LLM site guide', 'medora-authority'),
                lastModified: $now,
                priority: 1.0,
                changeFrequency: 'daily',
                summary: __('Curated Markdown map of this site for language models.', 'medora-authority'),
            ),
            new SitemapEntry(
                url: home_url('/llms-full.txt'),
                title: __('LLM full content bundle', 'medora-authority'),
                lastModified: $now,
                priority: 0.9,
                changeFrequency: 'daily',
                summary: __('Every indexed page inlined as Markdown.', 'medora-authority'),
            ),
            new SitemapEntry(
                url: rest_url('medora/v1/graph'),
                title: __('Knowledge graph (JSON-LD)', 'medora-authority'),
                lastModified: $now,
                priority: 0.9,
                changeFrequency: 'daily',
                summary: __('Entities and their relationships as Schema.org JSON-LD.', 'medora-authority'),
            ),
            new SitemapEntry(
                url: rest_url('medora/v1/entities'),
                title: __('Entity index (JSON)', 'medora-authority'),
                lastModified: $now,
                priority: 0.8,
                changeFrequency: 'daily',
                summary: __('Every entity this site covers, with authority scores.', 'medora-authority'),
            ),
        ];
    }

    /**
     * Inferred from how often the page has actually changed, not declared.
     */
    private function changeFrequency(WP_Post $post): string
    {
        $ageDays = (int) floor((time() - (int) get_post_modified_time('U', true, $post)) / DAY_IN_SECONDS);

        return match (true) {
            $ageDays <= 7   => 'daily',
            $ageDays <= 30  => 'weekly',
            $ageDays <= 180 => 'monthly',
            default         => 'yearly',
        };
    }
}
