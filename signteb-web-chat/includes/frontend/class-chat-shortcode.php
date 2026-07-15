<?php
/**
 * SWC_Chat_Shortcode — embeds the chat inline via [medora_chat].
 *
 * Lets a site owner drop the assistant into a sidebar, a widget area or the
 * body of any post/page, in addition to (or instead of) the floating launcher.
 * Assets load only when the shortcode actually renders, so pages without it
 * stay light.
 *
 * Usage: [medora_chat]  (alias: [signteb_chat])
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Chat_Shortcode
{
    public function register(): void
    {
        add_shortcode('medora_chat', [$this, 'render']);
        add_shortcode('signteb_chat', [$this, 'render']); // back-compat alias

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
        return (new SWC_Widget())->render_inline();
    }
}
