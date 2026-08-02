<?php

declare(strict_types=1);

namespace Medora\Authority\Prompt;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Prompt Engine.
 *
 * Also exposes the prompt pack to crawlers inline, as a non-rendered JSON
 * block. An AI crawler that fetches the page gets the structured answer
 * without a second request; a browser never sees it.
 */
final class PromptModule extends AbstractModule
{
    public function id(): string
    {
        return 'prompt';
    }

    public function title(): string
    {
        return __('AI Prompt Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Give every page a canonical answer, fact sheet, question pack and packed context window.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(PromptPackRepository::class, static fn (): PromptPackRepository => new PromptPackRepository());

        $container->singleton(
            PromptContextBuilder::class,
            static fn (Container $c): PromptContextBuilder => new PromptContextBuilder(
                $c->get(PromptPackRepository::class),
                $c->get(EntityRepository::class)
            )
        );
    }

    public function boot(Container $container): void
    {
        add_action('deleted_post', static function (int $postId) use ($container): void {
            $container->get(PromptPackRepository::class)->delete('post', $postId);
        });

        add_action('wp_head', function () use ($container): void {
            $this->printPromptPack($container);
        }, 6);
    }

    private function printPromptPack(Container $container): void
    {
        if (! is_singular()) {
            return;
        }

        $post = get_post();

        if (! $post instanceof WP_Post) {
            return;
        }

        $pack = $container->get(PromptPackRepository::class)->find('post', $post->ID);

        if ($pack === null || $pack['canonical_answer'] === '') {
            return;
        }

        $payload = wp_json_encode([
            'url'             => get_permalink($post),
            'title'           => get_the_title($post),
            'summary'         => $pack['summary'],
            'canonicalAnswer' => $pack['canonical_answer'],
            'facts'           => $pack['facts'],
            'questions'       => $pack['questions'],
            'generatedAt'     => $pack['generated_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            return;
        }

        // A non-executable script type: parsers read it, browsers ignore it.
        printf(
            "<script type=\"application/medora+json\" data-medora=\"prompt-pack\">%s</script>\n",
            $payload // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output.
        );
    }
}
