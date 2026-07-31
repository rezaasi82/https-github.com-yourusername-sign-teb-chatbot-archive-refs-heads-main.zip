<?php

namespace SignTeb\VideoHub\Api\Aparat;

use SignTeb\VideoHub\Api\VideoDto;
use SignTeb\VideoHub\Api\PlaylistAwareInterface;
use SignTeb\VideoHub\Api\VideoSourceInterface;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps Aparat payloads onto VideoDto. Field names differ between the v1 and
 * legacy endpoints, so every read goes through pick() with a candidate list.
 */
class AparatSource implements VideoSourceInterface, PlaylistAwareInterface
{
    public const ID = 'aparat';

    private Settings $settings;
    private AparatClient $client;

    public function __construct(?Settings $settings = null, ?AparatClient $client = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->client   = $client ?? new AparatClient();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return __('آپارات', 'signteb-video-hub');
    }

    public function is_configured(): bool
    {
        return $this->username() !== '';
    }

    public function username(): string
    {
        return AparatClient::normalize_username($this->settings->str('aparat_username'));
    }

    public function fetch(int $limit): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'videos' => [], 'error' => 'شناسه کانال آپارات تنظیم نشده است.'];
        }

        $playlist = $this->selected_playlist();

        if ($playlist !== '') {
            $raw = $this->client->videos_by_playlist($playlist, $limit);

            // A renamed or deleted playlist must not silently import nothing;
            // falling back to the channel keeps the site populated and the
            // error is surfaced by the connection test.
            if (! $raw['ok']) {
                Logger::warning('aparat', (string) ($raw['error'] ?? ''), ['playlist' => $playlist]);
                $raw = $this->client->videos_by_username($this->username(), $limit);
            }
        } else {
            $raw = $this->client->videos_by_username($this->username(), $limit);
        }

        if (! $raw['ok']) {
            return ['ok' => false, 'videos' => [], 'error' => $raw['error'] ?? 'خطای نامشخص آپارات.'];
        }

        $videos = [];
        foreach ($raw['items'] as $item) {
            $dto = $this->to_dto($item);
            if ($dto !== null && $dto->is_valid()) {
                $videos[] = $dto;
            }
        }

        return ['ok' => true, 'videos' => $videos];
    }

    public function selected_playlist(): string
    {
        return trim((string) $this->settings->get('aparat_playlist', ''));
    }

    public function playlists(): array
    {
        return $this->client->playlists_by_username($this->username());
    }

    public function test_connection(): array
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'message' => 'شناسه کانال آپارات تنظیم نشده است.'];
        }
        return $this->client->test_channel($this->username());
    }

    /**
     * @param array<string,mixed> $item
     */
    private function to_dto(array $item): ?VideoDto
    {
        $uid = (string) $this->pick($item, ['uid', 'hash', 'videohash', 'id']);
        if ($uid === '') {
            return null;
        }

        return VideoDto::from_array(self::ID, [
            'source_id'    => $uid,
            'title'        => $this->pick($item, ['title', 'name']),
            'description'  => $this->pick($item, ['description', 'descr', 'summary']),
            'thumbnail'    => $this->pick($item, ['big_poster', 'poster', 'preview_src', 'small_poster']),
            'embed_url'    => $this->embed_url($item, $uid),
            'source_url'   => $this->watch_url($item, $uid),
            'duration'     => $this->pick($item, ['duration', 'time', 'length']),
            'published_at' => $this->pick($item, ['create_date', 'sdate_rss', 'sdate_timediff', 'sdate', 'date']),
            'views'        => (int) $this->pick($item, ['visit_cnt_int', 'visit_cnt', 'visit']),
            'tags'         => $this->tags($item),
        ]);
    }

    /**
     * Aparat sometimes hands back a full <iframe> in `frame`; we only ever want
     * the src, because the markup carries its own sizing we cannot control.
     *
     * @param array<string,mixed> $item
     */
    private function embed_url(array $item, string $uid): string
    {
        $frame = (string) $this->pick($item, ['frame', 'embed', 'embed_url']);
        if ($frame !== '' && preg_match('/src=["\']([^"\']+)["\']/i', $frame, $m)) {
            return $m[1];
        }
        if (str_starts_with($frame, 'http')) {
            return $frame;
        }
        return sprintf('https://www.aparat.com/video/video/embed/videohash/%s/vt/frame', rawurlencode($uid));
    }

    /**
     * @param array<string,mixed> $item
     */
    private function watch_url(array $item, string $uid): string
    {
        $url = (string) $this->pick($item, ['url', 'link', 'permalink']);
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return 'https://www.aparat.com/v/' . rawurlencode($uid);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<int,string>
     */
    private function tags(array $item): array
    {
        $tags = $item['tags'] ?? $item['tag'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[,،]/', $tags) ?: [];
        }
        if (! is_array($tags)) {
            return [];
        }

        $out = [];
        foreach ($tags as $tag) {
            // v1 returns objects like {name: "کبد"}; legacy returns strings.
            $value = is_array($tag) ? ($tag['name'] ?? $tag['title'] ?? '') : $tag;
            $value = trim((string) $value);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * First non-empty value among the candidate keys.
     *
     * @param array<string,mixed> $item
     * @param array<int,string>   $keys
     */
    private function pick(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null) {
                return $item[$key];
            }
        }
        return '';
    }
}
