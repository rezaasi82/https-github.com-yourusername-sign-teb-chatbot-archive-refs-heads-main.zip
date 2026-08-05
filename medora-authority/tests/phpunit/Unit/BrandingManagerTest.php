<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Core\Options;
use Medora\Authority\WhiteLabel\BrandingManager;
use PHPUnit\Framework\TestCase;

/**
 * White-label branding.
 *
 * The accent-colour cases are the reason this file exists. The manager
 * published `--medora-accent` on `:root`, while the stylesheet declares the
 * same property on `.medora-app` — a nearer ancestor, which wins — so the
 * colour an agency picked was overridden for the entire dashboard and did
 * nothing. Nothing errored and the style tag was present in the markup, which
 * is exactly why it went unnoticed.
 */
final class BrandingManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['medora_test_options']);
    }

    public function testInactiveUntilBothTheFlagAndANameAreSet(): void
    {
        $this->assertFalse($this->branding(['product_name' => 'Acme SEO'])->isActive());
        $this->assertFalse($this->branding(['enabled' => '1', 'product_name' => ''])->isActive());
        $this->assertTrue($this->branding(['enabled' => '1', 'product_name' => 'Acme SEO'])->isActive());
    }

    public function testUnsetValuesFallBackToTheDefaults(): void
    {
        $branding = $this->branding(['enabled' => '1', 'product_name' => 'Acme SEO']);

        $this->assertSame('Acme SEO', $branding->productName());
        $this->assertSame('Medora', $branding->get('vendor_name'));
    }

    public function testAccentIsPublishedAsTheBrandTokenNotTheDesignToken(): void
    {
        $style = $this->accentStyle('#ff0000');

        $this->assertStringContainsString('--medora-brand-accent:#ff0000', $style);

        // Setting `--medora-accent` here is the bug: `.medora-app` redeclares
        // it and would win.
        $this->assertStringNotContainsString('--medora-accent:', $style);
    }

    public function testANonHexAccentPrintsNothing(): void
    {
        // Defence in depth. The REST layer drops these before storage, so
        // reaching here means something wrote the option directly.
        $this->assertSame('', $this->accentStyle('red; } body { display:none'));
        $this->assertSame('', $this->accentStyle('var(--x)'));
    }

    public function testThePluginRowIsRewritten(): void
    {
        $row = [
            MEDORA_PLUGIN_BASENAME => [
                'Name'      => 'Medora Authority',
                'Author'    => 'Medora',
                'AuthorURI' => 'https://medora.ai',
                'PluginURI' => 'https://medora.ai',
            ],
        ];

        $out = $this->branding(['enabled' => '1', 'product_name' => 'Acme SEO'])->rewritePluginRow($row);

        $this->assertSame('Acme SEO', $out[MEDORA_PLUGIN_BASENAME]['Name']);
        // The author is only replaced when the agency asks for it.
        $this->assertSame('Medora', $out[MEDORA_PLUGIN_BASENAME]['Author']);
    }

    public function testHideVendorReplacesTheAuthorAndLinks(): void
    {
        $row = [MEDORA_PLUGIN_BASENAME => ['Name' => 'M', 'Author' => 'Medora', 'AuthorURI' => '', 'PluginURI' => '']];

        $out = $this->branding([
            'enabled'      => '1',
            'product_name' => 'Acme SEO',
            'hide_vendor'  => '1',
            'vendor_name'  => 'Acme Ltd',
            'vendor_url'   => 'https://acme.test',
            'support_url'  => 'https://help.acme.test',
        ])->rewritePluginRow($row);

        $this->assertSame('Acme Ltd', $out[MEDORA_PLUGIN_BASENAME]['Author']);
        $this->assertSame('https://acme.test', $out[MEDORA_PLUGIN_BASENAME]['AuthorURI']);
        $this->assertSame('https://help.acme.test', $out[MEDORA_PLUGIN_BASENAME]['PluginURI']);
    }

    public function testAnotherPluginsRowIsLeftAlone(): void
    {
        $others = ['other/other.php' => ['Name' => 'Other']];

        $this->assertSame(
            $others,
            $this->branding(['enabled' => '1', 'product_name' => 'Acme'])->rewritePluginRow($others)
        );
    }

    /**
     * @param array<string, string> $whiteLabel
     */
    private function branding(array $whiteLabel): BrandingManager
    {
        $GLOBALS['medora_test_options'] = [Options::OPTION_KEY => ['white_label' => $whiteLabel]];

        $branding = new BrandingManager(new Options());

        // Options caches on first read; warming it pins this instance to the
        // configuration above.
        $branding->branding();

        return $branding;
    }

    private function accentStyle(string $accent): string
    {
        $branding = $this->branding(['enabled' => '1', 'product_name' => 'Acme', 'accent_color' => $accent]);

        ob_start();
        $branding->printAccentColor();

        return (string) ob_get_clean();
    }
}
