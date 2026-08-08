<?php

namespace SignTeb\VideoHub\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Elementor\ElementorBridge;
use SignTeb\VideoHub\Front\Renderer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Drag-and-drop video list: latest / popular, as grid, list, slider or
 * carousel, with the topic filter and live search optional per instance.
 */
class VideoGridWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'stvh_video_grid';
    }

    public function get_title(): string
    {
        return __('ویدئوهای ساین‌طب', 'signteb-video-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    /**
     * @return array<int,string>
     */
    public function get_categories(): array
    {
        return [ElementorBridge::CATEGORY];
    }

    /**
     * @return array<int,string>
     */
    public function get_keywords(): array
    {
        return ['video', 'aparat', 'youtube', 'ویدئو', 'آپارات', 'یوتیوب'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('محتوا', 'signteb-video-hub'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('title', [
            'label'   => __('عنوان بخش', 'signteb-video-hub'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('آخرین ویدئوها', 'signteb-video-hub'),
        ]);

        $this->add_control('subtitle', [
            'label' => __('زیرعنوان', 'signteb-video-hub'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $this->add_control('layout', [
            'label'   => __('چیدمان', 'signteb-video-hub'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'grid',
            'options' => [
                'grid'     => __('گرید', 'signteb-video-hub'),
                'list'     => __('فهرست', 'signteb-video-hub'),
                'carousel' => __('کاروسل', 'signteb-video-hub'),
                'slider'   => __('اسلایدر', 'signteb-video-hub'),
            ],
        ]);

        $this->add_control('orderby', [
            'label'   => __('ترتیب', 'signteb-video-hub'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'date',
            'options' => [
                'date'     => __('جدیدترین', 'signteb-video-hub'),
                'popular'  => __('محبوب‌ترین', 'signteb-video-hub'),
                'title'    => __('الفبا', 'signteb-video-hub'),
                'duration' => __('طولانی‌ترین', 'signteb-video-hub'),
                'random'   => __('تصادفی', 'signteb-video-hub'),
            ],
        ]);

        $this->add_control('topic', [
            'label'   => __('موضوع', 'signteb-video-hub'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $this->topic_options(),
        ]);

        $this->add_control('per_page', [
            'label'   => __('تعداد', 'signteb-video-hub'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 12,
            'min'     => 1,
            'max'     => 48,
        ]);

        $this->add_control('show_filters', [
            'label'        => __('نمایش فیلتر موضوعی', 'signteb-video-hub'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('show_search', [
            'label'        => __('نمایش جستجوی زنده', 'signteb-video-hub'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('theme', [
            'label'   => __('پوسته', 'signteb-video-hub'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => [
                'auto'  => __('خودکار', 'signteb-video-hub'),
                'light' => __('روشن', 'signteb-video-hub'),
                'dark'  => __('تیره', 'signteb-video-hub'),
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Escaping happens inside Renderer, per field.
        echo (new Renderer())->hub([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'title'        => (string) ($settings['title'] ?? ''),
            'subtitle'     => (string) ($settings['subtitle'] ?? ''),
            'layout'       => (string) ($settings['layout'] ?? 'grid'),
            'theme'        => (string) ($settings['theme'] ?? 'auto'),
            'orderby'      => (string) ($settings['orderby'] ?? 'date'),
            'topic'        => (string) ($settings['topic'] ?? ''),
            'per_page'     => (int) ($settings['per_page'] ?? 12),
            'show_filters' => ($settings['show_filters'] ?? 'yes') === 'yes',
            'show_search'  => ($settings['show_search'] ?? 'yes') === 'yes',
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function topic_options(): array
    {
        $options = ['' => __('همه موضوعات', 'signteb-video-hub')];

        $terms = get_terms(['taxonomy' => PostType::TAXONOMY, 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            return $options;
        }

        foreach ($terms as $term) {
            $options[$term->slug] = $term->name;
        }

        return $options;
    }
}
