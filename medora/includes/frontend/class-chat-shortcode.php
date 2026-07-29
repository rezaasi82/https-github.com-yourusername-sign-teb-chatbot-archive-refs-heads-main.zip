<?php
/**
 * Embeds the chat inline via [medora_chat].
 *
 * Lets a site owner drop the assistant into a sidebar, a widget area or the
 * body of any post/page, in addition to (or instead of) the floating launcher.
 * Assets load only when the shortcode actually renders, so pages without it
 * stay light.
 *
 * Usage: [medora_chat]  (alias: [medora_chat])
 *
 * @package Medora
 */

namespace Medora\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

class ChatShortcode
{
    public function register(): void
    {
        add_shortcode('medora_chat', [$this, 'render']);

        // Classic "Text" widgets don't run shortcodes by default; enable it so
        // the embed works when dropped into a sidebar text widget.
        add_filter('widget_text', 'do_shortcode');
    }

    /**
     * @param array<string,mixed>|string $atts
     */
    public function render($atts = []): string
    {
        // Never render inside feeds / REST previews.
        if (is_feed()) {
            return '';
        }

        // The shortcode stays registered even when switched off, so a page
        // that contains it shows nothing rather than the raw tag text.
        if (! (new \Medora\Core\Settings())->is_shortcode_enabled()) {
            return '';
        }

        return (new \Medora\Frontend\Widget())->render_inline();
    }
}
