<?php

declare(strict_types=1);

namespace Medora\Authority\Llms;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Module\AbstractModule;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * llms.txt Engine — serves `/llms.txt` and `/llms-full.txt` from live content.
 */
final class LlmsModule extends AbstractModule
{
    private const QUERY_VAR = 'medora_llms';

    public function id(): string
    {
        return 'llms';
    }

    public function title(): string
    {
        return __('llms.txt Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Publish a curated, always-current Markdown map of your site for language models.', 'medora-authority');
    }

    public function boot(Container $container): void
    {
        if (! $container->get(Options::class)->getBool('llms_txt_enabled', true)) {
            return;
        }

        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVar']);

        add_action('template_redirect', function () use ($container): void {
            $this->maybeServe($container);
        });

        // Any content change invalidates the cached documents.
        foreach (['save_post', 'deleted_post', 'trashed_post'] as $hook) {
            add_action($hook, static function () use ($container): void {
                $container->get(LlmsTxtGenerator::class)->flush();
            });
        }

        add_action('medora_module_toggled', static function () use ($container): void {
            $container->get(LlmsTxtGenerator::class)->flush();
        });
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=index', 'top');
        add_rewrite_rule('^llms-full\.txt$', 'index.php?' . self::QUERY_VAR . '=full', 'top');
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    private function maybeServe(Container $container): void
    {
        $variant = get_query_var(self::QUERY_VAR);

        if (! in_array($variant, ['index', 'full'], true)) {
            return;
        }

        $generator = $container->get(LlmsTxtGenerator::class);
        $body      = $variant === 'full' ? $generator->full() : $generator->index();

        status_header(200);
        header('Content-Type: text/markdown; charset=utf-8', true);
        header('X-Robots-Tag: noindex', true);
        // Long public cache: the document changes only when content does, and
        // the origin regenerates it on save anyway.
        header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400', true);

        // Raw Markdown by design — escaping would corrupt the document.
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        exit;
    }
}
