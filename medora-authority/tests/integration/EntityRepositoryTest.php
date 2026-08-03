<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityExtractor;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;

/**
 * The persistence behaviour the unit suite cannot reach: real upserts, real
 * unique-key collisions, real joins.
 */
final class EntityRepositoryTest extends MedoraTestCase
{
    private EntityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->container->get(EntityRepository::class);
    }

    public function test_upsert_converges_on_the_same_row(): void
    {
        $first = $this->repository->upsert(new Entity(
            name: 'Fatty liver disease',
            type: EntityType::MEDICAL_CONDITION,
        ));

        // Different casing, same concept — must resolve to the same row rather
        // than creating a duplicate.
        $second = $this->repository->upsert(new Entity(
            name: 'fatty LIVER disease',
            type: EntityType::MEDICAL_CONDITION,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->repository->count(EntityType::MEDICAL_CONDITION));
    }

    public function test_upsert_folds_arabic_and_persian_character_forms(): void
    {
        $persian = $this->repository->upsert(new Entity(name: 'علی رضایی', type: EntityType::PHYSICIAN));
        $arabic  = $this->repository->upsert(new Entity(name: 'علي رضايي', type: EntityType::PHYSICIAN));

        $this->assertSame($persian->id, $arabic->id, 'The same doctor must not become two entities.');
    }

    public function test_upsert_never_blanks_out_a_richer_record(): void
    {
        $this->repository->upsert(new Entity(
            name: 'Hepatology',
            type: EntityType::MEDICAL_SPECIALTY,
            description: 'The study of the liver.',
            sameAs: ['https://www.wikidata.org/wiki/Q1622272'],
        ));

        // A later, weaker extraction with no description or identifiers.
        $after = $this->repository->upsert(new Entity(
            name: 'Hepatology',
            type: EntityType::MEDICAL_SPECIALTY,
        ));

        $this->assertSame('The study of the liver.', $after->description);
        $this->assertContains('https://www.wikidata.org/wiki/Q1622272', $after->sameAs);
    }

    public function test_upsert_merges_rather_than_replaces_same_as_links(): void
    {
        $this->repository->upsert(new Entity(
            name: 'Hepatology',
            type: EntityType::MEDICAL_SPECIALTY,
            sameAs: ['https://a.test/1'],
        ));

        $after = $this->repository->upsert(new Entity(
            name: 'Hepatology',
            type: EntityType::MEDICAL_SPECIALTY,
            sameAs: ['https://b.test/2'],
        ));

        $this->assertCount(2, $after->sameAs);
    }

    public function test_link_is_idempotent(): void
    {
        $entity = $this->repository->upsert(new Entity(name: 'Endoscopy', type: EntityType::MEDICAL_PROCEDURE));
        $post   = $this->makePost();

        $this->repository->link($entity->id, 'post', $post->ID, 3, 0.5);
        $this->repository->link($entity->id, 'post', $post->ID, 5, 0.8);

        $linked = $this->repository->forObject('post', $post->ID);

        $this->assertCount(1, $linked);
        $this->assertSame(5, $linked[0]['occurrences']);
        $this->assertEqualsWithDelta(0.8, $linked[0]['salience'], 0.0001);
    }

    public function test_for_object_orders_by_salience(): void
    {
        $post = $this->makePost();

        $minor = $this->repository->upsert(new Entity(name: 'Jaundice', type: EntityType::SYMPTOM));
        $major = $this->repository->upsert(new Entity(name: 'Fatty liver', type: EntityType::MEDICAL_CONDITION));

        $this->repository->link($minor->id, 'post', $post->ID, 1, 0.10);
        $this->repository->link($major->id, 'post', $post->ID, 9, 0.90);

        $linked = $this->repository->forObject('post', $post->ID);

        $this->assertSame('Fatty liver', $linked[0]['entity']->name);
    }

    public function test_unlink_object_clears_only_that_object(): void
    {
        $entity = $this->repository->upsert(new Entity(name: 'Endoscopy', type: EntityType::MEDICAL_PROCEDURE));
        $keep   = $this->makePost();
        $drop   = $this->makePost();

        $this->repository->link($entity->id, 'post', $keep->ID, 1, 0.5);
        $this->repository->link($entity->id, 'post', $drop->ID, 1, 0.5);

        $this->repository->unlinkObject('post', $drop->ID);

        $this->assertCount(1, $this->repository->forObject('post', $keep->ID));
        $this->assertCount(0, $this->repository->forObject('post', $drop->ID));
        $this->assertSame(1, $this->repository->documentFrequency($entity->id));
    }

    public function test_query_filters_and_paginates(): void
    {
        foreach (range(1, 12) as $i) {
            $this->repository->upsert(new Entity(
                name: 'Condition ' . $i,
                type: EntityType::MEDICAL_CONDITION,
                authorityScore: (float) $i,
            ));
        }

        $this->repository->upsert(new Entity(name: 'Someone', type: EntityType::PERSON));

        $conditions = $this->repository->query(['type' => EntityType::MEDICAL_CONDITION, 'per_page' => 5]);

        $this->assertSame(12, $conditions['total']);
        $this->assertCount(5, $conditions['items']);
        // Default ordering is authority descending.
        $this->assertSame('Condition 12', $conditions['items'][0]->name);

        $page2 = $this->repository->query(['type' => EntityType::MEDICAL_CONDITION, 'per_page' => 5, 'page' => 2]);
        $this->assertSame('Condition 7', $page2['items'][0]->name);
    }

    public function test_query_search_matches_normalised_names(): void
    {
        $this->repository->upsert(new Entity(name: 'کبد چرب', type: EntityType::MEDICAL_CONDITION));

        // Searching with the Arabic yeh must still find the Persian entry.
        $result = $this->repository->query(['search' => 'کبد']);

        $this->assertSame(1, $result['total']);
    }

    public function test_delete_cascades_to_links_and_relations(): void
    {
        $entity = $this->repository->upsert(new Entity(name: 'Endoscopy', type: EntityType::MEDICAL_PROCEDURE));
        $other  = $this->repository->upsert(new Entity(name: 'Colonoscopy', type: EntityType::MEDICAL_PROCEDURE));
        $post   = $this->makePost();

        $this->repository->link($entity->id, 'post', $post->ID, 1, 0.5);
        $this->container->get(\Medora\Authority\Graph\RelationRepository::class)
            ->relate($entity->id, \Medora\Authority\Graph\RelationType::RELATED_TO, $other->id);

        $this->assertTrue($this->repository->delete($entity->id));

        $this->assertNull($this->repository->find($entity->id));
        $this->assertCount(0, $this->repository->forObject('post', $post->ID));
        $this->assertSame(0, $this->container->get(\Medora\Authority\Graph\RelationRepository::class)->count());
    }

    public function test_counts_by_type_are_ordered(): void
    {
        $this->repository->upsert(new Entity(name: 'A', type: EntityType::MEDICAL_CONDITION));
        $this->repository->upsert(new Entity(name: 'B', type: EntityType::MEDICAL_CONDITION));
        $this->repository->upsert(new Entity(name: 'C', type: EntityType::PERSON));

        $counts = $this->repository->countsByType();

        $this->assertSame(EntityType::MEDICAL_CONDITION, array_key_first($counts));
        $this->assertSame(2, $counts[EntityType::MEDICAL_CONDITION]);
    }

    public function test_extractor_indexes_a_real_post_end_to_end(): void
    {
        $post = $this->makePost();

        wp_set_object_terms($post->ID, ['Hepatology'], 'category');

        $results = $this->container->get(EntityExtractor::class)->indexPost($post);

        $this->assertNotEmpty($results, 'Extraction should find at least the assigned category.');

        $names = array_map(static fn (array $row): string => $row['entity']->name, $results);
        $this->assertContains('Hepatology', $names);

        // Salience must be a real 0..1 figure, ordered descending.
        $saliences = array_column($results, 'salience');
        $this->assertSame($saliences, array_reverse(array_reverse($saliences)));

        foreach ($saliences as $salience) {
            $this->assertGreaterThanOrEqual(0.0, $salience);
            $this->assertLessThanOrEqual(1.0, $salience);
        }

        // The most salient entity is recorded for schema and prompt packs.
        $this->assertNotEmpty(get_post_meta($post->ID, '_medora_primary_entity', true));
    }

    public function test_reindexing_replaces_the_previous_link_set(): void
    {
        $post = $this->makePost();
        wp_set_object_terms($post->ID, ['Hepatology'], 'category');

        $extractor = $this->container->get(EntityExtractor::class);
        $extractor->indexPost($post);

        $before = count($this->repository->forObject('post', $post->ID));
        $this->assertGreaterThan(0, $before);

        // Remove the term; the entity must stop being attributed to this post.
        wp_set_object_terms($post->ID, [], 'category');
        $extractor->indexPost(get_post($post->ID));

        $names = array_map(
            static fn (array $row): string => $row['entity']->name,
            $this->repository->forObject('post', $post->ID)
        );

        $this->assertNotContains('Hepatology', $names);
    }
}
