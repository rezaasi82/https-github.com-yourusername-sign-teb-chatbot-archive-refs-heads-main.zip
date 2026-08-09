<?php
/**
 * Inline SVG icon set.
 *
 * Every icon the plugin shows comes from this whitelist. The markup is a
 * compile-time literal — no request data ever reaches it — so callers can echo
 * the return value directly. Icons inherit the surrounding text colour via
 * `currentColor` and size via the `--pzk-ico-size` custom property, which keeps
 * one icon usable in a stat tile, a table cell and a button without variants.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class Icon
{
    /**
     * 24x24 path bodies, stroked with currentColor.
     *
     * @var array<string,string>
     */
    private const PATHS = [
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'chat'      => '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 0 1 11 4h2a8 8 0 0 1 8 8Z"/>',
        'phone'     => '<path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/>',
        'send'      => '<path d="M21 3 3 10.5l7 2.5 2.5 7L21 3Z"/><path d="m10 13.5 4-4"/>',
        'target'    => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
        'flame'     => '<path d="M12 3c3 3.5 5.5 6 5.5 9.5A5.5 5.5 0 0 1 12 18a5.5 5.5 0 0 1-5.5-5.5C6.5 10 8 8.5 9 7c.8 1.4 1.6 2 2.4 2C12 8 11 5.5 12 3Z"/>',
        'trending'  => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'shield'    => '<path d="M12 3l7 3v6c0 4.4-3 8-7 9-4-1-7-4.6-7-9V6l7-3Z"/>',
        'check'     => '<path d="M4 12.5 9.5 18 20 6.5"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'bell'      => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6Z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'alert'     => '<path d="M12 4 2.5 20h19L12 4Z"/><path d="M12 10v4M12 17h.01"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 6.6M17 14.4a6.5 6.5 0 0 1 4.5 5.6"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5"/>',
        'download'  => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
    ];

    /**
     * Return the inline SVG markup for an icon.
     *
     * @param string $name  One of the keys in self::PATHS.
     * @param string $class Extra CSS class(es) for the <svg> element.
     */
    public static function svg(string $name, string $class = ''): string
    {
        if (! isset(self::PATHS[$name])) {
            return '';
        }

        $classes = trim('pzk-ico pzk-ico-' . $name . ' ' . $class);

        return '<svg class="' . esc_attr($classes) . '" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="1.7" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . self::PATHS[$name]
            . '</svg>';
    }

    /**
     * Icon plus a visible label, for buttons and stat tiles.
     */
    public static function with_label(string $name, string $label, string $class = ''): string
    {
        return '<span class="pzk-ico-wrap ' . esc_attr($class) . '">'
            . self::svg($name)
            . '<span>' . esc_html($label) . '</span>'
            . '</span>';
    }

    /**
     * Lead-temperature indicator. A coloured dot carrying an accessible name,
     * replacing the coloured-circle emoji the scoring UI used to print.
     */
    public static function lead_dot(string $level, string $label): string
    {
        $level = in_array($level, ['hot', 'warm', 'cold'], true) ? $level : 'cold';

        return '<span class="pzk-dot pzk-dot-' . esc_attr($level) . '">'
            . '<i aria-hidden="true"></i>'
            . '<span>' . esc_html($label) . '</span>'
            . '</span>';
    }
}
