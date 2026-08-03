<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Linking\AnchorWrapper;
use PHPUnit\Framework\TestCase;

/**
 * This class rewrites a customer's published HTML, so it gets the most
 * adversarial tests in the suite. Every case here is a way the naive
 * implementation (`str_replace`) breaks a live page.
 */
final class AnchorWrapperTest extends TestCase
{
    private AnchorWrapper $wrapper;

    protected function setUp(): void
    {
        $this->wrapper = new AnchorWrapper();
    }

    public function testWrapsAPhraseInBodyText(): void
    {
        $result = $this->wrapper->wrap(
            '<p>A liver biopsy is sometimes needed.</p>',
            'liver biopsy',
            'https://example.test/biopsy/'
        );

        $this->assertTrue($result['applied']);
        $this->assertStringContainsString(
            '<a href="https://example.test/biopsy/" data-medora-link="1">liver biopsy</a>',
            $result['content']
        );
    }

    public function testNeverNestsInsideAnExistingAnchor(): void
    {
        $html = '<p>See our <a href="/old/">liver biopsy</a> guide.</p>';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/new/');

        $this->assertFalse($result['applied'], 'Nesting an <a> inside an <a> is invalid and breaks both links.');
        $this->assertSame($html, $result['content']);
        $this->assertSame(0, $result['eligible']);
    }

    public function testNeverLinksInsideAHeading(): void
    {
        $html = '<h2>Liver biopsy</h2><p>Nothing else here.</p>';

        $result = $this->wrapper->wrap($html, 'Liver biopsy', 'https://example.test/x/');

        $this->assertFalse($result['applied']);
        $this->assertSame($html, $result['content']);
    }

    public function testNeverRewritesAnAttributeValue(): void
    {
        // The classic corruption: the phrase appears in alt text, and a naive
        // replace produces alt="<a href=...>liver biopsy</a>".
        $html = '<p><img src="x.jpg" alt="liver biopsy diagram" /> Some prose.</p>';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/x/');

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('alt="liver biopsy diagram"', $result['content']);
    }

    public function testVoidElementDoesNotOpenAContext(): void
    {
        // If <img> were treated as opening a context, everything after it would
        // become unlinkable.
        $html = '<p><img src="x.jpg" alt="scan"> A liver biopsy follows.</p>';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/x/');

        $this->assertTrue($result['applied']);
    }

    public function testNeverLinksInsideCodeOrPre(): void
    {
        $html = '<pre><code>liver biopsy</code></pre><p>liver biopsy in prose.</p>';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/x/');

        $this->assertTrue($result['applied']);
        // Only the prose occurrence is eligible, so it is the one that got wrapped.
        $this->assertSame(1, $result['eligible']);
        $this->assertStringContainsString('<code>liver biopsy</code>', $result['content']);
    }

    public function testRespectsTheOccurrenceIndex(): void
    {
        $html = '<p>liver biopsy one.</p><p>liver biopsy two.</p><p>liver biopsy three.</p>';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/x/', 2);

        $this->assertTrue($result['applied']);

        // Exactly one link, and it is on the second occurrence.
        $this->assertSame(1, substr_count($result['content'], '<a href='));
        $this->assertStringContainsString('<a href="https://example.test/x/" data-medora-link="1">liver biopsy</a> two.', $result['content']);
        $this->assertStringContainsString('<p>liver biopsy one.</p>', $result['content']);
    }

    public function testOutOfRangeOccurrenceAppliesNothing(): void
    {
        $result = $this->wrapper->wrap('<p>liver biopsy.</p>', 'liver biopsy', 'https://example.test/x/', 5);

        $this->assertFalse($result['applied']);
        $this->assertSame(1, $result['eligible'], 'Eligible occurrences are still counted so the UI can explain.');
    }

    public function testMatchingIsWordBoundaryAnchored(): void
    {
        // "colon" must not match inside "colonoscopy".
        $result = $this->wrapper->wrap('<p>A colonoscopy examines the colon.</p>', 'colon', 'https://example.test/colon/');

        $this->assertTrue($result['applied']);
        $this->assertStringContainsString('A colonoscopy examines', $result['content']);
        $this->assertStringContainsString('>colon</a>.', $result['content']);
        $this->assertSame(1, $result['eligible']);
    }

    public function testWorksOnPersianText(): void
    {
        // \b is ASCII-only, so the boundary assertion must be Unicode-aware or
        // this silently fails on the plugin's primary non-Latin market.
        $result = $this->wrapper->wrap(
            '<p>کبد چرب یک بیماری شایع است.</p>',
            'کبد چرب',
            'https://example.test/fa/'
        );

        $this->assertTrue($result['applied']);
        $this->assertStringContainsString('>کبد چرب</a>', $result['content']);
    }

    public function testMatchingIsCaseInsensitive(): void
    {
        $result = $this->wrapper->wrap('<p>A Liver Biopsy is invasive.</p>', 'liver biopsy', 'https://example.test/x/');

        $this->assertTrue($result['applied']);
        // The original casing is preserved — the editor's prose is not altered.
        $this->assertStringContainsString('>Liver Biopsy</a>', $result['content']);
    }

    public function testEmptyInputsAreNoOps(): void
    {
        $this->assertFalse($this->wrapper->wrap('<p>text</p>', '', 'https://example.test/')['applied']);
        $this->assertFalse($this->wrapper->wrap('', 'phrase', 'https://example.test/')['applied']);
        $this->assertFalse($this->wrapper->wrap('<p>text</p>', '   ', 'https://example.test/')['applied']);
    }

    public function testPhraseWithRegexMetacharactersIsMatchedLiterally(): void
    {
        $result = $this->wrapper->wrap(
            '<p>Vitamin B12 (cobalamin) matters.</p>',
            'B12 (cobalamin)',
            'https://example.test/b12/'
        );

        $this->assertTrue($result['applied']);
        $this->assertStringContainsString('>B12 (cobalamin)</a>', $result['content']);
    }

    public function testBlockCommentsDoNotOpenAContext(): void
    {
        $html = '<!-- wp:paragraph --><p>A liver biopsy is invasive.</p><!-- /wp:paragraph -->';

        $result = $this->wrapper->wrap($html, 'liver biopsy', 'https://example.test/x/');

        $this->assertTrue($result['applied']);
        $this->assertStringContainsString('<!-- wp:paragraph -->', $result['content']);
    }

    public function testCountEligibleDoesNotModifyContent(): void
    {
        $html = '<p>liver biopsy one.</p><h2>liver biopsy</h2><p>liver biopsy two.</p>';

        // Two in prose, one in a heading that does not count.
        $this->assertSame(2, $this->wrapper->countEligible($html, 'liver biopsy'));
    }

    public function testUnwrapRemovesOnlyMarkedAnchors(): void
    {
        $html = '<p><a href="/a/" data-medora-link="1">one</a> and <a href="/b/">two</a>.</p>';

        $result = $this->wrapper->unwrap($html);

        $this->assertSame(1, $result['reverted']);
        $this->assertStringNotContainsString('data-medora-link', $result['content']);
        $this->assertStringContainsString('<a href="/b/">two</a>', $result['content'], "An editor's own link must survive.");
        $this->assertStringContainsString('one and', $result['content']);
    }

    public function testUnwrapCanTargetOneDestination(): void
    {
        $html = '<p><a href="/a/" data-medora-link="1">one</a> <a href="/b/" data-medora-link="1">two</a></p>';

        $result = $this->wrapper->unwrap($html, 'data-medora-link', '/a/');

        $this->assertSame(1, $result['reverted']);
        $this->assertStringContainsString('<a href="/b/" data-medora-link="1">two</a>', $result['content']);
    }

    public function testRoundTripRestoresTheOriginal(): void
    {
        $original = '<p>A liver biopsy is sometimes needed.</p>';

        $wrapped = $this->wrapper->wrap($original, 'liver biopsy', 'https://example.test/x/');
        $this->assertTrue($wrapped['applied']);

        $this->assertSame($original, $this->wrapper->unwrap($wrapped['content'])['content']);
    }
}
