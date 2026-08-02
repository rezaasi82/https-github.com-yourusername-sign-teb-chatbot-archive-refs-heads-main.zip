<?php

declare(strict_types=1);

namespace Medora\Authority\Graph;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Derives graph edges from indexed entities.
 *
 * The core inference is co-occurrence: two entities that both appear, both
 * saliently, on the same page are related. Weighting by the *product* of their
 * saliences is what keeps this from degenerating into "everything relates to
 * everything" — a passing mention pairs weakly with anything, while two
 * subjects that a page genuinely covers together produce a strong edge.
 */
final class KnowledgeGraphBuilder
{
    private const SOURCE = 'cooccurrence';

    /** Below this, a pairing is noise rather than a relationship. */
    private const MIN_EDGE_WEIGHT = 0.06;

    /** Guards against the O(n²) blow-up on pages that mention dozens of entities. */
    private const MAX_ENTITIES_PER_POST = 20;

    public function __construct(
        private readonly EntityRepository $entities,
        private readonly RelationRepository $relations,
    ) {
    }

    /**
     * Build edges for a single post.
     *
     * @return int Number of edges written.
     */
    public function buildForPost(WP_Post $post): int
    {
        $indexed = $this->entities->forObject('post', $post->ID, self::MAX_ENTITIES_PER_POST);

        if (count($indexed) < 1) {
            return 0;
        }

        $written = 0;
        $people  = [];
        $topics  = [];

        foreach ($indexed as $row) {
            /** @var Entity $entity */
            $entity = $row['entity'];

            if (in_array($entity->type, [EntityType::PERSON, EntityType::PHYSICIAN], true)) {
                $people[] = $row;
                continue;
            }

            $topics[] = $row;
        }

        // Authorship edges: person --author--> subject.
        foreach ($people as $person) {
            foreach ($topics as $topic) {
                $this->relations->relate(
                    $person['entity']->id,
                    RelationType::AUTHORED_BY,
                    $topic['entity']->id,
                    (float) $topic['salience'],
                    self::SOURCE
                );
                $written++;
            }
        }

        // Co-occurrence edges between subjects.
        $count = count($topics);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $weight = (float) $topics[$i]['salience'] * (float) $topics[$j]['salience'];

                if ($weight < self::MIN_EDGE_WEIGHT) {
                    continue;
                }

                $predicate = $this->inferPredicate($topics[$i]['entity'], $topics[$j]['entity']);

                $this->relations->relate(
                    $topics[$i]['entity']->id,
                    $predicate,
                    $topics[$j]['entity']->id,
                    $weight,
                    self::SOURCE
                );
                $written++;
            }
        }

        return $written;
    }

    /**
     * Rebuild the inferred layer of the graph across the whole site.
     *
     * Only edges this builder created are removed first; manually curated
     * edges (source `manual`) survive a rebuild, which is what makes the
     * entity editor safe to use.
     *
     * @return array{posts: int, edges: int}
     */
    public function rebuild(int $batchSize = 200): array
    {
        $this->relations->deleteBySource(self::SOURCE);

        $paged = 1;
        $posts = 0;
        $edges = 0;

        do {
            $batch = get_posts([
                'post_type'        => 'any',
                'post_status'      => 'publish',
                'posts_per_page'   => $batchSize,
                'paged'            => $paged,
                'orderby'          => 'ID',
                'order'            => 'ASC',
                'suppress_filters' => false,
            ]);

            foreach ($batch as $post) {
                $edges += $this->buildForPost($post);
                $posts++;
            }

            $paged++;
        } while (count($batch) === $batchSize);

        do_action('medora_graph_rebuilt', $posts, $edges);

        return ['posts' => $posts, 'edges' => $edges];
    }

    /**
     * Pick the most specific predicate the two types justify.
     *
     * Falls back to `relatedTo` rather than guessing: a wrong predicate is
     * worse than a vague one, because it is machine-readable and will be
     * believed.
     */
    private function inferPredicate(Entity $a, Entity $b): string
    {
        $pair = [$a->type, $b->type];
        sort($pair);

        return match (true) {
            $pair === [EntityType::MEDICAL_CONDITION, EntityType::MEDICAL_PROCEDURE] => RelationType::TREATS,
            $pair === [EntityType::MEDICAL_CONDITION, EntityType::SYMPTOM]           => RelationType::SYMPTOM_OF,
            in_array(EntityType::PLACE, $pair, true)                                 => RelationType::LOCATED_IN,
            in_array(EntityType::SERVICE, $pair, true)                               => RelationType::PROVIDES,
            default                                                                  => RelationType::RELATED_TO,
        };
    }
}
