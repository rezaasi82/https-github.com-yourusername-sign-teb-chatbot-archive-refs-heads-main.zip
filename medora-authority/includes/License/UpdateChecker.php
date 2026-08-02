<?php

declare(strict_types=1);

namespace Medora\Authority\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Self-hosted update channel.
 *
 * A commercial plugin cannot use the wordpress.org update path, so it hooks
 * the transient WordPress builds when it checks for updates and injects its
 * own package URL. The URL is only produced for an active licence, which is
 * what makes the entitlement enforceable without breaking an expired site.
 */
final class UpdateChecker
{
    private const TRANSIENT = 'medora_update_info';
    private const TTL       = 12 * HOUR_IN_SECONDS;

    public function __construct(private readonly LicenseManager $license)
    {
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_filter('plugins_api', [$this, 'pluginInformation'], 20, 3);

        // A licence change can change entitlement to an update, so the cached
        // response must not outlive it.
        add_action('medora_license_status_changed', static function (): void {
            delete_transient(self::TRANSIENT);
        });
    }

    public function injectUpdate(mixed $transient): mixed
    {
        if (! is_object($transient) || ! isset($transient->response)) {
            return $transient;
        }

        $remote = $this->remoteInfo();

        if ($remote === null || version_compare(MEDORA_VERSION, (string) $remote['version'], '>=')) {
            return $transient;
        }

        $update = (object) [
            'slug'         => dirname(MEDORA_PLUGIN_BASENAME),
            'plugin'       => MEDORA_PLUGIN_BASENAME,
            'new_version'  => (string) $remote['version'],
            'url'          => (string) ($remote['homepage'] ?? ''),
            'package'      => (string) ($remote['package'] ?? ''),
            'tested'       => (string) ($remote['tested'] ?? ''),
            'requires_php' => (string) ($remote['requires_php'] ?? '8.2'),
        ];

        // With no package URL the core updater shows the notice but the update
        // will fail, so an unlicensed site is told why instead.
        if ($update->package === '') {
            $transient->no_update[MEDORA_PLUGIN_BASENAME] = $update;

            return $transient;
        }

        $transient->response[MEDORA_PLUGIN_BASENAME] = $update;

        return $transient;
    }

    public function pluginInformation(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== dirname(MEDORA_PLUGIN_BASENAME)) {
            return $result;
        }

        $remote = $this->remoteInfo();

        if ($remote === null) {
            return $result;
        }

        return (object) [
            'name'          => 'Medora Authority',
            'slug'          => dirname(MEDORA_PLUGIN_BASENAME),
            'version'       => (string) $remote['version'],
            'requires'      => (string) ($remote['requires'] ?? '6.4'),
            'requires_php'  => (string) ($remote['requires_php'] ?? '8.2'),
            'author'        => 'Medora',
            'homepage'      => (string) ($remote['homepage'] ?? ''),
            'download_link' => (string) ($remote['package'] ?? ''),
            'sections'      => [
                'description' => (string) ($remote['description'] ?? ''),
                'changelog'   => (string) ($remote['changelog'] ?? ''),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function remoteInfo(): ?array
    {
        $cached = get_transient(self::TRANSIENT);

        if (is_array($cached)) {
            return $cached;
        }

        $state = $this->license->state();

        $response = wp_remote_get(
            add_query_arg(
                [
                    'version' => MEDORA_VERSION,
                    'key'     => (string) $state['key'],
                    'domain'  => hash('sha256', strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))),
                ],
                (string) apply_filters('medora_update_endpoint', 'https://license.medora.ai/v1/update')
            ),
            ['timeout' => 12, 'headers' => ['Accept' => 'application/json']]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Cache the miss briefly so a down endpoint does not add an HTTP
            // round trip to every admin page load.
            set_transient(self::TRANSIENT, ['version' => MEDORA_VERSION], 30 * MINUTE_IN_SECONDS);

            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! isset($body['version'])) {
            return null;
        }

        set_transient(self::TRANSIENT, $body, self::TTL);

        return $body;
    }
}
