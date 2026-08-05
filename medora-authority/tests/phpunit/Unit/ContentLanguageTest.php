<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Core\Options;
use Medora\Authority\Support\ContentLanguage;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * The content language is not the admin language.
 *
 * Everything used to read `get_locale()`, which describes the person in
 * wp-admin. A Persian clinic running an English-locale WordPress is an ordinary
 * setup, and it made the plugin declare `inLanguage: "en-US"` on every Persian
 * page — a wrong signal handed to exactly the crawlers this product exists to
 * signal correctly — and told the AI Writer to summarise those pages in English.
 *
 * The fallback cases matter as much as the override cases: with nothing
 * configured, behaviour must be identical to before.
 */
final class ContentLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        // Hooks registered by a test would otherwise leak into every later one
        // — including tests in other files, since the registry is global.
        medora_test_reset_hooks();
    }

    protected function tearDown(): void
    {
        medora_test_reset_hooks();

        unset($GLOBALS['medora_test_options'], $GLOBALS['medora_test_locale']);
    }

    public function testAnUnsetLanguageFollowsWordPress(): void
    {
        $this->assertSame('fa-IR', $this->language('', 'fa_IR')->tag());
        $this->assertSame('en-US', $this->language('', 'en_US')->tag());
    }

    public function testLocaleUnderscoresBecomeBcp47Hyphens(): void
    {
        $this->assertSame('pt-BR', $this->language('', 'pt_BR')->tag());
        $this->assertSame('fa-IR', $this->language('fa_IR', 'en_US')->tag());
    }

    public function testAConfiguredLanguageBeatsTheAdminLocale(): void
    {
        $language = $this->language('fa', 'en_US');

        $this->assertSame('fa', $language->tag());
        $this->assertSame('fa', $language->code());
        $this->assertSame('Persian (فارسی)', $language->name());
    }

    public function testTheCodeIsTheTwoLetterSubtag(): void
    {
        $this->assertSame('fa', $this->language('fa_IR')->code());
        $this->assertSame('pt', $this->language('', 'pt_BR')->code());
    }

    /**
     * @dataProvider directions
     */
    public function testDirectionComesFromTheContentNotTheAdmin(string $configured, bool $rtl): void
    {
        $this->assertSame($rtl, $this->language($configured, 'en_US')->isRtl());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function directions(): array
    {
        return [
            'persian' => ['fa', true],
            'arabic'  => ['ar', true],
            'urdu'    => ['ur', true],
            'english' => ['en', false],
            'turkish' => ['tr', false],
        ];
    }

    public function testAnUnknownLanguageKeepsItsTagButNamesEnglish(): void
    {
        // The tag is the publisher's business; the name only exists to put a
        // word in a prompt, and inventing one would be worse than defaulting.
        $language = $this->language('xx');

        $this->assertSame('xx', $language->tag());
        $this->assertSame('English', $language->name());
    }

    public function testAFilterCanSetTheLanguagePerPost(): void
    {
        // Where Polylang and WPML belong: on a multilingual site the language
        // is a property of the page, and no site-wide setting can be right.
        $post = new WP_Post((object) ['ID' => 7]);

        add_filter(
            'medora_content_language',
            static fn (string $tag, ?WP_Post $for): string => $for instanceof WP_Post ? 'ar' : $tag,
            10,
            2
        );

        $language = $this->language('fa', 'en_US');

        $this->assertSame('ar', $language->tag($post));
        $this->assertTrue($language->isRtl($post));

        // Site-level output is unaffected by a per-post override.
        $this->assertSame('fa', $language->tag());
    }

    public function testChoicesLeadWithFollowingWordPress(): void
    {
        $choices = ContentLanguage::choices();

        $this->assertSame('', $choices[0]['value']);
        $this->assertContains('fa', array_column($choices, 'value'));
        $this->assertContains('ar', array_column($choices, 'value'));

        foreach ($choices as $choice) {
            $this->assertNotSame('', $choice['label']);
        }
    }

    private function language(string $configured, string $locale = 'en_US'): ContentLanguage
    {
        $GLOBALS['medora_test_options'] = [Options::OPTION_KEY => ['default_language' => $configured]];
        $GLOBALS['medora_test_locale']  = $locale;

        $language = new ContentLanguage(new Options());

        // Options caches on first read; warming it pins this instance to the
        // configuration above.
        $language->tag();

        return $language;
    }
}
