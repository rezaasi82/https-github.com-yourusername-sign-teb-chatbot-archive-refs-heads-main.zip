<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Citation\Citation;
use Medora\Authority\Citation\CitationFormatter;
use Medora\Authority\Citation\CitationQualityScorer;
use Medora\Authority\Citation\EvidenceLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CitationTest extends TestCase
{
    private function sample(): Citation
    {
        return new Citation(
            title: 'Nonalcoholic fatty liver disease: a systematic review',
            authors: ['Jane A Smith', 'Ali Rezaei'],
            container: 'The Lancet',
            year: 2021,
            doi: '10.1016/S0140-6736(21)00001-1',
            evidenceLevel: EvidenceLevel::LEVEL_1A,
        );
    }

    public function testApaFormatting(): void
    {
        $formatted = ( new CitationFormatter() )->format($this->sample(), CitationFormatter::APA);

        $this->assertStringContainsString('(2021).', $formatted);
        $this->assertStringContainsString('The Lancet.', $formatted);
        $this->assertStringContainsString('https://doi.org/10.1016', $formatted);
    }

    public function testVancouverFormatting(): void
    {
        $formatted = ( new CitationFormatter() )->format($this->sample(), CitationFormatter::VANCOUVER);

        $this->assertStringContainsString('doi:10.1016', $formatted);
        $this->assertStringContainsString('2021.', $formatted);
    }

    public function testHarvardFormatting(): void
    {
        $formatted = ( new CitationFormatter() )->format($this->sample(), CitationFormatter::HARVARD);

        $this->assertStringContainsString( "'Nonalcoholic", $formatted );
    }

    public function testVancouverTruncatesAtSixAuthors(): void
    {
        $citation = new Citation(
            title: 'Many hands',
            authors: ['A One', 'B Two', 'C Three', 'D Four', 'E Five', 'F Six', 'G Seven'],
        );

        $this->assertStringContainsString(
            'et al',
            ( new CitationFormatter() )->format($citation, CitationFormatter::VANCOUVER)
        );
    }

    public function testCanonicalUrlPrefersDoiThenPubmedThenUrl(): void
    {
        $this->assertSame(
            'https://doi.org/10.1016/S0140-6736(21)00001-1',
            $this->sample()->canonicalUrl()
        );

        $this->assertSame(
            'https://pubmed.ncbi.nlm.nih.gov/12345678/',
            ( new Citation( title: 'x', pmid: '12345678' ) )->canonicalUrl()
        );

        $this->assertSame(
            'https://example.test/paper',
            ( new Citation( title: 'x', url: 'https://example.test/paper' ) )->canonicalUrl()
        );
    }

    public function testSchemaNodeIsWellFormed(): void
    {
        $node = $this->sample()->toSchemaNode();

        $this->assertSame('ScholarlyArticle', $node['@type']);
        $this->assertSame('doi:10.1016/S0140-6736(21)00001-1', $node['identifier']);
        $this->assertCount(2, $node['author']);
        $this->assertArrayNotHasKey('pmid', $node);
    }

    #[DataProvider('publicationTypeProvider')]
    public function testEvidenceLevelInference(string|array $types, string $expected): void
    {
        $this->assertSame($expected, EvidenceLevel::fromPublicationType($types));
    }

    /** @return array<string, array{0: string|list<string>, 1: string}> */
    public static function publicationTypeProvider(): array
    {
        return [
            'meta-analysis'    => [['Meta-Analysis'], EvidenceLevel::LEVEL_1A],
            'systematic review' => ['Systematic Review', EvidenceLevel::LEVEL_1A],
            'guideline'        => ['Practice Guideline', EvidenceLevel::GUIDELINE],
            'rct'              => [['Randomized Controlled Trial'], EvidenceLevel::LEVEL_1B],
            'cohort'           => [['Cohort Study'], EvidenceLevel::LEVEL_2B],
            'case report'      => [['Case Report'], EvidenceLevel::LEVEL_4],
            'editorial'        => [['Editorial'], EvidenceLevel::LEVEL_5],
            'unknown'          => [['Letter'], ''],
        ];
    }

    public function testEvidenceStrengthIsOrdered(): void
    {
        $this->assertGreaterThan(
            EvidenceLevel::strength(EvidenceLevel::LEVEL_4),
            EvidenceLevel::strength(EvidenceLevel::LEVEL_1A)
        );

        $this->assertGreaterThan(
            EvidenceLevel::strength(EvidenceLevel::LEVEL_5),
            EvidenceLevel::strength(EvidenceLevel::LEVEL_2B)
        );
    }

    public function testQualityScoreRewardsDesignRecencyAndIdentifiers(): void
    {
        $scorer = new CitationQualityScorer();

        $strong = $scorer->score(
            year: (int) gmdate('Y'),
            hasDoi: true,
            container: 'The Lancet',
            citedBy: 500,
            evidenceLevel: EvidenceLevel::LEVEL_1A
        );

        $weak = $scorer->score(
            year: 1998,
            hasDoi: false,
            container: '',
            citedBy: 0,
            evidenceLevel: EvidenceLevel::LEVEL_5
        );

        $this->assertGreaterThan($weak, $strong);
        $this->assertLessThanOrEqual(100.0, $strong);
        $this->assertGreaterThanOrEqual(0.0, $weak);
    }

    public function testLandmarkTrialOutranksRecentCaseReport(): void
    {
        $scorer = new CitationQualityScorer();

        // Recency is weighted but must never dominate study design.
        $landmark = $scorer->score(
            year: 2003,
            hasDoi: true,
            container: 'NEJM',
            citedBy: 4000,
            evidenceLevel: EvidenceLevel::LEVEL_1B
        );

        $recentAnecdote = $scorer->score(
            year: (int) gmdate('Y'),
            hasDoi: true,
            container: 'Case Reports',
            citedBy: 0,
            evidenceLevel: EvidenceLevel::LEVEL_4
        );

        $this->assertGreaterThan($recentAnecdote, $landmark);
    }
}
