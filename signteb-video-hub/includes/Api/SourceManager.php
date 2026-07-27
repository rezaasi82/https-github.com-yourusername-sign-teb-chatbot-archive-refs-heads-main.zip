<?php

namespace SignTeb\VideoHub\Api;

use SignTeb\VideoHub\Api\Aparat\AparatSource;
use SignTeb\VideoHub\Api\Youtube\YoutubeSource;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registry of video sources. The `stvh_video_sources` filter is the extension
 * point for third-party providers.
 */
class SourceManager
{
    /** @var array<string,VideoSourceInterface>|null */
    private ?array $sources = null;

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    /**
     * @return array<string,VideoSourceInterface>
     */
    public function all(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [
            AparatSource::ID  => new AparatSource($this->settings),
            YoutubeSource::ID => new YoutubeSource($this->settings),
        ];

        /**
         * Register additional video sources.
         *
         * @param array<string,VideoSourceInterface> $sources
         */
        $filtered = apply_filters('stvh_video_sources', $sources);

        $this->sources = array_filter(
            is_array($filtered) ? $filtered : $sources,
            static fn($source): bool => $source instanceof VideoSourceInterface
        );

        return $this->sources;
    }

    public function get(string $id): ?VideoSourceInterface
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * Sources that have credentials and can actually be synced.
     *
     * @return array<string,VideoSourceInterface>
     */
    public function configured(): array
    {
        return array_filter($this->all(), static fn(VideoSourceInterface $s): bool => $s->is_configured());
    }

    /**
     * Connection status for every source, for the dashboard panel.
     *
     * @return array<string,array{label:string,configured:bool,ok:bool,message:string}>
     */
    public function status(): array
    {
        $out = [];
        foreach ($this->all() as $id => $source) {
            if (! $source->is_configured()) {
                $out[$id] = [
                    'label'      => $source->label(),
                    'configured' => false,
                    'ok'         => false,
                    'message'    => __('تنظیم نشده', 'signteb-video-hub'),
                ];
                continue;
            }
            $probe    = $source->test_connection();
            $out[$id] = [
                'label'      => $source->label(),
                'configured' => true,
                'ok'         => (bool) $probe['ok'],
                'message'    => (string) $probe['message'],
            ];
        }
        return $out;
    }
}
