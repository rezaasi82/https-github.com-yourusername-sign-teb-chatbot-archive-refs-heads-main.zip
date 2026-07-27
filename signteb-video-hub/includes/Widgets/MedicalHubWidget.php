<?php

namespace SignTeb\VideoHub\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use SignTeb\VideoHub\Elementor\ElementorBridge;
use SignTeb\VideoHub\Front\MedicalHub;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The AI Medical Hub block as an Elementor widget, so it can be dropped at the
 * bottom of any condition or service page (feature 18).
 */
class MedicalHubWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'stvh_medical_hub';
    }

    public function get_title(): string
    {
        return __('مرکز هوشمند پزشکی', 'signteb-video-hub');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-group';
    }

    /**
     * @return array<int,string>
     */
    public function get_categories(): array
    {
        return [ElementorBridge::CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('محتوا', 'signteb-video-hub'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('title', [
            'label'   => __('عنوان', 'signteb-video-hub'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('ادامه مسیر شما', 'signteb-video-hub'),
        ]);

        $this->add_control('videos', [
            'label'   => __('تعداد ویدئوی مرتبط', 'signteb-video-hub'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 3,
            'min'     => 0,
            'max'     => 8,
        ]);

        $this->add_control('articles', [
            'label'   => __('تعداد مقاله مرتبط', 'signteb-video-hub'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 3,
            'min'     => 0,
            'max'     => 8,
        ]);

        $this->add_control('questions', [
            'label'   => __('تعداد سوال متداول', 'signteb-video-hub'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 4,
            'min'     => 0,
            'max'     => 8,
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $settings = $this->get_settings_for_display();

        // Escaping happens inside MedicalHub, per field.
        echo (new MedicalHub())->render((int) $post_id, [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'title'     => (string) ($settings['title'] ?? ''),
            'videos'    => (int) ($settings['videos'] ?? 3),
            'articles'  => (int) ($settings['articles'] ?? 3),
            'questions' => (int) ($settings['questions'] ?? 4),
        ]);
    }
}
