<?php

declare(strict_types=1);

namespace Medora\Authority\Medical;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Graph\RelationRepository;
use Medora\Authority\Graph\RelationType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Seeds the curated clinical graph — disease, treatment, drug, symptom.
 *
 * Two rules make this safe to publish:
 *
 * 1. **Only terms the site actually covers get edges.** Seeding the whole
 *    ontology would claim expertise the site does not have; a clinic that never
 *    writes about hepatitis should not appear in the graph as a hepatitis
 *    source. An edge is written only when *both* endpoints already exist as
 *    entities, which happens when content mentions them.
 * 2. **Curated edges are written under `source = 'ontology'`.** The inferred
 *    co-occurrence layer is rebuilt nightly and deletes only its own rows, so a
 *    rebuild can never overwrite a clinical assertion with a statistical guess.
 */
final class MedicalGraph
{
    public const SOURCE = 'ontology';

    /** Curated edges are asserted, not inferred, so they carry full weight. */
    private const WEIGHT = 1.0;

    public function __construct(
        private readonly MedicalOntology $ontology,
        private readonly EntityRepository $entities,
        private readonly RelationRepository $relations,
    ) {
    }

    /**
     * Write every clinical edge whose endpoints the site already covers.
     *
     * @return array{written: int, skipped_missing_entity: int, unresolved_terms: int}
     */
    public function seed(): array
    {
        $validated = $this->ontology->validatedRelations();

        $written = 0;
        $skipped = 0;

        foreach ($validated['valid'] as $relation) {
            $subject = $this->resolve((string) $relation['subject']);
            $object  = $this->resolve((string) $relation['object']);

            if ($subject === null || $object === null) {
                // One or both concepts are not covered by this site's content.
                $skipped++;
                continue;
            }

            $this->relations->relate(
                $subject->id,
                (string) $relation['predicate'],
                $object->id,
                (float) ($relation['weight'] ?? self::WEIGHT),
                self::SOURCE
            );

            $written++;
        }

        $result = [
            'written'                => $written,
            'skipped_missing_entity' => $skipped,
            'unresolved_terms'       => count($validated['unresolved']),
        ];

        /**
         * Fires after the clinical graph has been seeded.
         *
         * @param array{written: int, skipped_missing_entity: int, unresolved_terms: int} $result
         */
        do_action('medora_medical_graph_seeded', $result);

        return $result;
    }

    /**
     * Everything the site knows about one condition, assembled from the graph.
     *
     * This is what an answer engine asking "what do you know about X" should
     * receive: not a page, but a structured clinical picture.
     *
     * @return array{
     *     entity: array<string, mixed>,
     *     symptoms: list<array<string, mixed>>,
     *     treatments: list<array<string, mixed>>,
     *     diagnostics: list<array<string, mixed>>,
     *     risk_factors: list<array<string, mixed>>,
     *     specialties: list<array<string, mixed>>,
     *     related: list<array<string, mixed>>
     * }|null
     */
    public function profileFor(string $conditionName): ?array
    {
        $entity = $this->entities->findByName($conditionName);

        if ($entity === null) {
            return null;
        }

        $buckets = [
            'symptoms'     => [],
            'treatments'   => [],
            'diagnostics'  => [],
            'risk_factors' => [],
            'specialties'  => [],
            'related'      => [],
        ];

        foreach ($this->relations->forEntity($entity->id, 100) as $edge) {
            $other = $this->entities->find($edge['other_id']);

            if ($other === null) {
                continue;
            }

            // The clinical predicates are directional, so which bucket an edge
            // lands in depends on which end of it this entity sits at.
            $bucket = match (true) {
                $edge['predicate'] === RelationType::SYMPTOM_OF && $edge['direction'] === 'incoming' => 'symptoms',
                $edge['predicate'] === RelationType::TREATS && $edge['direction'] === 'incoming'     => 'treatments',
                $edge['predicate'] === RelationType::DIAGNOSED_BY && $edge['direction'] === 'incoming' => 'diagnostics',
                $edge['predicate'] === RelationType::RISK_FACTOR && $edge['direction'] === 'incoming'  => 'risk_factors',
                $edge['predicate'] === RelationType::SPECIALTY_OF && $edge['direction'] === 'outgoing' => 'specialties',
                default => 'related',
            };

            $buckets[$bucket][] = $other->toArray() + [
                'weight'    => $edge['weight'],
                'curated'   => RelationType::isClinical($edge['predicate']),
                'predicate' => $edge['predicate'],
            ];
        }

        return ['entity' => $entity->toArray()] + $buckets;
    }

    /**
     * Coverage report: which curated concepts the site does and does not cover.
     *
     * This is the actionable half — an uncovered condition that the site's
     * specialty implies it should cover is a content gap with a name.
     *
     * @return array{
     *     covered: list<array{name: string, type: string, authority: float}>,
     *     gaps: list<array{name: string, type: string}>,
     *     coverage_percent: float
     * }
     */
    public function coverage(): array
    {
        $covered = [];
        $gaps    = [];

        foreach ($this->ontology->terms() as $name => $definition) {
            $entity = $this->resolve((string) $name);

            if ($entity === null) {
                $gaps[] = ['name' => (string) $name, 'type' => (string) $definition['type']];
                continue;
            }

            $covered[] = [
                'name'      => $entity->name,
                'type'      => $entity->type,
                'authority' => $entity->authorityScore,
            ];
        }

        $total = count($covered) + count($gaps);

        usort($covered, static fn (array $a, array $b): int => $b['authority'] <=> $a['authority']);

        return [
            'covered'          => $covered,
            'gaps'             => $gaps,
            'coverage_percent' => $total > 0 ? round((count($covered) / $total) * 100, 1) : 0.0,
        ];
    }

    /** Remove every curated edge, e.g. before re-seeding a changed ontology. */
    public function clear(): int
    {
        return $this->relations->deleteBySource(self::SOURCE);
    }

    private function resolve(string $name): ?Entity
    {
        // Type is left unconstrained: the same concept may have been created by
        // the taxonomy extractor with a site-specific type, and refusing to
        // match on that basis would silently drop half the curated graph.
        return $this->entities->findByName($name);
    }
}
