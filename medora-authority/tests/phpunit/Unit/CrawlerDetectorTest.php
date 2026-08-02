<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Analytics\ReferralDetector;
use Medora\Authority\Crawler\CrawlerDetector;
use Medora\Authority\Crawler\CrawlerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrawlerDetectorTest extends TestCase
{
    #[DataProvider('userAgentProvider')]
    public function testDetectsKnownCrawlers(string $userAgent, ?string $expected): void
    {
        $match = ( new CrawlerDetector() )->detect($userAgent);

        $this->assertSame($expected, $match['slug'] ?? null);
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function userAgentProvider(): array
    {
        return [
            'GPTBot'      => ['Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)', 'gptbot'],
            'ClaudeBot'   => ['Mozilla/5.0 (compatible; ClaudeBot/1.0)', 'claudebot'],
            'Perplexity'  => ['Mozilla/5.0 (compatible; PerplexityBot/1.0)', 'perplexitybot'],
            'CCBot'       => ['CCBot/2.0 (https://commoncrawl.org/faq/)', 'ccbot'],
            'Applebot'    => ['Mozilla/5.0 (compatible; Applebot/0.1)', 'applebot'],
            'browser'     => ['Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/605.1.15', null],
            'empty'       => ['', null],
        ];
    }

    public function testLongestTokenWinsOnOverlappingNames(): void
    {
        // "ClaudeBot" is a substring-adjacent name; the more specific
        // "Claude-SearchBot" must not be swallowed by it.
        $match = ( new CrawlerDetector() )->detect('Mozilla/5.0 (compatible; Claude-SearchBot/1.0)');

        $this->assertSame('claude-searchbot', $match['slug'] ?? null);
    }

    public function testRobotsOnlyTokensNeverMatchARequest(): void
    {
        // Google-Extended is a robots.txt directive, not a real user agent —
        // treating it as one would produce phantom crawl log entries.
        $this->assertNull(( new CrawlerDetector() )->detect('Google-Extended'));
    }

    public function testRegistryIsPopulatedAndWellFormed(): void
    {
        $registry = CrawlerRegistry::all();

        $this->assertGreaterThanOrEqual(25, count($registry));

        foreach ($registry as $slug => $crawler) {
            $this->assertSame($slug, $crawler['slug']);
            $this->assertNotSame('', $crawler['name']);
            $this->assertNotSame('', $crawler['vendor']);
            $this->assertNotSame('', $crawler['purpose']);
            $this->assertNotSame('', $crawler['robots_token']);
        }
    }

    public function testRegistryGroupsByVendor(): void
    {
        $grouped = CrawlerRegistry::byVendor();

        $this->assertArrayHasKey('OpenAI', $grouped);
        $this->assertArrayHasKey('Anthropic', $grouped);
    }

    #[DataProvider('referrerProvider')]
    public function testDetectsAssistantReferrals(string $referrer, string $query, ?string $expected): void
    {
        $match = ( new ReferralDetector() )->detect($referrer, $query);

        $this->assertSame($expected, $match['slug'] ?? null);
    }

    /** @return array<string, array{0: string, 1: string, 2: string|null}> */
    public static function referrerProvider(): array
    {
        return [
            'chatgpt'       => ['https://chatgpt.com/c/abc', '', 'chatgpt'],
            'claude'        => ['https://claude.ai/chat/1', '', 'claude'],
            'perplexity'    => ['https://www.perplexity.ai/search/x', '', 'perplexity'],
            'copilot'       => ['https://copilot.microsoft.com/', '', 'copilot'],
            'utm fallback'  => ['', 'utm_source=chatgpt.com&utm_medium=ai', 'chatgpt'],
            'own domain'    => ['https://example.test/page', '', null],
            'unrelated'     => ['https://news.ycombinator.com/', '', null],
            'nothing'       => ['', '', null],
        ];
    }

    public function testReferralLabelsAreHumanReadable(): void
    {
        $this->assertSame('Microsoft Copilot', ReferralDetector::label('copilot'));
        $this->assertSame('ChatGPT', ReferralDetector::label('chatgpt'));
    }
}
