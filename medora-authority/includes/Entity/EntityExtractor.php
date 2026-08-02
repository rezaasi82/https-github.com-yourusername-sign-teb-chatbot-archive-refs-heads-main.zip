<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs every registered extractor over a post, merges the results and persists
 * the surviving entities with a salience score.
 *
 * Salience answers "how much is this page *about* this entity?" and is what
 * downstream consumers — schema, prompt packs, internal linking — rank on. It
 * blends four signals rather than relying on raw frequency, which would let a
 * stuffed keyword outrank the actual subject of the page.
 */
final class EntityExtractor
{
    /** @var list<ExtractorInterface> */
    private array $extractors = [];

    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function addExtractor(ExtractorInterface $extractor): void
    {
        $this->extractors[] = $extractor;

        usort($this->extractors, static fn (ExtractorInterface $a, ExtractorInterface $b): int => $b->priority() <=> $a->priority());
    }

    /** @return list<ExtractorInterface> */
    public function extractors(): array
    {
        return $this->extractors;
    }

    /**
     * Extract, persist and link every entity on a post.
     *
     * @return list<array{entity: Entity, salience: float, occurrences: int}>
     */
    public function indexPost(WP_Post $post): array
    {
        $plainText = Text::plain($post->post_content);
        $title     = $post->post_title;

        /** @var array<string, Candidate> $merged */
        $merged = [];

        foreach ($this->extractors as $extractor) {
            foreach ($extractor->extract($post, $plainText) as $candidate) {
                if (trim($candidate->entity->name) === '') {
                    continue;
                }

                $uid = $candidate->uid();

                $merged[$uid] = isset($merged[$uid])
                    ? $merged[$uid]->mergedWith($candidate)
                    : $candidate;
            }
        }

        /**
         * Filter the merged candidate set before it is written.
         *
         * @param array<string, Candidate> $merged
         * @param WP_Post                  $post
         */
        $merged = (array) apply_filters('medora_entity_candidates', $merged, $post);

        if ($merged === []) {
            $this->repository->unlinkObject('post', $post->ID);

            return [];
        }

        $totalOccurrences = max(1, array_sum(array_map(static fn (Candidate $c): int => $c->occurrences, $merged)));

        // Re-indexing replaces the previous link set so entities removed from
        // the content stop being attributed to it.
        $this->repository->unlinkObject('post', $post->ID);

        $results = [];

        foreach ($merged as $candidate) {
            $salience = $this->salience($candidate, $totalOccurrences, $title, $plainText);

            if ($salience < 0.02) {
                continue;
            }

            $stored = $this->repository->upsert($candidate->entity);

            $this->repository->link($stored->id, 'post', $post->ID, $candidate->occurrences, $salience);

            $results[] = [
                'entity'      => $stored,
                'salience'    => $salience,
                'occurrences' => $candidate->occurrences,
            ];
        }

        usort($results, static fn (array $a, array $b): int => $b['salience'] <=> $a['salience']);

        // The most salient entity is what the page is "about" — schema and the
        // prompt pack both key off this.
        if ($results !== []) {
            update_post_meta($post->ID, '_medora_primary_entity', $results[0]['entity']->uid);
        }

        /**
         * Fires after a post's entities have been indexed.
         *
         * @param WP_Post                                                        $post
         * @param list<array{entity: Entity, salience: float, occurrences: int}> $results
         */
        do_action('medora_entities_indexed', $post, $results);

        return $results;
    }

    /**
     * Weighted salience in 0..1.
     *
     * - frequency (35%): share of total entity mentions on the page
     * - title (30%): appearing in the title is the strongest single signal
     * - position (20%): entities introduced early are usually the subject
     * - confidence (15%): how much the extractor trusts the match
     */
    private function salience(Candidate $candidate, int $totalOccurrences, string $title, string $body): float
    {
        $name = Text::normalize($candidate->entity->name);

        $frequency = min(1.0, $candidate->occurrences / $totalOccurrences);

        $inTitle = str_contains(Text::normalize($title), $name) ? 1.0 : 0.0;

        $position    = 0.0;
        $normalized  = Text::normalize($body);
        $firstOffset = mb_strpos($normalized, $name, 0, 'UTF-8');

        if ($firstOffset !== false) {
            $length = max(1, mb_strlen($normalized, 'UTF-8'));
            // Linear decay: an entity first mentioned at 10% depth scores 0.9.
            $position = max(0.0, 1.0 - ($firstOffset / $length));
        }

        $salience = ($frequency * 0.35)
            + ($inTitle * 0.30)
            + ($position * 0.20)
            + ($candidate->confidence * 0.15);

        /**
         * Filter the computed salience for an entity on a page.
         *
         * @param float     $salience
         * @param Candidate $candidate
         */
        return (float) min(1.0, max(0.0, apply_filters('medora_entity_salience', $salience, $candidate)));
    }
}
