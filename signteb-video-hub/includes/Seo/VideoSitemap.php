<?php

namespace SignTeb\VideoHub\Seo;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Helpers\Format;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 12 — /video-sitemap.xml.
 *
 * Served through a rewrite rule rather than a physical file so it is always
 * current and needs no write access to the web root. It is also announced in
 * robots.txt, which is how Search Console discovers it without manual
 * submission.
 */
class VideoSitemap
{
    public const QUERY_VAR = 'stvh_sitemap';
    public const PATH      = 'video-sitemap.xml';

    private Settings $settings;
    private VideoRepository $videos;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->videos   = new VideoRepository();
    }

    public function register(): void
    {
        if (! $this->settings->bool('sitemap_enabled')) {
            return;
        }

        add_action('init', [$this, 'add_rewrite']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'maybe_render']);
        add_filter('robots_txt', [$this, 'announce'], 10, 2);
    }

    public function add_rewrite(): void
    {
        add_rewrite_rule('^' . self::PATH . '$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function add_query_var(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function url(): string
    {
        return home_url('/' . self::PATH);
    }

    public function announce(string $output, bool $public): string
    {
        if ($public) {
            $output .= "\nSitemap: " . self::url() . "\n";
        }
        return $output;
    }

    public function maybe_render(): void
    {
        if ((string) get_query_var(self::QUERY_VAR) !== '1') {
            return;
        }

        $xml = get_transient('stvh_sitemap_xml');
        if (! is_string($xml) || $xml === '') {
            $xml = $this->build();
            set_transient('stvh_sitemap_xml', $xml, 6 * HOUR_IN_SECONDS);
        }

        header('Content-Type: application/xml; charset=UTF-8', true, 200);
        header('X-Robots-Tag: noindex, follow', true);
        // Raw XML document — escaping happens per-node inside build().
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function build(): string
    {
        $ids = $this->videos->all_published_ids();

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

        foreach ($ids as $id) {
            $node = $this->url_node($id);
            if ($node !== '') {
                $lines[] = $node;
            }
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }

    private function url_node(int $post_id): string
    {
        $data = VideoMeta::seo_payload($post_id);

        // Google requires a thumbnail plus either a content or player URL.
        if ($data['thumbnail'] === '' || ($data['embed'] === '' && VideoMeta::source_url($post_id) === '')) {
            return '';
        }

        $description = wp_trim_words($data['description'] !== '' ? $data['description'] : $data['title'], 60, '…');

        $parts   = [];
        $parts[] = '  <url>';
        $parts[] = '    <loc>' . esc_url($data['url']) . '</loc>';
        $parts[] = '    <video:video>';
        $parts[] = '      <video:thumbnail_loc>' . esc_url($data['thumbnail']) . '</video:thumbnail_loc>';
        $parts[] = '      <video:title>' . $this->cdata($data['title']) . '</video:title>';
        $parts[] = '      <video:description>' . $this->cdata($description) . '</video:description>';

        if ($data['embed'] !== '') {
            $parts[] = '      <video:player_loc allow_embed="yes">' . esc_url($data['embed']) . '</video:player_loc>';
        }
        if ($data['duration'] > 0) {
            // Google caps the declared duration at 8 hours.
            $parts[] = '      <video:duration>' . min($data['duration'], 28800) . '</video:duration>';
        }

        $published = strtotime($data['published']);
        if ($published !== false) {
            $parts[] = '      <video:publication_date>' . esc_html(wp_date('c', $published)) . '</video:publication_date>';
        }

        $views = (int) get_post_meta($post_id, VideoMeta::SOURCE_VIEWS, true);
        if ($views > 0) {
            $parts[] = '      <video:view_count>' . $views . '</video:view_count>';
        }

        $parts[] = '      <video:family_friendly>yes</video:family_friendly>';
        $parts[] = '      <video:live>no</video:live>';
        $parts[] = '    </video:video>';
        $parts[] = '  </url>';

        return implode("\n", $parts);
    }

    /**
     * Titles and descriptions are free text (they can contain & and <), so
     * they go in CDATA with the terminator neutralised.
     */
    private function cdata(string $text): string
    {
        $text = str_replace(']]>', ']]&gt;', wp_strip_all_tags($text));
        return '<![CDATA[' . $text . ']]>';
    }

    public static function flush(): void
    {
        delete_transient('stvh_sitemap_xml');
    }

    /**
     * Diagnostic count for the dashboard: videos actually eligible for the
     * sitemap, which is not the same as the total video count.
     */
    public function eligible_count(): int
    {
        $count = 0;
        foreach ($this->videos->all_published_ids() as $id) {
            if (VideoMeta::thumbnail($id) !== '' && VideoMeta::embed_url($id) !== '') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Total runtime of the library — a nice dashboard stat and cheap here
     * because the ids are already loaded.
     */
    public function total_duration_human(): string
    {
        $total = 0;
        foreach ($this->videos->all_published_ids() as $id) {
            $total += VideoMeta::duration($id);
        }
        return Format::duration($total);
    }
}
