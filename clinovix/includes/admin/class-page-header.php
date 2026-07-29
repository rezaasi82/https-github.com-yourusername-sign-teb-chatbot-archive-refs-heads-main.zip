<?php
/**
 * Shared branded header for every plugin screen.
 *
 * @package Clinovix
 */

namespace Clinovix\Admin;

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
        echo '<div class="clx-head">';
        echo '<span class="clx-head-mark" aria-hidden="true">&#128172;</span>';

        echo '<div class="clx-head-text">';
        echo '<h1>' . esc_html($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';

        if ($chips) {
            echo '<div class="clx-head-meta">';
            foreach ($chips as $chip) {
                $state = $chip['state'] ?? '';
                $class = 'clx-chip';
                if ($state === 'on' || $state === 'off') {
                    $class .= ' clx-chip-' . $state;
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
        echo '<div class="clx-empty">';
        echo '<span class="clx-empty-ico" aria-hidden="true">' . wp_kses_post($icon) . '</span>';
        echo '<strong>' . esc_html($title) . '</strong>';
        if ($hint !== '') {
            echo '<span>' . esc_html($hint) . '</span>';
        }
        echo '</div>';
    }
}
