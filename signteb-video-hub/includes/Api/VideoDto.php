<?php

namespace SignTeb\VideoHub\Api;

use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalized video record. Every source maps its own payload into this shape,
 * so the sync manager never learns provider-specific field names.
 */
final class VideoDto
{
    public function __construct(
        public readonly string $source,
        public readonly string $source_id,
        public readonly string $title,
        public readonly string $description = '',
        public readonly string $thumbnail = '',
        public readonly string $embed_url = '',
        public readonly string $source_url = '',
        public readonly int $duration = 0,
        public readonly string $published_at = '',
        public readonly int $views = 0,
        /** @var array<int,string> */
        public readonly array $tags = [],
    ) {
    }

    /**
     * Stable fingerprint of everything sync would overwrite. Unchanged videos
     * are skipped, which keeps re-sync cheap and preserves manual edits.
     */
    public function hash(): string
    {
        return md5(implode('|', [
            $this->title,
            $this->description,
            $this->thumbnail,
            $this->embed_url,
            $this->duration,
            $this->published_at,
        ]));
    }

    public function is_valid(): bool
    {
        return $this->source_id !== '' && $this->title !== '';
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function from_array(string $source, array $data): self
    {
        return new self(
            source: $source,
            source_id: (string) ($data['source_id'] ?? ''),
            title: trim(wp_strip_all_tags((string) ($data['title'] ?? ''))),
            description: trim(wp_strip_all_tags((string) ($data['description'] ?? ''))),
            thumbnail: esc_url_raw((string) ($data['thumbnail'] ?? '')),
            embed_url: esc_url_raw((string) ($data['embed_url'] ?? '')),
            source_url: esc_url_raw((string) ($data['source_url'] ?? '')),
            duration: Format::parse_duration($data['duration'] ?? 0),
            published_at: Format::to_mysql_datetime($data['published_at'] ?? ''),
            views: (int) ($data['views'] ?? 0),
            tags: array_values(array_filter(array_map(
                static fn($t): string => trim(wp_strip_all_tags((string) $t)),
                is_array($data['tags'] ?? null) ? $data['tags'] : []
            ))),
        );
    }
}
