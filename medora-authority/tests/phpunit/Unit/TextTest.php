<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Support\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The text layer is where a silent bug does the most damage: every entity,
 * every salience score and every chunk boundary is derived from it, and a
 * regression in normalisation would quietly degrade the whole pipeline rather
 * than throwing.
 */
final class TextTest extends TestCase
{
    public function testPlainStripsMarkupShortcodesAndBlockComments(): void
    {
        $html = '<!-- wp:paragraph --><p>Hello <strong>world</strong></p><!-- /wp:paragraph -->'
            . '[shortcode attr="x"]<script>alert(1)</script>';

        $this->assertSame('Hello world', Text::plain($html));
    }

    public function testNormalizeFoldsArabicFormsToPersian(): void
    {
        // "علي" written with the Arabic yeh must resolve to the same entity as
        // "علی" with the Persian yeh — otherwise the same doctor becomes two.
        $this->assertSame(
            Text::normalize('علی'),
            Text::normalize('علي')
        );

        $this->assertSame(
            Text::normalize('کلینیک'),
            Text::normalize('كلينيك')
        );
    }

    public function testNormalizeStripsZeroWidthNonJoiner(): void
    {
        $withZwnj = "می\u{200c}شود";

        $this->assertStringNotContainsString("\u{200c}", Text::normalize($withZwnj));
    }

    public function testTokensDropStopWordsInBothScripts(): void
    {
        $tokens = Text::tokens('The patient is in the clinic and the doctor');

        $this->assertNotContains('the', $tokens);
        $this->assertNotContains('is', $tokens);
        $this->assertContains('patient', $tokens);
        $this->assertContains('clinic', $tokens);

        $persian = Text::tokens('بیمار در کلینیک است و پزشک');

        $this->assertNotContains('در', $persian);
        $this->assertNotContains('است', $persian);
        $this->assertContains('بیمار', $persian);
    }

    public function testSentencesSplitOnPersianQuestionMark(): void
    {
        $sentences = Text::sentences('کبد چرب چیست؟ این یک بیماری شایع است.');

        $this->assertCount(2, $sentences);
        $this->assertSame('کبد چرب چیست؟', $sentences[0]);
    }

    public function testWordCountIsMultibyteSafe(): void
    {
        $this->assertSame(4, Text::wordCount('علائم کبد چرب چیست'));
        $this->assertSame(4, Text::wordCount('<p>one two three four</p>'));
    }

    public function testTruncateBreaksOnWordBoundary(): void
    {
        $result = Text::truncate('the quick brown fox jumps over', 15);

        $this->assertStringEndsWith('…', $result);
        $this->assertStringNotContainsString('bro…', $result);
    }

    public function testTruncateLeavesShortStringsUntouched(): void
    {
        $this->assertSame('short', Text::truncate('short', 50));
    }

    public function testHashIgnoresMarkupAndCaseChanges(): void
    {
        $this->assertSame(
            Text::hash('<p>Fatty Liver Disease</p>'),
            Text::hash('fatty liver disease')
        );
    }

    public function testHashChangesWhenContentChanges(): void
    {
        $this->assertNotSame(
            Text::hash('fatty liver disease'),
            Text::hash('fatty liver disease treatment')
        );
    }

    #[DataProvider('tokenEstimateProvider')]
    public function testEstimateTokensUsesScriptAwareDivisor(string $text, int $atLeast, int $atMost): void
    {
        $estimate = Text::estimateTokens($text);

        $this->assertGreaterThanOrEqual($atLeast, $estimate);
        $this->assertLessThanOrEqual($atMost, $estimate);
    }

    /** @return array<string, array{0: string, 1: int, 2: int}> */
    public static function tokenEstimateProvider(): array
    {
        return [
            // ~40 Latin characters at ~4 chars/token.
            'latin'   => ['The quick brown fox jumps over a dog.', 8, 12],
            // Persian segments far more finely, so the same character count
            // must estimate higher.
            'persian' => ['کبد چرب یک بیماری شایع در ایران است.', 12, 20],
            'empty'   => ['', 0, 0],
        ];
    }

    public function testNgramsProducesContiguousWindows(): void
    {
        $bigrams = Text::ngrams('liver biopsy recovery time', 2);

        $this->assertSame(
            ['liver biopsy', 'biopsy recovery', 'recovery time'],
            $bigrams
        );
    }

    public function testNgramsReturnsEmptyWhenTextIsShorterThanWindow(): void
    {
        $this->assertSame([], Text::ngrams('liver', 3));
    }

    public function testTermFrequenciesAreSortedDescending(): void
    {
        $frequencies = Text::termFrequencies('liver liver liver biopsy biopsy scan');

        $this->assertSame(['liver' => 3, 'biopsy' => 2, 'scan' => 1], $frequencies);
    }
}
