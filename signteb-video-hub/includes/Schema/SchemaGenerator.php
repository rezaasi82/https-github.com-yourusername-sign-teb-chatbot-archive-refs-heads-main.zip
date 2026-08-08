<?php

namespace SignTeb\VideoHub\Schema;

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Core\VideoMeta;
use SignTeb\VideoHub\Db\VideoRepository;
use SignTeb\VideoHub\Helpers\Format;
use SignTeb\VideoHub\Helpers\Json;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 5 — JSON-LD for video singles and video archives.
 *
 * Everything is emitted as one @graph so the nodes can reference each other
 * by @id, which is what lets Google tie the VideoObject to the page, the page
 * to the physician, and the FAQ to both.
 */
class SchemaGenerator
{
    private Settings $settings;
    private VideoRepository $videos;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->videos   = new VideoRepository();
    }

    public function register(): void
    {
        if (! $this->settings->bool('schema_enabled')) {
            return;
        }
        add_action('wp_head', [$this, 'output'], 20);
    }

    public function output(): void
    {
        $graph = [];

        if (is_singular(PostType::POST_TYPE)) {
            $graph = $this->single_graph(get_queried_object_id());
        } elseif (is_post_type_archive(PostType::POST_TYPE) || is_tax(PostType::TAXONOMY)) {
            $graph = $this->archive_graph();
        }

        /**
         * Final say over the JSON-LD graph.
         *
         * @param array<int,array<string,mixed>> $graph
         */
        $graph = apply_filters('stvh_schema_graph', $graph);

        if ($graph === []) {
            return;
        }

        printf(
            '<script type="application/ld+json" data-stvh="1">%s</script>' . "\n",
            Json::encode(['@context' => 'https://schema.org', '@graph' => array_values($graph)])
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function single_graph(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $graph   = [];
        $graph[] = $this->video_object($post_id);

        $faq = $this->faq_page($post_id);
        if ($faq !== null) {
            $graph[] = $faq;
        }

        if (! SeoCompat::owns_page_schema()) {
            $graph[] = $this->medical_web_page($post_id);
            $graph[] = $this->breadcrumbs($this->single_crumbs($post_id));
        }

        $physician = $this->physician();
        if ($physician !== null) {
            $graph[] = $physician;
        }

        return array_values(array_filter($graph));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function archive_graph(): array
    {
        $term = is_tax(PostType::TAXONOMY) ? get_queried_object() : null;
        $args = ['per_page' => $this->settings->int('cards_per_page')];
        if ($term instanceof WP_Term) {
            $args['topic'] = $term->term_id;
        }

        $result = $this->videos->query($args);
        $graph  = [];

        $list = $this->item_list($result['ids']);
        if ($list !== null) {
            $graph[] = $list;
        }

        if (! SeoCompat::owns_page_schema()) {
            $graph[] = $this->breadcrumbs($this->archive_crumbs($term instanceof WP_Term ? $term : null));
        }

        $physician = $this->physician();
        if ($physician !== null) {
            $graph[] = $physician;
        }

        return array_values(array_filter($graph));
    }

    /**
     * @return array<string,mixed>
     */
    public function video_object(int $post_id): array
    {
        $data = VideoMeta::seo_payload($post_id);

        $node = [
            '@type'        => 'VideoObject',
            '@id'          => $data['url'] . '#video',
            'name'         => $data['title'],
            'description'  => $data['description'] !== '' ? $data['description'] : $data['title'],
            'uploadDate'   => $this->iso_date($data['published']),
            'url'          => $data['url'],
            'inLanguage'   => 'fa-IR',
            'isFamilyFriendly' => true,
        ];

        if ($data['thumbnail'] !== '') {
            $node['thumbnailUrl'] = [$data['thumbnail']];
        }
        if ($data['embed'] !== '') {
            $node['embedUrl'] = $data['embed'];
        }
        if ($data['duration'] > 0) {
            $node['duration'] = Format::iso8601_duration($data['duration']);
        }

        $source_url = VideoMeta::source_url($post_id);
        if ($source_url !== '') {
            $node['contentUrl'] = $source_url;
        }

        $views = (int) get_post_meta($post_id, '_stvh_views_30d', true) + (int) get_post_meta($post_id, VideoMeta::SOURCE_VIEWS, true);
        if ($views > 0) {
            $node['interactionStatistic'] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => ['@type' => 'WatchAction'],
                'userInteractionCount' => $views,
            ];
        }

        $publisher = $this->publisher();
        if ($publisher !== []) {
            $node['publisher'] = $publisher;
        }

        $physician_id = $this->physician_id();
        if ($physician_id !== '') {
            $node['author'] = ['@id' => $physician_id];
        }

        return $node;
    }

    /**
     * @return array<string,mixed>
     */
    private function medical_web_page(int $post_id): array
    {
        $url = (string) get_permalink($post_id);

        $node = [
            '@type'            => 'MedicalWebPage',
            '@id'              => $url . '#webpage',
            'url'              => $url,
            'name'             => (string) get_the_title($post_id),
            'inLanguage'       => 'fa-IR',
            'datePublished'    => $this->iso_date((string) get_post_field('post_date', $post_id)),
            'dateModified'     => $this->iso_date((string) get_post_field('post_modified', $post_id)),
            'mainEntity'       => ['@id' => $url . '#video'],
            'isPartOf'         => ['@id' => home_url('/') . '#website'],
        ];

        $summary = VideoMeta::summary($post_id);
        if ($summary !== '') {
            $node['description'] = $summary;
        }

        $physician_id = $this->physician_id();
        if ($physician_id !== '') {
            $node['reviewedBy'] = ['@id' => $physician_id];
        }

        $terms = wp_get_post_terms($post_id, PostType::TAXONOMY, ['fields' => 'names']);
        if (! is_wp_error($terms) && $terms !== []) {
            $node['about'] = array_map(
                static fn(string $name): array => ['@type' => 'MedicalCondition', 'name' => $name],
                $terms
            );
        }

        return $node;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function faq_page(int $post_id): ?array
    {
        $faq = VideoMeta::faq($post_id);
        if ($faq === []) {
            return null;
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => get_permalink($post_id) . '#faq',
            'mainEntity' => array_map(
                static fn(array $item): array => [
                    '@type'          => 'Question',
                    'name'           => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ],
                $faq
            ),
        ];
    }

    /**
     * @param array<int,int> $ids
     * @return array<string,mixed>|null
     */
    private function item_list(array $ids): ?array
    {
        if ($ids === []) {
            return null;
        }

        $elements = [];
        $position = 1;
        foreach ($ids as $id) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => (string) get_permalink($id),
                'name'     => (string) get_the_title($id),
            ];
        }

        return [
            '@type'           => 'ItemList',
            '@id'             => $this->current_url() . '#videolist',
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array<int,array{name:string,url:string}> $crumbs
     * @return array<string,mixed>
     */
    private function breadcrumbs(array $crumbs): array
    {
        $elements = [];
        $position = 1;
        foreach ($crumbs as $crumb) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $crumb['name'],
                'item'     => $crumb['url'],
            ];
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $this->current_url() . '#breadcrumb',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<int,array{name:string,url:string}>
     */
    private function single_crumbs(int $post_id): array
    {
        $crumbs = [
            ['name' => __('خانه', 'signteb-video-hub'), 'url' => home_url('/')],
            ['name' => __('ویدئوها', 'signteb-video-hub'), 'url' => (string) get_post_type_archive_link(PostType::POST_TYPE)],
        ];

        $terms = wp_get_post_terms($post_id, PostType::TAXONOMY);
        if (! is_wp_error($terms) && $terms !== []) {
            $link = get_term_link($terms[0]);
            if (! is_wp_error($link)) {
                $crumbs[] = ['name' => $terms[0]->name, 'url' => (string) $link];
            }
        }

        $crumbs[] = ['name' => (string) get_the_title($post_id), 'url' => (string) get_permalink($post_id)];

        return $crumbs;
    }

    /**
     * @return array<int,array{name:string,url:string}>
     */
    private function archive_crumbs(?WP_Term $term): array
    {
        $crumbs = [
            ['name' => __('خانه', 'signteb-video-hub'), 'url' => home_url('/')],
            ['name' => __('ویدئوها', 'signteb-video-hub'), 'url' => (string) get_post_type_archive_link(PostType::POST_TYPE)],
        ];

        if ($term !== null) {
            $link = get_term_link($term);
            if (! is_wp_error($link)) {
                $crumbs[] = ['name' => $term->name, 'url' => (string) $link];
            }
        }

        return $crumbs;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function physician(): ?array
    {
        $name = $this->settings->str('physician_name');
        if ($name === '') {
            return null;
        }

        $node = [
            '@type' => 'Physician',
            '@id'   => $this->physician_id(),
            'name'  => $name,
            'url'   => $this->settings->str('physician_url') ?: home_url('/'),
        ];

        $specialty = $this->settings->str('physician_specialty');
        if ($specialty !== '') {
            $node['medicalSpecialty'] = $specialty;
        }

        $clinic = $this->settings->str('clinic_name');
        if ($clinic !== '') {
            $node['worksFor'] = ['@type' => 'MedicalClinic', 'name' => $clinic];
        }

        return $node;
    }

    private function physician_id(): string
    {
        if ($this->settings->str('physician_name') === '') {
            return '';
        }
        return home_url('/') . '#physician';
    }

    /**
     * @return array<string,mixed>
     */
    private function publisher(): array
    {
        $name = $this->settings->str('clinic_name') ?: get_bloginfo('name');
        if ($name === '') {
            return [];
        }

        $node = ['@type' => 'Organization', 'name' => $name, 'url' => home_url('/')];

        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id > 0) {
            $logo = wp_get_attachment_image_url($logo_id, 'full');
            if (is_string($logo)) {
                $node['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
            }
        }

        return $node;
    }

    private function iso_date(string $mysql_date): string
    {
        $timestamp = strtotime($mysql_date);
        if ($timestamp === false) {
            $timestamp = time();
        }
        return wp_date('c', $timestamp);
    }

    private function current_url(): string
    {
        if (is_tax(PostType::TAXONOMY)) {
            $link = get_term_link(get_queried_object_id(), PostType::TAXONOMY);
            if (! is_wp_error($link)) {
                return (string) $link;
            }
        }
        if (is_singular()) {
            return (string) get_permalink(get_queried_object_id());
        }
        return (string) get_post_type_archive_link(PostType::POST_TYPE);
    }
}
