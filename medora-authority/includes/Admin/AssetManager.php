<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads built assets, and only on Medora's own screens.
 *
 * The build emits a PHP asset manifest (`*.asset.php`) alongside each bundle
 * containing its dependency list and a content hash. Reading that rather than
 * hardcoding dependencies means the WordPress packages the app actually
 * imports are enqueued, and cache busting is automatic.
 */
final class AssetManager
{
    public const HANDLE_APP = 'medora-app';

    public function enqueueApp(string $screenId): void
    {
        $asset = $this->manifest('app');

        wp_enqueue_script(
            self::HANDLE_APP,
            MEDORA_PLUGIN_URL . 'assets/js/app.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            self::HANDLE_APP,
            MEDORA_PLUGIN_URL . 'assets/css/app.css',
            ['wp-components'],
            $asset['version']
        );

        // The dashboard is RTL-aware through logical CSS properties, but
        // WordPress still expects an explicit RTL registration for its own
        // stylesheet handling.
        wp_style_add_data(self::HANDLE_APP, 'rtl', 'replace');

        wp_set_script_translations(self::HANDLE_APP, 'medora-authority', MEDORA_PLUGIN_DIR . 'languages');

        wp_add_inline_script(
            self::HANDLE_APP,
            sprintf(
                'window.medoraBoot = %s;',
                wp_json_encode($this->bootData($screenId))
            ),
            'before'
        );
    }

    public function enqueueEditor(): void
    {
        $asset = $this->manifest('editor');

        wp_enqueue_script(
            'medora-editor',
            MEDORA_PLUGIN_URL . 'assets/js/editor.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'medora-editor',
            MEDORA_PLUGIN_URL . 'assets/css/editor.css',
            [],
            $asset['version']
        );

        wp_add_inline_script(
            'medora-editor',
            sprintf('window.medoraBoot = %s;', wp_json_encode($this->bootData('editor'))),
            'before'
        );
    }

    /**
     * Everything the app needs to make its first request, inlined so the
     * dashboard does not spend a round trip discovering its own endpoints.
     *
     * @return array<string, mixed>
     */
    private function bootData(string $screenId): array
    {
        $data = [
            'restUrl'   => esc_url_raw(rest_url('medora/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'adminUrl'  => esc_url_raw(admin_url('admin.php?page=medora')),
            'screen'    => $screenId,
            'version'   => MEDORA_VERSION,
            'locale'    => str_replace('_', '-', (string) get_user_locale()),
            'isRtl'     => is_rtl(),
            'postId'    => (int) (get_the_ID() ?: 0),
            'capabilities' => [
                'manage'   => current_user_can(\Medora\Authority\Core\Capabilities::MANAGE_SETTINGS),
                'analyze'  => current_user_can(\Medora\Authority\Core\Capabilities::RUN_ANALYSIS),
                'entities' => current_user_can(\Medora\Authority\Core\Capabilities::MANAGE_ENTITIES),
                'audit'    => current_user_can(\Medora\Authority\Core\Capabilities::VIEW_AUDIT_LOG),
            ],
        ];

        /**
         * Filter the data bootstrapped into the dashboard.
         *
         * @param array<string, mixed> $data
         * @param string               $screenId
         */
        return (array) apply_filters('medora_boot_data', $data, $screenId);
    }

    /**
     * @return array{dependencies: list<string>, version: string}
     */
    private function manifest(string $bundle): array
    {
        $path = MEDORA_PLUGIN_DIR . 'assets/js/' . $bundle . '.asset.php';

        if (is_readable($path)) {
            /** @var array{dependencies?: list<string>, version?: string} $manifest */
            $manifest = require $path;

            return [
                'dependencies' => (array) ($manifest['dependencies'] ?? []),
                'version'      => (string) ($manifest['version'] ?? MEDORA_VERSION),
            ];
        }

        // Running from source without a build: fall back to the packages the
        // app is known to need so development still works.
        return [
            'dependencies' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data'],
            'version'      => MEDORA_VERSION,
        ];
    }
}
