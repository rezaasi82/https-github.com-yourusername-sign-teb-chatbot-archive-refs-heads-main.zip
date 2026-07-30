<?php

namespace SignTeb\VideoHub\Sync;

use SignTeb\VideoHub\Api\SourceManager;
use SignTeb\VideoHub\Api\VideoDto;
use SignTeb\VideoHub\Api\VideoSourceInterface;
use SignTeb\VideoHub\Cache\CacheManager;
use SignTeb\VideoHub\Core\Logger;
use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\AiQueueRepository;
use SignTeb\VideoHub\Db\SyncLogRepository;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pulls videos from every configured source and upserts them as posts.
 *
 * Edits made in WordPress win: on re-sync only provider-owned fields are
 * refreshed, and only when the source-side hash actually changed.
 */
class SyncManager
{
    private Settings $settings;
    private SourceManager $sources;
    private VideoRepository $videos;
    private SyncLogRepository $log;
    private AiQueueRepository $queue;
    private ThumbnailImporter $thumbnails;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->sources  = new SourceManager($this->settings);
        $this->videos   = new VideoRepository();
        $this->log      = new SyncLogRepository();
        $this->queue    = new AiQueueRepository();
        $this->thumbnails = new ThumbnailImporter($this->settings);
    }

    /**
     * Sync every configured source.
     *
     * @return array{ok:bool,imported:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function sync_all(): array
    {
        $totals = ['ok' => true, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $configured = $this->sources->configured();
        if ($configured === []) {
            $totals['ok']       = false;
            $totals['errors'][] = 'هیچ منبع ویدئویی تنظیم نشده است.';
            return $totals;
        }

        foreach ($configured as $source) {
            $result = $this->sync_source($source);
            $totals['imported'] += $result['imported'];
            $totals['updated']  += $result['updated'];
            $totals['skipped']  += $result['skipped'];
            if (! $result['ok']) {
                $totals['ok']       = false;
                $totals['errors'][] = $result['error'] ?? '';
            }
        }

        $totals['errors'] = array_values(array_filter($totals['errors']));
        update_option('stvh_last_sync', current_time('mysql'), false);

        if ($totals['imported'] > 0 || $totals['updated'] > 0) {
            (new CacheManager($this->settings))->purge_all();
        }

        return $totals;
    }

    /**
     * @return array{ok:bool,imported:int,updated:int,skipped:int,error?:string}
     */
    public function sync_source(VideoSourceInterface $source): array
    {
        $started = microtime(true);
        $limit   = max(1, $this->settings->int('sync_limit'));

        $fetched = $source->fetch($limit);
        if (! $fetched['ok']) {
            $error = (string) ($fetched['error'] ?? 'خطای نامشخص.');
            $this->log->add([
                'source'      => $source->id(),
                'status'      => 'failed',
                'imported'    => 0,
                'updated'     => 0,
                'skipped'     => 0,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'message'     => $error,
            ]);
            Logger::error('sync', $error, ['source' => $source->id()]);

            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'error' => $error];
        }

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($fetched['videos'] as $dto) {
            $outcome = $this->upsert($dto);
            match ($outcome) {
                'imported' => $imported++,
                'updated'  => $updated++,
                default    => $skipped++,
            };
        }

        $this->log->add([
            'source'      => $source->id(),
            'status'      => 'success',
            'imported'    => $imported,
            'updated'     => $updated,
            'skipped'     => $skipped,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'message'     => sprintf('%d ویدئوی دریافت‌شده.', count($fetched['videos'])),
        ]);

        return ['ok' => true, 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return string 'imported' | 'updated' | 'skipped'
     */
    public function upsert(VideoDto $dto): string
    {
        $existing = $this->videos->find_by_source($dto->source, $dto->source_id);

        if ($existing > 0) {
            if ((string) get_post_meta($existing, VideoMeta::CONTENT_HASH, true) === $dto->hash()) {
                return 'skipped';
            }
            $this->update_post($existing, $dto);
            $this->write_meta($existing, $dto);
            do_action('stvh_video_updated', $existing, $dto);
            return 'updated';
        }

        $post_id = wp_insert_post([
            'post_type'    => PostType::POST_TYPE,
            'post_status'  => $this->settings->bool('auto_publish') ? 'publish' : 'draft',
            'post_title'   => $dto->title,
            'post_content' => $dto->description,
            'post_excerpt' => wp_trim_words($dto->description, 40, '…'),
            'post_date'    => get_date_from_gmt($dto->published_at),
            'post_name'    => Format::slug($dto->title) ?: $dto->source . '-' . $dto->source_id,
        ], true);

        if (is_wp_error($post_id)) {
            Logger::error('sync', $post_id->get_error_message(), ['source_id' => $dto->source_id]);
            return 'skipped';
        }

        $post_id = (int) $post_id;
        $this->write_meta($post_id, $dto);
        $this->assign_topics($post_id, $dto);
        // Before the AI queue: the composed body and the OpenGraph image both
        // want a local attachment to already exist.
        $this->thumbnails->import($post_id);
        $this->enqueue_ai($post_id);

        /**
         * Fires after a new video is imported. Used by the Google indexing
         * queue and available to integrations.
         */
        do_action('stvh_video_imported', $post_id, $dto);

        return 'imported';
    }

    /**
     * Only provider-owned fields are refreshed; the editor's own excerpt,
     * topics and featured image survive.
     */
    private function update_post(int $post_id, VideoDto $dto): void
    {
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $dto->title,
        ]);
    }

    private function write_meta(int $post_id, VideoDto $dto): void
    {
        update_post_meta($post_id, VideoMeta::SOURCE, $dto->source);
        update_post_meta($post_id, VideoMeta::SOURCE_ID, $dto->source_id);
        update_post_meta($post_id, VideoMeta::SOURCE_URL, $dto->source_url);
        update_post_meta($post_id, VideoMeta::EMBED_URL, $dto->embed_url);
        update_post_meta($post_id, VideoMeta::THUMBNAIL, $dto->thumbnail);
        update_post_meta($post_id, VideoMeta::DURATION, $dto->duration);
        update_post_meta($post_id, VideoMeta::PUBLISHED_AT, $dto->published_at);
        update_post_meta($post_id, VideoMeta::SOURCE_VIEWS, $dto->views);
        update_post_meta($post_id, VideoMeta::CONTENT_HASH, $dto->hash());
    }

    /**
     * Map provider tags onto existing topic terms. New terms are never created
     * from tags — the topic set is an editorial decision (feature 17).
     */
    private function assign_topics(int $post_id, VideoDto $dto): void
    {
        $haystack = mb_strtolower($dto->title . ' ' . $dto->description . ' ' . implode(' ', $dto->tags));
        $matched  = [];

        $terms = get_terms(['taxonomy' => PostType::TAXONOMY, 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $needles = array_filter([$term->slug, mb_strtolower($term->name)]);
            /**
             * Extra keywords that should map onto a topic term.
             *
             * @param array<int,string> $needles
             */
            $needles = apply_filters('stvh_topic_keywords', $needles, $term, $dto);

            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, mb_strtolower((string) $needle))) {
                    $matched[] = (int) $term->term_id;
                    break;
                }
            }
        }

        if ($matched !== []) {
            wp_set_object_terms($post_id, array_unique($matched), PostType::TAXONOMY, false);
        }
    }

    private function enqueue_ai(int $post_id): void
    {
        if (! $this->settings->bool('ai_enabled')) {
            return;
        }
        if ($this->settings->bool('ai_auto_summary')) {
            $this->queue->enqueue($post_id, 'summary');
        }
        if ($this->settings->bool('ai_auto_links')) {
            $this->queue->enqueue($post_id, 'links');
        }
        if ($this->settings->bool('ai_auto_article')) {
            $this->queue->enqueue($post_id, 'article');
        }
    }

    public function last_sync(): string
    {
        return (string) get_option('stvh_last_sync', '');
    }
}
