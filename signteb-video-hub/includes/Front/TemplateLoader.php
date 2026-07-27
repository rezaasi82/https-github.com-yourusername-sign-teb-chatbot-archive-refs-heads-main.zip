<?php

namespace SignTeb\VideoHub\Front;

use SignTeb\VideoHub\Core\PostType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Falls back to the bundled archive template when the theme has none.
 *
 * A theme always wins: if it ships archive-stvh_video.php or
 * taxonomy-stvh_topic.php, WordPress resolves that first and this never runs.
 * The bundled template exists so /videos/ and /videos/{topic}/ look right on a
 * stock theme (feature 17).
 */
class TemplateLoader
{
    public function register(): void
    {
        add_filter('archive_template_hierarchy', [$this, 'archive_hierarchy']);
        add_filter('taxonomy_template_hierarchy', [$this, 'archive_hierarchy']);
        add_filter('template_include', [$this, 'resolve'], 99);
    }

    /**
     * Ensure our template name is in the hierarchy so a theme can override it
     * by simply dropping a file with the same name into the theme folder.
     *
     * @param array<int,string> $templates
     * @return array<int,string>
     */
    public function archive_hierarchy(array $templates): array
    {
        if (! $this->is_video_archive()) {
            return $templates;
        }

        array_unshift($templates, 'stvh-video-archive.php');

        return $templates;
    }

    public function resolve(string $template): string
    {
        if (! $this->is_video_archive()) {
            return $template;
        }

        // A theme-provided template was located — leave it alone.
        if (basename($template) === 'stvh-video-archive.php') {
            return $template;
        }

        $located = locate_template(['stvh-video-archive.php', 'archive-' . PostType::POST_TYPE . '.php']);
        if ($located !== '') {
            return $located;
        }

        return STVH_DIR . 'templates/archive-video.php';
    }

    private function is_video_archive(): bool
    {
        return is_post_type_archive(PostType::POST_TYPE) || is_tax(PostType::TAXONOMY);
    }
}
