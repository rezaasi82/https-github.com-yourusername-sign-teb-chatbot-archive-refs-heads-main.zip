<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Graph\KnowledgeGraphBuilder;
use Medora\Authority\Graph\RelationRepository;
use Medora\Authority\Graph\RelationType;
use Medora\Authority\Medical\MedicalGraph;

/**
 * The curated clinical graph is published as machine-readable assertions, so
 * the two invariants that keep it honest are pinned here: it never claims
 * coverage the site does not have, and a statistical rebuild can never
 * overwrite a clinical fact.
 */
final class MedicalGraphTest extends MedoraTestCase
{
    private MedicalGraph $graph;
    private EntityRepository $entities;
    private RelationRepository $relations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container->get(Options::class)->set('site_mode', 'medical');

        $this->graph     = $this->container->get(MedicalGraph::class);
        $this->entities  = $this->container->get(EntityRepository::class);
        $this->relations = $this->container->get(RelationRepository::class);
    }

    private function coverConcepts(string ...$names): void
    {
        foreach ($names as $name) {
            $this->entities->upsert(new Entity(name: $name, type: EntityType::MEDICAL_CONDITION));
        }
    }

    public function test_seeding_an_empty_site_writes_nothing(): void
    {
        $result = $this->graph->seed();

        $this->assertSame(0, $result['written'], 'A site with no content must not claim clinical coverage.');
        $this->assertGreaterThan(0, $result['skipped_missing_entity']);
        $this->assertSame(0, $this->relations->count());
    }

    public function test_edges_appear_only_once_both_endpoints_are_covered(): void
    {
        // One endpoint only — still nothing to assert.
        $this->coverConcepts('Gallstones');
        $this->assertSame(0, $this->graph->seed()['written']);

        // Now both ends exist, so the curated edge becomes publishable.
        $this->entities->upsert(new Entity(name: 'Cholecystectomy', type: EntityType::MEDICAL_PROCEDURE));

        $this->assertGreaterThan(0, $this->graph->seed()['written']);

        $gallstones = $this->entities->findByName('Gallstones');
        $edges      = $this->relations->forEntity($gallstones->id);

        $predicates = array_column($edges, 'predicate');
        $this->assertContains(RelationType::TREATS, $predicates);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->coverConcepts('Gallstones');
        $this->entities->upsert(new Entity(name: 'Cholecystectomy', type: EntityType::MEDICAL_PROCEDURE));

        $first = $this->graph->seed()['written'];
        $count = $this->relations->count();

        $second = $this->graph->seed()['written'];

        $this->assertSame($first, $second);
        $this->assertSame($count, $this->relations->count(), 'Re-seeding must not duplicate edges.');
    }

    public function test_a_graph_rebuild_never_destroys_curated_clinical_edges(): void
    {
        $this->coverConcepts('Gallstones');
        $this->entities->upsert(new Entity(name: 'Cholecystectomy', type: EntityType::MEDICAL_PROCEDURE));

        $this->graph->seed();
        $curated = $this->relations->count();

        $this->assertGreaterThan(0, $curated);

        // The inferred layer is rebuilt nightly and deletes only its own rows.
        $this->container->get(KnowledgeGraphBuilder::class)->rebuild();

        $surviving = array_filter(
            $this->relations->all(),
            static fn (array $edge): bool => RelationType::isClinical($edge['predicate'])
        );

        $this->assertCount(
            $curated,
            $surviving,
            'A statistical rebuild must never overwrite a clinical assertion.'
        );
    }

    public function test_clearing_removes_only_the_curated_layer(): void
    {
        $this->coverConcepts('Gallstones');
        $this->entities->upsert(new Entity(name: 'Cholecystectomy', type: EntityType::MEDICAL_PROCEDURE));
        $this->graph->seed();

        // An unrelated inferred edge.
        $a = $this->entities->findByName('Gallstones');
        $b = $this->entities->upsert(new Entity(name: 'Something else', type: EntityType::MEDICAL_CONDITION));
        $this->relations->relate($a->id, RelationType::RELATED_TO, $b->id, 0.5, 'cooccurrence');

        $this->graph->clear();

        $remaining = $this->relations->all();

        $this->assertCount(1, $remaining);
        $this->assertSame(RelationType::RELATED_TO, $remaining[0]['predicate']);
    }

    public function test_profile_assembles_a_structured_clinical_picture(): void
    {
        foreach (
            [
                ['Fatty liver disease', EntityType::MEDICAL_CONDITION],
                ['Cirrhosis', EntityType::MEDICAL_CONDITION],
                ['Diabetes mellitus', EntityType::MEDICAL_CONDITION],
                ['Obesity', EntityType::MEDICAL_CONDITION],
                ['Fatigue', EntityType::SYMPTOM],
                ['Jaundice', EntityType::SYMPTOM],
                ['FibroScan', EntityType::MEDICAL_PROCEDURE],
                ['Liver biopsy', EntityType::MEDICAL_PROCEDURE],
                ['Hepatology', EntityType::MEDICAL_SPECIALTY],
                ['Liver', EntityType::ANATOMY],
            ] as [$name, $type]
        ) {
            $this->entities->upsert(new Entity(name: $name, type: $type));
        }

        $this->graph->seed();

        $profile = $this->graph->profileFor('Fatty liver disease');

        $this->assertNotNull($profile);
        $this->assertSame('Fatty liver disease', $profile['entity']['name']);

        $names = static fn (array $rows): array => array_column($rows, 'name');

        $this->assertContains('Fatigue', $names($profile['symptoms']));
        $this->assertContains('FibroScan', $names($profile['diagnostics']));
        $this->assertContains('Diabetes mellitus', $names($profile['risk_factors']));
        $this->assertContains('Hepatology', $names($profile['specialties']));
    }

    public function test_profile_resolves_a_persian_alias(): void
    {
        // Entities are keyed on the normalised name, so the Persian surface
        // form must resolve to the same profile.
        $this->entities->upsert(new Entity(name: 'کبد چرب', type: EntityType::MEDICAL_CONDITION));

        $this->assertNotNull($this->graph->profileFor('کبد چرب'));
        // Arabic character forms fold to Persian ones.
        $this->assertNotNull($this->graph->profileFor('كبد چرب'));
    }

    public function test_profile_for_an_uncovered_condition_is_null(): void
    {
        $this->assertNull($this->graph->profileFor('Something the site never mentions'));
    }

    public function test_coverage_report_separates_covered_concepts_from_gaps(): void
    {
        $this->coverConcepts('Fatty liver disease', 'Cirrhosis');

        $coverage = $this->graph->coverage();

        $this->assertCount(2, $coverage['covered']);
        $this->assertNotEmpty($coverage['gaps']);
        $this->assertGreaterThan(0.0, $coverage['coverage_percent']);
        $this->assertLessThan(100.0, $coverage['coverage_percent']);

        $gapNames = array_column($coverage['gaps'], 'name');
        $this->assertContains('Gallstones', $gapNames, 'An uncovered ontology term is a named content gap.');
    }

    public function test_module_reseeds_when_entities_are_indexed(): void
    {
        $this->entities->upsert(new Entity(name: 'Gallstones', type: EntityType::MEDICAL_CONDITION));
        $this->entities->upsert(new Entity(name: 'Cholecystectomy', type: EntityType::MEDICAL_PROCEDURE));

        $this->assertSame(0, $this->relations->count());

        // The medical module listens on this action so newly covered concepts
        // gain their clinical edges without manual intervention.
        do_action('medora_entities_indexed', $this->makePost(), []);

        $this->assertGreaterThan(0, $this->relations->count());
    }
}
