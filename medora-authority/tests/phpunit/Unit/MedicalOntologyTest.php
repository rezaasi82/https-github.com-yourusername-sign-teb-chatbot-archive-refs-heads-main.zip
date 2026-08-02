<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Entity\EntityType;
use Medora\Authority\Graph\RelationType;
use Medora\Authority\Medical\MedicalOntology;
use Medora\Authority\Support\Text;
use PHPUnit\Framework\TestCase;

/**
 * The clinical vocabulary is published as machine-readable assertions, so an
 * internal inconsistency here becomes misinformation rather than a bug. These
 * tests are the guard on that.
 */
final class MedicalOntologyTest extends TestCase
{
    private MedicalOntology $ontology;

    protected function setUp(): void
    {
        $this->ontology = new MedicalOntology();
    }

    public function testEveryTermIsWellFormed(): void
    {
        $terms = $this->ontology->terms();

        $this->assertNotEmpty($terms);

        foreach ($terms as $name => $definition) {
            $this->assertIsString($name);
            $this->assertNotSame('', trim((string) $name));

            $this->assertArrayHasKey('type', $definition);
            $this->assertTrue(
                EntityType::isValid((string) $definition['type']),
                sprintf('"%s" declares unknown type "%s".', $name, $definition['type'])
            );

            $this->assertIsArray($definition['aliases'] ?? []);
            $this->assertIsArray($definition['same_as'] ?? []);
        }
    }

    public function testEveryTermUsesAMedicalType(): void
    {
        foreach ($this->ontology->terms() as $name => $definition) {
            $this->assertTrue(
                EntityType::isMedical((string) $definition['type']),
                sprintf('"%s" is in the clinical ontology but is not typed medically.', $name)
            );
        }
    }

    public function testSameAsEntriesAreValidUrls(): void
    {
        foreach ($this->ontology->terms() as $name => $definition) {
            foreach ((array) ($definition['same_as'] ?? []) as $url) {
                $this->assertNotFalse(
                    filter_var((string) $url, FILTER_VALIDATE_URL),
                    sprintf('"%s" has a malformed sameAs URL: %s', $name, $url)
                );
            }
        }
    }

    public function testAliasesAreLongEnoughToMatchSafely(): void
    {
        // The dictionary extractor drops surface forms shorter than three
        // characters, so shipping one would be a silently dead alias.
        foreach ($this->ontology->terms() as $name => $definition) {
            foreach ((array) ($definition['aliases'] ?? []) as $alias) {
                $this->assertGreaterThanOrEqual(
                    3,
                    mb_strlen(Text::normalize((string) $alias), 'UTF-8'),
                    sprintf('Alias "%s" on "%s" is too short to be matched.', $alias, $name)
                );
            }
        }
    }

    public function testAliasesDoNotCollideAcrossTerms(): void
    {
        $seen = [];

        foreach ($this->ontology->terms() as $name => $definition) {
            $surfaces = array_merge([(string) $name], array_map('strval', (array) ($definition['aliases'] ?? [])));

            foreach ($surfaces as $surface) {
                $key = Text::normalize($surface);

                $this->assertArrayNotHasKey(
                    $key,
                    $seen,
                    sprintf('"%s" is claimed by both "%s" and "%s".', $surface, $seen[$key] ?? '', $name)
                );

                $seen[$key] = (string) $name;
            }
        }
    }

    public function testMultilingualCoverage(): void
    {
        // The product targets Persian and Arabic markets; a vocabulary with
        // only Latin surface forms would recognise nothing on those sites.
        $withNonLatin = 0;

        foreach ($this->ontology->terms() as $definition) {
            foreach ((array) ($definition['aliases'] ?? []) as $alias) {
                if (preg_match('/[\x{0600}-\x{06FF}]/u', (string) $alias) === 1) {
                    $withNonLatin++;
                    break;
                }
            }
        }

        $this->assertGreaterThanOrEqual(
            (int) (count($this->ontology->terms()) * 0.8),
            $withNonLatin,
            'At least 80% of terms should carry a Persian or Arabic alias.'
        );
    }

    public function testEveryRelationResolvesToKnownTerms(): void
    {
        $validated = $this->ontology->validatedRelations();

        $this->assertSame(
            [],
            $validated['unresolved'],
            'Every curated relation must reference terms that exist in the ontology.'
        );

        $this->assertNotEmpty($validated['valid']);
    }

    public function testRelationsUseValidPredicates(): void
    {
        foreach ($this->ontology->relations() as $relation) {
            $this->assertTrue(
                RelationType::isValid((string) $relation['predicate']),
                sprintf('Unknown predicate "%s".', $relation['predicate'])
            );
        }
    }

    public function testRelationsAreNotSelfReferential(): void
    {
        foreach ($this->ontology->relations() as $relation) {
            $this->assertNotSame(
                $relation['subject'],
                $relation['object'],
                sprintf('"%s" relates to itself.', $relation['subject'])
            );
        }
    }

    public function testRelationsAreNotDuplicated(): void
    {
        $seen = [];

        foreach ($this->ontology->relations() as $relation) {
            $key = sprintf('%s|%s|%s', $relation['subject'], $relation['predicate'], $relation['object']);

            $this->assertArrayNotHasKey($key, $seen, sprintf('Duplicate relation: %s', $key));

            $seen[$key] = true;
        }
    }

    public function testStatsAreOrderedAndComplete(): void
    {
        $stats = $this->ontology->stats();

        $this->assertSame(count($this->ontology->terms()), array_sum($stats));
        $this->assertSame($stats, array_slice($stats, 0, count($stats), true));
    }
}
