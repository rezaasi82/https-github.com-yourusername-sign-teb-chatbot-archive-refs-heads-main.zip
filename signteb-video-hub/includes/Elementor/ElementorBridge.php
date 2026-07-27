<?php

namespace SignTeb\VideoHub\Elementor;

use SignTeb\VideoHub\Front\Assets;
use SignTeb\VideoHub\Widgets\MedicalHubWidget;
use SignTeb\VideoHub\Widgets\VideoGridWidget;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Feature 6 — Elementor integration.
 *
 * Everything here is guarded on Elementor actually being active; the plugin
 * has no hard dependency on it.
 */
class ElementorBridge
{
    public const CATEGORY = 'signteb';

    public function register(): void
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        // Elementor renders widgets after wp_head, so the preview/editor needs
        // the assets registered up front.
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_editor_assets']);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_editor_assets']);
    }

    public static function is_active(): bool
    {
        return did_action('elementor/loaded') > 0;
    }

    /**
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager): void
    {
        if (! is_object($widgets_manager) || ! method_exists($widgets_manager, 'register')) {
            return;
        }

        $widgets_manager->register(new VideoGridWidget());
        $widgets_manager->register(new MedicalHubWidget());
    }

    /**
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_category($elements_manager): void
    {
        if (! is_object($elements_manager) || ! method_exists($elements_manager, 'add_category')) {
            return;
        }

        $elements_manager->add_category(self::CATEGORY, [
            'title' => __('ساین‌طب', 'signteb-video-hub'),
            'icon'  => 'eicon-video-camera',
        ]);
    }

    public function enqueue_editor_assets(): void
    {
        Assets::force();
    }
}
