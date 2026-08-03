<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Catalogue of AI and search crawlers Medora knows about.
 *
 * Each entry carries the user-agent token used for detection, the robots.txt
 * token (they differ — Google's AI opt-out is `Google-Extended`, which is not
 * a real user agent), the vendor, and what the crawler is actually used for.
 * That last field is what lets a site owner make an informed decision instead
 * of blanket-blocking and disappearing from AI answers.
 */
final class CrawlerRegistry
{
    public const PURPOSE_TRAINING  = 'training';
    public const PURPOSE_SEARCH    = 'search';
    public const PURPOSE_RETRIEVAL = 'retrieval';

    /**
     * @return array<string, array{
     *     slug: string,
     *     name: string,
     *     vendor: string,
     *     ua_token: string,
     *     robots_token: string,
     *     purpose: string,
     *     recommended: bool
     * }>
     */
    public static function all(): array
    {
        $crawlers = [
            // OpenAI
            ['gptbot', 'GPTBot', 'OpenAI', 'GPTBot', 'GPTBot', self::PURPOSE_TRAINING, true],
            ['oai-searchbot', 'OAI-SearchBot', 'OpenAI', 'OAI-SearchBot', 'OAI-SearchBot', self::PURPOSE_SEARCH, true],
            ['chatgpt-user', 'ChatGPT-User', 'OpenAI', 'ChatGPT-User', 'ChatGPT-User', self::PURPOSE_RETRIEVAL, true],

            // Anthropic
            ['claudebot', 'ClaudeBot', 'Anthropic', 'ClaudeBot', 'ClaudeBot', self::PURPOSE_TRAINING, true],
            ['claude-user', 'Claude-User', 'Anthropic', 'Claude-User', 'Claude-User', self::PURPOSE_RETRIEVAL, true],
            ['claude-searchbot', 'Claude-SearchBot', 'Anthropic', 'Claude-SearchBot', 'Claude-SearchBot', self::PURPOSE_SEARCH, true],

            // Google
            ['googlebot', 'Googlebot', 'Google', 'Googlebot', 'Googlebot', self::PURPOSE_SEARCH, true],
            ['google-extended', 'Google-Extended', 'Google', '', 'Google-Extended', self::PURPOSE_TRAINING, true],
            ['googleother', 'GoogleOther', 'Google', 'GoogleOther', 'GoogleOther', self::PURPOSE_RETRIEVAL, true],

            // Microsoft
            ['bingbot', 'Bingbot', 'Microsoft', 'bingbot', 'Bingbot', self::PURPOSE_SEARCH, true],
            ['msnbot', 'MSNBot', 'Microsoft', 'msnbot', 'msnbot', self::PURPOSE_SEARCH, true],

            // Perplexity
            ['perplexitybot', 'PerplexityBot', 'Perplexity', 'PerplexityBot', 'PerplexityBot', self::PURPOSE_SEARCH, true],
            ['perplexity-user', 'Perplexity-User', 'Perplexity', 'Perplexity-User', 'Perplexity-User', self::PURPOSE_RETRIEVAL, true],

            // Meta
            ['meta-externalagent', 'Meta-ExternalAgent', 'Meta', 'meta-externalagent', 'meta-externalagent', self::PURPOSE_TRAINING, true],
            ['facebookbot', 'FacebookBot', 'Meta', 'FacebookBot', 'FacebookBot', self::PURPOSE_TRAINING, true],

            // Apple / Amazon
            ['applebot', 'Applebot', 'Apple', 'Applebot', 'Applebot', self::PURPOSE_SEARCH, true],
            ['applebot-extended', 'Applebot-Extended', 'Apple', '', 'Applebot-Extended', self::PURPOSE_TRAINING, true],
            ['amazonbot', 'Amazonbot', 'Amazon', 'Amazonbot', 'Amazonbot', self::PURPOSE_SEARCH, true],

            // Independent crawlers used as LLM training corpora
            ['ccbot', 'CCBot', 'Common Crawl', 'CCBot', 'CCBot', self::PURPOSE_TRAINING, true],
            ['bytespider', 'Bytespider', 'ByteDance', 'Bytespider', 'Bytespider', self::PURPOSE_TRAINING, false],
            ['diffbot', 'Diffbot', 'Diffbot', 'Diffbot', 'Diffbot', self::PURPOSE_TRAINING, false],
            ['omgili', 'Omgilibot', 'Webz.io', 'omgili', 'omgili', self::PURPOSE_TRAINING, false],
            ['timpibot', 'Timpibot', 'Timpi', 'Timpibot', 'Timpibot', self::PURPOSE_TRAINING, false],

            // Answer engines and assistants
            ['youbot', 'YouBot', 'You.com', 'YouBot', 'YouBot', self::PURPOSE_SEARCH, true],
            ['bravebot', 'BraveBot', 'Brave', 'BraveBot', 'BraveBot', self::PURPOSE_SEARCH, true],
            ['duckassistbot', 'DuckAssistBot', 'DuckDuckGo', 'DuckAssistBot', 'DuckAssistBot', self::PURPOSE_SEARCH, true],
            ['mistralai-user', 'MistralAI-User', 'Mistral', 'MistralAI-User', 'MistralAI-User', self::PURPOSE_RETRIEVAL, true],
            ['cohere-ai', 'cohere-ai', 'Cohere', 'cohere-ai', 'cohere-ai', self::PURPOSE_TRAINING, false],
            ['deepseekbot', 'DeepSeekBot', 'DeepSeek', 'DeepSeekBot', 'DeepSeekBot', self::PURPOSE_SEARCH, true],
            ['grokbot', 'GrokBot', 'xAI', 'GrokBot', 'GrokBot', self::PURPOSE_SEARCH, true],
            ['petalbot', 'PetalBot', 'Huawei', 'PetalBot', 'PetalBot', self::PURPOSE_SEARCH, false],
        ];

        $map = [];

        foreach ($crawlers as [$slug, $name, $vendor, $uaToken, $robotsToken, $purpose, $recommended]) {
            $map[$slug] = [
                'slug'         => $slug,
                'name'         => $name,
                'vendor'       => $vendor,
                'ua_token'     => $uaToken,
                'robots_token' => $robotsToken,
                'purpose'      => $purpose,
                'recommended'  => $recommended,
            ];
        }

        /**
         * Register crawlers that ship after this release without waiting for a
         * plugin update. Entries must use the same shape as the core list.
         *
         * @param array<string, array<string, mixed>> $map
         */
        return apply_filters('medora_crawler_registry', $map);
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /**
     * Crawlers grouped by vendor, for the dashboard's access-control table.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function byVendor(): array
    {
        $grouped = [];

        foreach (self::all() as $crawler) {
            $grouped[$crawler['vendor']][] = $crawler;
        }

        ksort($grouped);

        return $grouped;
    }

    public static function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            self::PURPOSE_TRAINING  => __('Model training', 'medora-authority'),
            self::PURPOSE_SEARCH    => __('AI search index', 'medora-authority'),
            self::PURPOSE_RETRIEVAL => __('Live answer retrieval', 'medora-authority'),
            default                 => __('Unknown', 'medora-authority'),
        };
    }
}
