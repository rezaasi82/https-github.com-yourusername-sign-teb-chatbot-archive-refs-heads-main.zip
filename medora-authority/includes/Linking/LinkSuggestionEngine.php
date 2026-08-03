<?php

declare(strict_types=1);

namespace Medora\Authority\Linking;

use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Support\Text;
use Medora\Authority\Vector\VectorIndex;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Suggests internal links from semantic similarity and shared entities.
 *
 * Explicitly *not* keyword-matching. The two signals used are:
 *
 * 1. **Vector similarity** — the target page is about a genuinely related
 *    subject, even when it uses different words.
 * 2. **Shared entities** — the two pages cover the same thing, which gives a
 *    natural, honest anchor phrase.
 *
 * Anchor text is always an entity name that actually occurs in the source
 * page's text. Suggesting an anchor that is not present would push the editor
 * toward inserting a phrase for the link's sake — which is the exact
 * keyword-stuffing behaviour this is meant to replace.
 */
final class LinkSuggestionEngine
{
    private const MIN_SIMILARITY = 0.28;

    public function __construct(
        private readonly VectorIndex $vectors,
        private readonly EntityRepository $entities,
    ) {
    }

    /**
     * @return list<array{
     *     target_id: int,
     *     title: string,
     *     url: string,
     *     score: float,
     *     reason: string,
     *     anchors: list<string>,
     *     already_linked: bool
     * }>
     */
    public function suggestFor(WP_Post $post, int $limit = 8): array
    {
        $related      = $this->vectors->related($post->ID, $limit * 2, self::MIN_SIMILARITY);
        $sourceEntities = $this->entityNames($post->ID);
        $existing     = $this->existingLinks($post->post_content);
        $normalized   = Text::normalize(Text::plain($post->post_content));

        $suggestions = [];

        foreach ($related as $match) {
            $targetId = $match['object_id'];
            $shared   = array_intersect($sourceEntities, $this->entityNames($targetId));

            // Anchors must be phrases the source page already contains.
            $anchors = [];

            foreach ($shared as $name) {
                if (str_contains($normalized, Text::normalize($name))) {
                    $anchors[] = $name;
                }
            }

            $suggestions[] = [
                'target_id'      => $targetId,
                'title'          => $match['title'],
                'url'            => $match['url'],
                'score'          => $match['score'],
                'reason'         => $shared === []
                    ? __('Semantically related subject.', 'medora-authority')
                    : sprintf(
                        /* translators: %s: comma-separated entity names. */
                        __('Both pages cover: %s', 'medora-authority'),
                        implode('، ', array_slice($shared, 0, 3))
                    ),
                'anchors'        => array_slice(array_values($anchors), 0, 3),
                'already_linked' => in_array($targetId, $existing, true),
            ];
        }

        // Pages already linked stay in the list but sink, so an editor can see
        // the relationship exists without being told to add it twice.
        usort($suggestions, static function (array $a, array $b): int {
            if ($a['already_linked'] !== $b['already_linked']) {
                return $a['already_linked'] ? 1 : -1;
            }

            // Suggestions with a usable anchor are actionable immediately.
            $anchorDiff = (count($b['anchors']) > 0 ? 1 : 0) <=> (count($a['anchors']) > 0 ? 1 : 0);

            return $anchorDiff !== 0 ? $anchorDiff : ($b['score'] <=> $a['score']);
        });

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Pages that *should* link to this one — the inbound half, which is what
     * actually moves authority toward a page you are trying to rank.
     *
     * @return list<array{source_id: int, title: string, url: string, score: float}>
     */
    public function inboundOpportunities(WP_Post $post, int $limit = 8): array
    {
        $candidates  = $this->vectors->related($post->ID, $limit * 3, self::MIN_SIMILARITY);
        $permalink   = (string) get_permalink($post);
        $opportunities = [];

        foreach ($candidates as $candidate) {
            $source = get_post($candidate['object_id']);

            if (! $source instanceof WP_Post) {
                continue;
            }

            if (str_contains($source->post_content, $permalink)) {
                continue;
            }

            $opportunities[] = [
                'source_id' => $source->ID,
                'title'     => get_the_title($source),
                'url'       => (string) get_permalink($source),
                'score'     => $candidate['score'],
            ];
        }

        return array_slice($opportunities, 0, $limit);
    }

    /** @return list<string> */
    private function entityNames(int $postId): array
    {
        $names = [];

        foreach ($this->entities->forObject('post', $postId, 15) as $row) {
            if ($row['salience'] >= 0.15) {
                $names[] = $row['entity']->name;
            }
        }

        return $names;
    }

    /** @return list<int> post ids already linked from this content */
    private function existingLinks(string $content): array
    {
        if (preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\']#i', $content, $matches) === 0) {
            return [];
        }

        $ids = [];

        foreach ($matches[1] as $href) {
            $id = url_to_postid($href);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
