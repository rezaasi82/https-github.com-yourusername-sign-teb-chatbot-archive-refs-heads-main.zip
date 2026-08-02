<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Support\Chunker;
use PHPUnit\Framework\TestCase;

/**
 * Chunking determines what a retriever actually sees, so the invariants
 * asserted here — sequential indexes, non-empty text, headings carried onto
 * their sections — are the difference between a citable page and an
 * unretrievable one.
 */
final class ChunkerTest extends TestCase
{
    private const SAMPLE = '<p>Intro paragraph about the liver. It sets up the topic.</p>'
        . '<h2>Symptoms</h2><p>Fatigue is common. Jaundice may appear. Pain occurs in the upper abdomen.</p>'
        . '<h2>Treatment</h2><p>Diet is the first line. Exercise helps too. Medication is sometimes used.</p>';

    public function testProducesSequentiallyIndexedChunks(): void
    {
        $chunks = ( new Chunker() )->chunk(self::SAMPLE, 60, 1);

        $this->assertNotEmpty($chunks);
        $this->assertSame(range(0, count($chunks) - 1), array_column($chunks, 'index'));
    }

    public function testHeadingsAreCarriedOntoTheirChunks(): void
    {
        $chunks = ( new Chunker() )->chunk(self::SAMPLE, 60, 1);

        $this->assertContains('Symptoms', array_column($chunks, 'heading'));
        $this->assertContains('Treatment', array_column($chunks, 'heading'));
    }

    public function testEveryChunkCarriesTextAndATokenEstimate(): void
    {
        foreach (( new Chunker() )->chunk(self::SAMPLE, 60, 1) as $chunk) {
            $this->assertNotSame('', trim($chunk['text']));
            $this->assertGreaterThan(0, $chunk['tokens']);
        }
    }

    public function testEmptyContentProducesNoChunks(): void
    {
        $this->assertSame([], ( new Chunker() )->chunk(''));
        $this->assertSame([], ( new Chunker() )->chunk('<p></p>'));
    }

    public function testContentWithoutHeadingsStillChunks(): void
    {
        $chunks = ( new Chunker() )->chunk('<p>One sentence here. Another sentence follows.</p>');

        $this->assertNotEmpty($chunks);
        $this->assertSame('', $chunks[0]['heading']);
    }

    public function testASingleOversizedSentenceIsNotDropped(): void
    {
        // A sentence longer than the budget still has to ship: a truncated
        // claim is worse than an oversized chunk.
        $long = str_repeat('word ', 400) . '.';

        $chunks = ( new Chunker() )->chunk('<p>' . $long . '</p>', 50, 1);

        $this->assertNotEmpty($chunks);
        $this->assertStringContainsString('word', $chunks[0]['text']);
    }

    public function testSmallerBudgetProducesMoreChunks(): void
    {
        $chunker = new Chunker();

        $this->assertGreaterThanOrEqual(
            count($chunker->chunk(self::SAMPLE, 400, 0)),
            count($chunker->chunk(self::SAMPLE, 40, 0))
        );
    }
}
