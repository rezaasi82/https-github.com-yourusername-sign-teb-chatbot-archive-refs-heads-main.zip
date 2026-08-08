<?php

namespace SignTeb\VideoHub\Sync;

use SignTeb\VideoHub\Ai\ContentComposer;
use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Brings already-imported videos up to the current import rules.
 *
 * Fixes applied later only help the next sync — and sync deliberately skips
 * unchanged videos, so an existing library would keep its bad slugs, missing
 * featured images and empty bodies forever. This backfills them in bounded
 * batches so a large library cannot time out a single request.
 */
class Repair
{
    private const BATCH = 10;

    private Settings $settings;
    private VideoRepository $videos;
    private ThumbnailImporter $thumbnails;
    private ContentComposer $composer;

    public function __construct(?Settings $settings = null)
    {
        $this->settings   = $settings ?? new Settings();
        $this->videos     = new VideoRepository();
        $this->thumbnails = new ThumbnailImporter($this->settings);
        $this->composer   = new ContentComposer($this->settings);
    }

    /**
     * Repair one batch, newest first.
     *
     * @return array{processed:int,slugs:int,thumbnails:int,bodies:int,remaining:int}
     */
    public function run(int $limit = self::BATCH): array
    {
        $limit  = max(1, min(50, $limit));
        $result = ['processed' => 0, 'slugs' => 0, 'thumbnails' => 0, 'bodies' => 0, 'remaining' => 0];

        $done  = $this->completed();
        $ids   = $this->videos->all_published_ids();
        $queue = array_values(array_diff($ids, $done));

        foreach (array_slice($queue, 0, $limit) as $post_id) {
            if ($this->repair_slug($post_id)) {
                $result['slugs']++;
            }
            if ($this->thumbnails->import($post_id) > 0) {
                $result['thumbnails']++;
            }
            if ($this->composer->maybe_write($post_id)) {
                $result['bodies']++;
            }

            $done[] = $post_id;
            $result['processed']++;
        }

        $this->save_completed($done);
        $result['remaining'] = max(0, count($queue) - $result['processed']);

        return $result;
    }

    /**
     * Rewrite a slug that still carries punctuation or an author suffix.
     *
     * WordPress stores slugs percent-encoded for non-ASCII, so the comparison
     * happens on the decoded value — otherwise every Persian slug would look
     * "wrong" and be rewritten on every pass.
     */
    private function repair_slug(int $post_id): bool
    {
        $current = rawurldecode((string) get_post_field('post_name', $post_id));
        $wanted  = Format::slug((string) get_the_title($post_id));

        if ($wanted === '' || $current === '') {
            return false;
        }
        if ($current === rawurldecode($wanted)) {
            return false;
        }

        // Only intervene when the existing slug is genuinely unclean: contains
        // punctuation, or is far longer than the cleaned form. A slug an editor
        // shortened by hand must survive.
        $has_punctuation = (bool) preg_match('/[^\p{L}\p{N}\-]/u', $current);
        $is_overlong     = mb_strlen($current) > mb_strlen(rawurldecode($wanted)) + 12;

        if (! $has_punctuation && ! $is_overlong) {
            return false;
        }

        $updated = wp_update_post([
            'ID'        => $post_id,
            'post_name' => $wanted,
        ], true);

        return ! is_wp_error($updated);
    }

    /**
     * How much work is left, for the dashboard.
     */
    public function pending(): int
    {
        return max(0, count($this->videos->all_published_ids()) - count($this->completed()));
    }

    /**
     * @return array<int,int>
     */
    private function completed(): array
    {
        $done = get_option('stvh_repaired', []);

        return is_array($done) ? array_map('intval', $done) : [];
    }

    /**
     * @param array<int,int> $done
     */
    private function save_completed(array $done): void
    {
        update_option('stvh_repaired', array_values(array_unique($done)), false);
    }

    /**
     * Forget the progress marker so the whole library is reprocessed.
     */
    public function reset(): void
    {
        delete_option('stvh_repaired');
    }

    /**
     * Repair a single video on demand, from its editor screen.
     *
     * @return array{slug:bool,thumbnail:bool,body:bool}
     */
    public function run_one(int $post_id): array
    {
        if (get_post_type($post_id) !== PostType::POST_TYPE) {
            return ['slug' => false, 'thumbnail' => false, 'body' => false];
        }

        return [
            'slug'      => $this->repair_slug($post_id),
            'thumbnail' => $this->thumbnails->import($post_id) > 0,
            'body'      => $this->composer->maybe_write($post_id),
        ];
    }
}
