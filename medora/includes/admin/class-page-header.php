<?php
/**
 * Shared branded header for every plugin screen.
 *
 * @package Medora
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class PageHeader
{
    /**
     * @param array<int,array{label:string,state?:string}> $chips
     */
    public static function render(string $title, string $subtitle = '', array $chips = []): void
    {
        echo '<div class="mdr-head">';
        echo '<span class="mdr-head-mark" aria-hidden="true">&#128172;</span>';

        echo '<div class="mdr-head-text">';
        echo '<h1>' . esc_html($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';

        if ($chips) {
            echo '<div class="mdr-head-meta">';
            foreach ($chips as $chip) {
                $state = $chip['state'] ?? '';
                $class = 'mdr-chip';
                if ($state === 'on' || $state === 'off') {
                    $class .= ' mdr-chip-' . $state;
                }
                echo '<span class="' . esc_attr($class) . '">' . esc_html($chip['label']) . '</span>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Empty-state block for lists with no rows yet.
     */
    public static function empty_state(string $title, string $hint = '', string $icon = '&#128203;'): void
    {
        echo '<div class="mdr-empty">';
        echo '<span class="mdr-empty-ico" aria-hidden="true">' . wp_kses_post($icon) . '</span>';
        echo '<strong>' . esc_html($title) . '</strong>';
        if ($hint !== '') {
            echo '<span>' . esc_html($hint) . '</span>';
        }
        echo '</div>';
    }
}
