<?php

namespace SignTeb\VideoHub\Seo;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Buffers URLs for the Indexing API.
 *
 * Google allows 200 publish calls a day per project, and a bulk import can
 * create dozens of videos at once — so imports enqueue and the hourly cron
 * drains a bounded batch instead of calling inline.
 */
class IndexingQueue
{
    private const OPTION     = 'stvh_indexing_queue';
    private const BATCH      = 20;
    private const MAX_QUEUED = 500;

    private Settings $settings;
    private IndexingClient $client;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->client   = new IndexingClient($this->settings);
    }

    public function register(): void
    {
        add_action('stvh_video_imported', [$this, 'on_video_changed'], 10, 1);
        add_action('stvh_video_updated', [$this, 'on_video_changed'], 10, 1);
        add_action('transition_post_status', [$this, 'on_status_change'], 10, 3);
    }

    public function on_video_changed(int $post_id): void
    {
        $this->enqueue($post_id);
    }

    /**
     * @param \WP_Post $post
     */
    public function on_status_change(string $new_status, string $old_status, $post): void
    {
        if (! $post instanceof \WP_Post || $post->post_type !== PostType::POST_TYPE) {
            return;
        }
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->enqueue($post->ID);
        }
    }

    public function enqueue(int $post_id): void
    {
        if (! $this->client->is_configured() || $post_id <= 0) {
            return;
        }
        if (get_post_status($post_id) !== 'publish') {
            return;
        }

        $queue = $this->queue();
        if (in_array($post_id, $queue, true)) {
            return;
        }

        $queue[] = $post_id;
        $this->save(array_slice($queue, -self::MAX_QUEUED));
    }

    /**
     * Drain one batch. Returns how many URLs were accepted by Google.
     */
    public function process(): int
    {
        if (! $this->client->is_configured()) {
            return 0;
        }

        $queue = $this->queue();
        if ($queue === []) {
            return 0;
        }

        $batch     = array_slice($queue, 0, self::BATCH);
        $remaining = array_slice($queue, count($batch));
        $sent      = 0;

        foreach ($batch as $post_id) {
            $url = (string) get_permalink($post_id);
            if ($url === '' || get_post_status($post_id) !== 'publish') {
                continue;
            }

            $result = $this->client->publish($url);
            if ($result['ok']) {
                $sent++;
                update_post_meta($post_id, '_stvh_indexed_at', current_time('mysql'));
            } else {
                // A failed URL goes to the back of the queue, not the bin.
                $remaining[] = $post_id;
            }
        }

        $this->save(array_values(array_unique($remaining)));

        return $sent;
    }

    /**
     * @return array<int,int>
     */
    public function queue(): array
    {
        $queue = get_option(self::OPTION, []);
        return is_array($queue) ? array_values(array_unique(array_map('intval', $queue))) : [];
    }

    public function count(): int
    {
        return count($this->queue());
    }

    /**
     * @param array<int,int> $queue
     */
    private function save(array $queue): void
    {
        update_option(self::OPTION, $queue, false);
    }

    public function clear(): void
    {
        delete_option(self::OPTION);
    }
}
