<?php
/**
 * Embeds the chat inline via [pazira_chat].
 *
 * Lets a site owner drop the assistant into a sidebar, a widget area or the
 * body of any post/page, in addition to (or instead of) the floating launcher.
 * Assets load only when the shortcode actually renders, so pages without it
 * stay light.
 *
 * Usage: [pazira_chat]  (alias: [pazira_chat])
 *
 * @package Pazira
 */

namespace Pazira\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

class ChatShortcode
{
    public function register(): void
    {
        add_shortcode('pazira_chat', [$this, 'render']);

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
        return (new \Pazira\Frontend\Widget())->render_inline();
    }
}
