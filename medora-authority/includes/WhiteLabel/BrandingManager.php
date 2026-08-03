<?php

declare(strict_types=1);

namespace Medora\Authority\WhiteLabel;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves and applies white-label branding.
 */
final class BrandingManager
{
    private const OPTION = 'white_label';

    public function __construct(private readonly Options $options)
    {
    }

    /** @return array<string, string> */
    public function branding(): array
    {
        /** @var array<string, string> $stored */
        $stored = $this->options->getArray(self::OPTION);

        $defaults = [
            'enabled'      => '',
            'product_name' => 'Medora Authority',
            'short_name'   => 'Medora',
            'vendor_name'  => 'Medora',
            'vendor_url'   => 'https://medora.ai',
            'support_url'  => '',
            'logo_url'     => '',
            'accent_color' => '#2f6df6',
            'hide_vendor'  => '',
        ];

        /**
         * Filter the resolved branding. SaaS hosts use this to force branding
         * from code, which the customer then cannot override in the UI.
         *
         * @param array<string, string> $branding
         */
        return (array) apply_filters('medora_branding', array_merge($defaults, $stored));
    }

    public function isActive(): bool
    {
        $branding = $this->branding();

        return ! empty($branding['enabled']) && trim((string) $branding['product_name']) !== '';
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->branding()[$key] ?? $default);
    }

    public function productName(): string
    {
        return $this->get('product_name', 'Medora Authority');
    }

    public function register(): void
    {
        add_filter('medora_admin_menu_title', fn (): string => $this->get('short_name', 'Medora'));
        add_filter('medora_admin_page_title', fn (): string => $this->productName());

        add_filter('all_plugins', [$this, 'rewritePluginRow']);
        add_action('admin_head', [$this, 'printAccentColor']);
    }

    /**
     * Rewrite the plugin's own row on the Plugins screen.
     *
     * @param array<string, array<string, mixed>> $plugins
     * @return array<string, array<string, mixed>>
     */
    public function rewritePluginRow(array $plugins): array
    {
        if (! isset($plugins[MEDORA_PLUGIN_BASENAME])) {
            return $plugins;
        }

        $branding = $this->branding();

        $plugins[MEDORA_PLUGIN_BASENAME]['Name'] = $this->productName();

        if (! empty($branding['hide_vendor'])) {
            $plugins[MEDORA_PLUGIN_BASENAME]['Author']     = (string) $branding['vendor_name'];
            $plugins[MEDORA_PLUGIN_BASENAME]['AuthorURI']  = (string) $branding['vendor_url'];
            $plugins[MEDORA_PLUGIN_BASENAME]['PluginURI']  = (string) ($branding['support_url'] ?: $branding['vendor_url']);
        }

        return $plugins;
    }

    /**
     * Publish the accent colour as a CSS custom property so the React
     * dashboard picks it up without a rebuild.
     */
    public function printAccentColor(): void
    {
        $accent = $this->get('accent_color', '#2f6df6');

        // Only accept a hex colour; anything else could break out of the style
        // block.
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $accent) !== 1) {
            return;
        }

        printf(
            '<style id="medora-branding">:root{--medora-accent:%s;}</style>',
            esc_attr($accent)
        );
    }
}
