<?php

namespace SignTeb\VideoHub\Api\Youtube;

use SignTeb\VideoHub\Api\VideoDto;
use SignTeb\VideoHub\Api\VideoSourceInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Helpers\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Data API v3 source.
 *
 * Reads the channel's "uploads" playlist rather than search.list: it costs
 * 1 quota unit instead of 100 and returns items in true upload order.
 */
class YoutubeSource implements VideoSourceInterface
{
    public const ID = 'youtube';

    private const API_BASE = 'https://www.googleapis.com/youtube/v3/';
    private const TIMEOUT  = 20;

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return __('یوتیوب', 'signteb-video-hub');
    }

    public function is_configured(): bool
    {
        return $this->settings->str('youtube_channel') !== '' && $this->api_key() !== '';
    }

    private function api_key(): string
    {
        return $this->settings->secret('youtube_api_key');
    }

    public function fetch(int $limit): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'videos' => [], 'error' => 'کانال یا کلید API یوتیوب تنظیم نشده است.'];
        }

        $playlist = $this->uploads_playlist_id();
        if ($playlist === '') {
            return ['ok' => false, 'videos' => [], 'error' => 'پلی‌لیست آپلودهای کانال پیدا نشد.'];
        }

        $limit = max(1, min(50, $limit));
        $items = $this->get('playlistItems', [
            'part'       => 'snippet,contentDetails',
            'playlistId' => $playlist,
            'maxResults' => $limit,
        ]);

        if (! $items['ok']) {
            return ['ok' => false, 'videos' => [], 'error' => $items['error'] ?? 'خطای یوتیوب.'];
        }

        $entries = is_array($items['body']['items'] ?? null) ? $items['body']['items'] : [];
        $ids     = [];
        foreach ($entries as $entry) {
            $id = (string) Json::dig($entry, 'contentDetails.videoId', '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        // Durations and view counts only exist on videos.list.
        $details = $ids === [] ? [] : $this->video_details($ids);

        $videos = [];
        foreach ($entries as $entry) {
            $id = (string) Json::dig($entry, 'contentDetails.videoId', '');
            if ($id === '') {
                continue;
            }
            $detail = $details[$id] ?? [];
            $dto    = VideoDto::from_array(self::ID, [
                'source_id'    => $id,
                'title'        => (string) Json::dig($entry, 'snippet.title', ''),
                'description'  => (string) Json::dig($entry, 'snippet.description', ''),
                'thumbnail'    => $this->best_thumbnail((array) Json::dig($entry, 'snippet.thumbnails', [])),
                'embed_url'    => 'https://www.youtube.com/embed/' . $id,
                'source_url'   => 'https://www.youtube.com/watch?v=' . $id,
                'duration'     => (string) Json::dig($detail, 'contentDetails.duration', ''),
                'published_at' => (string) Json::dig($entry, 'snippet.publishedAt', ''),
                'views'        => (int) Json::dig($detail, 'statistics.viewCount', 0),
                'tags'         => (array) Json::dig($detail, 'snippet.tags', []),
            ]);
            if ($dto->is_valid()) {
                $videos[] = $dto;
            }
        }

        return ['ok' => true, 'videos' => $videos];
    }

    public function test_connection(): array
    {
        if ($this->api_key() === '') {
            return ['ok' => false, 'message' => 'کلید API یوتیوب وارد نشده است.'];
        }
        if ($this->settings->str('youtube_channel') === '') {
            return ['ok' => false, 'message' => 'شناسه کانال یوتیوب وارد نشده است.'];
        }
        $playlist = $this->uploads_playlist_id();
        return $playlist !== ''
            ? ['ok' => true, 'message' => 'اتصال به یوتیوب برقرار است.']
            : ['ok' => false, 'message' => 'کانال یوتیوب پیدا نشد یا کلید API معتبر نیست.'];
    }

    /**
     * Resolve channel id / @handle / custom name to the uploads playlist id,
     * cached for a day because it never changes for a given channel.
     */
    private function uploads_playlist_id(): string
    {
        $channel   = $this->settings->str('youtube_channel');
        $cache_key = 'stvh_yt_uploads_' . md5($channel);
        $cached    = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $params = ['part' => 'contentDetails'];
        if (str_starts_with($channel, 'UC') && strlen($channel) === 24) {
            $params['id'] = $channel;
        } elseif (str_starts_with($channel, '@')) {
            $params['forHandle'] = $channel;
        } else {
            $params['forUsername'] = $channel;
        }

        $response = $this->get('channels', $params);
        if (! $response['ok']) {
            return '';
        }

        $playlist = (string) Json::dig(
            $response['body'],
            'items.0.contentDetails.relatedPlaylists.uploads',
            ''
        );

        if ($playlist !== '') {
            set_transient($cache_key, $playlist, DAY_IN_SECONDS);
        }

        return $playlist;
    }

    /**
     * @param array<int,string> $ids
     * @return array<string,array<string,mixed>> keyed by video id
     */
    private function video_details(array $ids): array
    {
        $response = $this->get('videos', [
            'part' => 'contentDetails,statistics,snippet',
            'id'   => implode(',', array_slice($ids, 0, 50)),
        ]);

        if (! $response['ok']) {
            return [];
        }

        $out = [];
        foreach ((array) ($response['body']['items'] ?? []) as $item) {
            if (is_array($item) && isset($item['id'])) {
                $out[(string) $item['id']] = $item;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $thumbnails
     */
    private function best_thumbnail(array $thumbnails): string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
            $url = Json::dig($thumbnails, $size . '.url', '');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }
        return '';
    }

    /**
     * @param array<string,scalar> $params
     * @return array{ok:bool,body:array<mixed>,error?:string}
     */
    private function get(string $endpoint, array $params): array
    {
        $params['key'] = $this->api_key();
        $url           = self::API_BASE . $endpoint . '?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            Logger::error('youtube', $response->get_error_message(), ['endpoint' => $endpoint]);
            return ['ok' => false, 'body' => [], 'error' => $response->get_error_message()];
        }

        $body = Json::decode((string) wp_remote_retrieve_body($response));
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $message = (string) Json::dig($body, 'error.message', sprintf('یوتیوب کد %d برگرداند.', $code));
            Logger::error('youtube', $message, ['endpoint' => $endpoint, 'code' => $code]);
            return ['ok' => false, 'body' => $body, 'error' => $message];
        }

        return ['ok' => true, 'body' => $body];
    }
}
