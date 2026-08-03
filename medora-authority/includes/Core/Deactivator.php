<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class Deactivator
{
    /**
     * Deactivation stops scheduled work and clears generated artefacts. It
     * never touches the data tables — a deactivate/reactivate cycle while
     * debugging must not cost a site its knowledge graph.
     */
    public static function deactivate(): void
    {
        Cron::unschedule();

        delete_transient('medora_llms_txt');
        delete_transient('medora_sitemap_index');

        flush_rewrite_rules();
    }
}
