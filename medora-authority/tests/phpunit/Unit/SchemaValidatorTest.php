<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Schema\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testWellFormedGraphPasses(): void
    {
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type' => 'Organization',
                    '@id'   => 'https://x.test/#org',
                    'name'  => 'X Clinic',
                    'url'   => 'https://x.test',
                ],
                [
                    '@type'     => 'WebSite',
                    '@id'       => 'https://x.test/#site',
                    'name'      => 'X Clinic',
                    'url'       => 'https://x.test',
                    'publisher' => [ '@id' => 'https://x.test/#org' ],
                ],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['node_count']);
        $this->assertSame([], $result['errors']);
    }

    public function testMissingContextIsAnError(): void
    {
        $result = $this->validator->validate(['@graph' => []]);

        $this->assertFalse($result['valid']);
    }

    public function testMissingRequiredPropertyIsAnError(): void
    {
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [
                // Organization requires both name and url.
                ['@type' => 'Organization', '@id' => 'https://x.test/#org', 'name' => 'X'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('url', $result['errors'][0]['message']);
    }

    public function testNodeWithoutATypeIsAnError(): void
    {
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [['name' => 'Orphan']],
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testDanglingReferenceIsWarnedButNotFatal(): void
    {
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'     => 'WebSite',
                    '@id'       => 'https://x.test/#site',
                    'name'      => 'X',
                    'url'       => 'https://x.test',
                    'publisher' => [ '@id' => 'https://x.test/#missing' ],
                ],
            ],
        ]);

        // A dangling pointer degrades the graph but does not invalidate it, so
        // it must not block output.
        $this->assertTrue($result['valid']);

        $warnings = array_filter(
            $result['errors'],
            static fn (array $issue): bool => $issue['severity'] === SchemaValidator::SEVERITY_WARNING
        );

        $this->assertNotEmpty($warnings);
    }

    public function testCrossPageEntityReferencesAreNotWarned(): void
    {
        // Entity ids intentionally resolve to nodes published on other pages;
        // flagging them would make every real page look broken.
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'    => 'WebPage',
                    '@id'      => 'https://x.test/page/#webpage',
                    'name'     => 'Page',
                    'url'      => 'https://x.test/page',
                    'mentions' => [ '@id' => 'https://x.test/#/entity/abc123' ],
                ],
            ],
        ]);

        $this->assertSame([], $result['errors']);
    }

    public function testEmptyStringPropertyCountsAsMissing(): void
    {
        $result = $this->validator->validate([
            '@context' => 'https://schema.org',
            '@graph'   => [
                ['@type' => 'Person', '@id' => 'https://x.test/#p', 'name' => ''],
            ],
        ]);

        $this->assertFalse($result['valid']);
    }
}
