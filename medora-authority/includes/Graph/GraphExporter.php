<?php

declare(strict_types=1);

namespace Medora\Authority\Graph;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Serialises the knowledge graph for machines and for the dashboard.
 *
 * Two shapes, one source:
 *
 * - **JSON-LD** for AI systems. Emitted as a `@graph` of Schema.org nodes with
 *   stable `@id`s, so a crawler can dereference and re-fetch the same entity.
 * - **Nodes/links** for the D3-style force graph in the React dashboard.
 */
final class GraphExporter
{
    public function __construct(
        private readonly EntityRepository $entities,
        private readonly RelationRepository $relations,
    ) {
    }

    /**
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    public function toJsonLd(int $limit = 1000): array
    {
        $result = $this->entities->query(['per_page' => min(200, $limit), 'orderby' => 'authority']);
        $byId   = [];

        foreach ($result['items'] as $entity) {
            $byId[$entity->id] = $entity;
        }

        $nodes = [];

        foreach ($byId as $entity) {
            $node = $entity->toSchemaNode();

            foreach ($this->relations->forEntity($entity->id, 25) as $edge) {
                $other = $byId[$edge['other_id']] ?? $this->entities->find($edge['other_id']);

                if (! $other instanceof Entity) {
                    continue;
                }

                $property = $edge['predicate'];

                // Schema.org properties are single- or multi-valued depending
                // on the property; always emitting arrays would be invalid, so
                // the first value is scalar and later ones promote to a list.
                $reference = ['@id' => $other->schemaId()];

                if (! isset($node[$property])) {
                    $node[$property] = $reference;
                } elseif (isset($node[$property]['@id'])) {
                    $node[$property] = [$node[$property], $reference];
                } else {
                    $node[$property][] = $reference;
                }
            }

            $nodes[] = $node;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => $nodes,
        ];
    }

    /**
     * @return array{
     *     nodes: list<array{id: int, uid: string, label: string, type: string, score: float, degree: int, url: string}>,
     *     links: list<array{source: int, target: int, predicate: string, weight: float}>,
     *     stats: array{entities: int, relations: int}
     * }
     */
    public function toVisualisation(int $limit = 300): array
    {
        $degrees = [];

        foreach ($this->relations->degrees($limit) as $row) {
            $degrees[$row['entity_id']] = $row['degree'];
        }

        $result = $this->entities->query(['per_page' => min(200, $limit), 'orderby' => 'authority']);
        $nodes  = [];
        $ids    = [];

        foreach ($result['items'] as $entity) {
            $ids[$entity->id] = true;

            $nodes[] = [
                'id'     => $entity->id,
                'uid'    => $entity->uid,
                'label'  => $entity->name,
                'type'   => $entity->type,
                'score'  => $entity->authorityScore,
                'degree' => $degrees[$entity->id] ?? 0,
                'url'    => $entity->permalink,
            ];
        }

        $links = [];

        foreach ($this->relations->all($limit * 6) as $edge) {
            // Drop edges pointing outside the exported node set, otherwise the
            // force layout renders orphaned links.
            if (! isset($ids[$edge['subject_id']], $ids[$edge['object_id']])) {
                continue;
            }

            $links[] = [
                'source'    => $edge['subject_id'],
                'target'    => $edge['object_id'],
                'predicate' => $edge['predicate'],
                'weight'    => $edge['weight'],
            ];
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            'stats' => [
                'entities'  => $this->entities->count(),
                'relations' => $this->relations->count(),
            ],
        ];
    }
}
