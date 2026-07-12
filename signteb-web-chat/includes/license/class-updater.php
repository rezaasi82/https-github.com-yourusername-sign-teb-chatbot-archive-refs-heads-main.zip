<?php
/**
 * SWC_Updater — WordPress auto-update client backed by the Medora Cloud feed.
 *
 * Reads GET {cloud}/v1/update/latest ({version,url,sha256}) and, when a newer
 * version is available AND the license is active/grace, offers the update
 * through the native WordPress "Plugins" screen. No third-party libraries.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Updater
{
    private const CACHE = 'swc_update_feed';

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }

    /**
     * @return array{version:string,url:string,sha256:string}|null
     */
    private function feed(): ?array
    {
        $url = $this->feed_url();
        if ($url === '') {
            return null;
        }
        $cached = get_transient(self::CACHE);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get($url, ['timeout' => 12]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['version'])) {
            return null;
        }
        $feed = [
            'version' => sanitize_text_field($data['version']),
            'url'     => esc_url_raw($data['url'] ?? ''),
            'sha256'  => sanitize_text_field($data['sha256'] ?? ''),
        ];
        set_transient(self::CACHE, $feed, 12 * HOUR_IN_SECONDS);
        return $feed;
    }

    private function feed_url(): string
    {
        $settings = new SWC_Settings();
        $explicit = esc_url_raw((string) $settings->get('update_feed_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }
        $endpoint = (string) $settings->get('cloud_endpoint', '');
        $parts    = $endpoint !== '' ? wp_parse_url($endpoint) : [];
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . '/v1/update/latest';
    }

    /**
     * @param object $transient
     * @return object
     */
    public function inject($transient)
    {
        if (! is_object($transient) || empty($transient->checked)) {
            return $transient;
        }
        $feed = $this->feed();
        if ($feed === null || $feed['url'] === '') {
            return $transient;
        }
        if (version_compare($feed['version'], SWC_VERSION, '<=')) {
            return $transient;
        }
        // Only offer downloads to entitled sites.
        if (! (new SWC_License_Manager())->is_active()) {
            return $transient;
        }

        $item = (object) [
            'slug'        => 'signteb-web-chat',
            'plugin'      => SWC_BASENAME,
            'new_version' => $feed['version'],
            'url'         => 'https://signteb.com',
            'package'     => $feed['url'],
            'tested'      => get_bloginfo('version'),
        ];
        $transient->response[SWC_BASENAME] = $item;
        return $transient;
    }

    /**
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'signteb-web-chat') {
            return $result;
        }
        $feed = $this->feed();
        if ($feed === null) {
            return $result;
        }
        return (object) [
            'name'          => 'Medora AI',
            'slug'          => 'signteb-web-chat',
            'version'       => $feed['version'],
            'author'        => 'رضا آسیابی',
            'homepage'      => 'https://signteb.com',
            'download_link' => $feed['url'],
            'sections'      => [
                'description' => __('دستیار هوشمند جذب و راهنمایی بیماران — به‌روزرسانی از Medora Cloud.', 'signteb-web-chat'),
            ],
        ];
    }
}
