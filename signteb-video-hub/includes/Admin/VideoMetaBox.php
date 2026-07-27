<?php

namespace SignTeb\VideoHub\Admin;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\AnalyticsRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-video editor panel: source details, AI output and the manual
 * "regenerate" / "publish article" / "ping Google" actions.
 */
class VideoMetaBox
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add']);
        add_filter('manage_' . PostType::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . PostType::POST_TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
    }

    public function add(): void
    {
        add_meta_box(
            'stvh_video_details',
            __('اطلاعات ویدئو و هوش مصنوعی', 'signteb-video-hub'),
            [$this, 'render'],
            PostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function render($post): void
    {
        $post_id = (int) $post->ID;

        $data = [
            'post_id'   => $post_id,
            'source'    => VideoMeta::source($post_id),
            'source_id' => VideoMeta::source_id($post_id),
            'source_url' => VideoMeta::source_url($post_id),
            'embed'     => VideoMeta::embed_url($post_id),
            'thumbnail' => VideoMeta::thumbnail($post_id),
            'duration'  => VideoMeta::duration_human($post_id),
            'summary'   => VideoMeta::summary($post_id),
            'keypoints' => VideoMeta::keypoints($post_id),
            'faq'       => VideoMeta::faq($post_id),
            'links'     => VideoMeta::links($post_id),
            'article'   => VideoMeta::article($post_id),
            'ai_status' => (string) get_post_meta($post_id, VideoMeta::AI_STATUS, true),
            'ai_ready'  => $this->settings->bool('ai_enabled') && $this->settings->has_secret('ai_api_key'),
            'indexed_at' => (string) get_post_meta($post_id, '_stvh_indexed_at', true),
        ];

        require STVH_DIR . 'includes/Admin/views/video-metabox.php';
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function columns(array $columns): array
    {
        $insert_before = 'date';
        $new           = [];

        foreach ($columns as $key => $label) {
            if ($key === $insert_before) {
                $new['stvh_source']   = __('منبع', 'signteb-video-hub');
                $new['stvh_duration'] = __('مدت', 'signteb-video-hub');
                $new['stvh_ai']       = __('هوش مصنوعی', 'signteb-video-hub');
                $new['stvh_plays']    = __('پخش (۳۰ روز)', 'signteb-video-hub');
            }
            $new[$key] = $label;
        }

        return $new;
    }

    public function column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'stvh_source':
                $source = VideoMeta::source($post_id);
                $url    = VideoMeta::source_url($post_id);
                if ($source === '') {
                    echo '—';
                    break;
                }
                if ($url !== '') {
                    printf(
                        '<a href="%s" target="_blank" rel="noopener">%s</a>',
                        esc_url($url),
                        esc_html($source)
                    );
                } else {
                    echo esc_html($source);
                }
                break;

            case 'stvh_duration':
                $duration = VideoMeta::duration_human($post_id);
                echo esc_html($duration !== '' ? $duration : '—');
                break;

            case 'stvh_ai':
                if (VideoMeta::has_ai($post_id)) {
                    echo '<span class="stvh-badge stvh-badge--ok">' . esc_html__('آماده', 'signteb-video-hub') . '</span>';
                } elseif ((string) get_post_meta($post_id, VideoMeta::AI_STATUS, true) === 'failed') {
                    echo '<span class="stvh-badge stvh-badge--err">' . esc_html__('ناموفق', 'signteb-video-hub') . '</span>';
                } else {
                    echo '<span class="stvh-badge">' . esc_html__('در انتظار', 'signteb-video-hub') . '</span>';
                }
                break;

            case 'stvh_plays':
                echo esc_html(number_format_i18n((int) get_post_meta($post_id, '_stvh_views_30d', true)));
                break;
        }
    }

    /**
     * Play/CTR numbers for the metabox footer.
     *
     * @return array{plays:int,clicks:int,impressions:int,watch_seconds:int,ctr:float}|null
     */
    public static function stats_for(int $post_id): ?array
    {
        foreach ((new AnalyticsRepository())->top_videos(30, 200) as $row) {
            if ($row['video_id'] === $post_id) {
                return [
                    'plays'         => $row['plays'],
                    'clicks'        => $row['clicks'],
                    'impressions'   => $row['impressions'],
                    'watch_seconds' => $row['watch_seconds'],
                    'ctr'           => $row['ctr'],
                ];
            }
        }
        return null;
    }
}
