<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Semantic\TopicCoverage;
use PHPUnit\Framework\TestCase;

/**
 * Facets are identified by slug and labelled only at display.
 *
 * They used to be keyed by their translated label, which made a facet's
 * identity locale-dependent — the same page produced `Symptoms` in English and
 * `علائم` in Persian, so every consumer matching on the key stopped matching
 * the moment the site was translated. Nothing errored; the brief just quietly
 * produced fallback headings for every section.
 *
 * The unit suite runs without a loaded text domain, so `__()` returns its input
 * and these assertions read as English. That is the point: the *slug* is what
 * the code matches on, and it is the same string in every locale.
 */
final class TopicFacetTest extends TestCase
{
    public function testKnownSlugsHaveLabels(): void
    {
        $this->assertSame('Symptoms', TopicCoverage::label('symptoms'));
        $this->assertSame('When to seek care', TopicCoverage::label('when_to_seek_care'));
        $this->assertSame('Evidence and sources', TopicCoverage::label('evidence_and_sources'));
    }

    public function testUnknownSlugFallsBackToItsHumanisedForm(): void
    {
        // A facet added through `medora_topic_facets` without registering a
        // label must still render as something, not as an empty heading.
        $this->assertSame('Custom facet', TopicCoverage::label('custom_facet'));
    }

    public function testLabelsMapsAListInOrder(): void
    {
        $this->assertSame(
            ['Definition', 'Treatment', 'Risks'],
            TopicCoverage::labels(['definition', 'treatment', 'risks'])
        );
    }

    public function testLabelDefaultsAreTheHumanisedSlug(): void
    {
        // Worth pinning: in an untranslated environment every registered label
        // is identical to the fallback, which means "did this slug get a real
        // label?" cannot be answered by comparing the two. A missing label is
        // caught by the POT check instead — an unregistered facet contributes
        // no `__()` call for a translator to see.
        $this->assertSame('When to seek care', TopicCoverage::label('when_to_seek_care'));
        $this->assertSame('When to seek care', ucfirst(str_replace('_', ' ', 'when_to_seek_care')));
    }

    public function testSlugsAreSafeAsIdentifiers(): void
    {
        // `RecommendationEngine` builds action codes from these. Anything
        // outside [a-z0-9_] would not survive `sanitize_key()`, which is how
        // the translated labels collapsed to a single empty code in Persian.
        foreach (self::slugs() as $slug) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $slug);
            $this->assertSame($slug, sanitize_key($slug));
        }
    }

    /**
     * @return list<string>
     */
    private static function slugs(): array
    {
        return [
            'definition', 'symptoms', 'causes', 'diagnosis', 'treatment', 'prognosis',
            'when_to_seek_care', 'what_it_is', 'who_needs_it', 'preparation', 'recovery',
            'risks', 'cost', 'what_it_does', 'features', 'pricing', 'comparison',
            'reviews', 'how_it_works', 'why_it_matters', 'examples', 'common_questions',
            'evidence_and_sources', 'review_date',
        ];
    }
}
