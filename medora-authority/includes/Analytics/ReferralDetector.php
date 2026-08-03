<?php

declare(strict_types=1);

namespace Medora\Authority\Analytics;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Identifies traffic arriving from an AI assistant.
 *
 * Detection uses the referrer host plus the UTM parameters the assistants
 * actually set. This is imperfect by nature — several assistants strip the
 * referrer, and some route through a redirector — so the numbers are reported
 * as *observed* AI referrals, never as a complete count. Overstating this is
 * the single easiest way for an analytics feature to lose credibility.
 */
final class ReferralDetector
{
    /**
     * Referrer host fragment => assistant slug.
     *
     * @var array<string, string>
     */
    private const HOSTS = [
        'chat.openai.com'      => 'chatgpt',
        'chatgpt.com'          => 'chatgpt',
        'openai.com'           => 'chatgpt',
        'claude.ai'            => 'claude',
        'anthropic.com'        => 'claude',
        'perplexity.ai'        => 'perplexity',
        'gemini.google.com'    => 'gemini',
        'bard.google.com'      => 'gemini',
        'copilot.microsoft.com' => 'copilot',
        'bing.com'             => 'copilot',
        'edgeservices.bing.com' => 'copilot',
        'search.brave.com'     => 'brave',
        'duckduckgo.com'       => 'duckduckgo',
        'you.com'              => 'you',
        'grok.com'             => 'grok',
        'x.ai'                 => 'grok',
        'chat.deepseek.com'    => 'deepseek',
        'meta.ai'              => 'meta',
        'chat.mistral.ai'      => 'mistral',
        'poe.com'              => 'poe',
        'phind.com'            => 'phind',
    ];

    /**
     * @return array{slug: string, label: string, referrer_host: string}|null
     */
    public function detect(string $referrer, string $queryString = ''): ?array
    {
        $slug = $this->fromUtm($queryString) ?? $this->fromReferrer($referrer);

        if ($slug === null) {
            return null;
        }

        return [
            'slug'          => $slug,
            'label'         => self::label($slug),
            'referrer_host' => (string) (wp_parse_url($referrer, PHP_URL_HOST) ?? ''),
        ];
    }

    private function fromReferrer(string $referrer): ?string
    {
        $host = strtolower((string) (wp_parse_url($referrer, PHP_URL_HOST) ?? ''));

        if ($host === '') {
            return null;
        }

        // Own-domain referrers are internal navigation, not an AI referral.
        if ($host === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
            return null;
        }

        foreach (self::HOSTS as $needle => $slug) {
            if ($host === $needle || str_ends_with($host, '.' . $needle)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Some assistants tag outbound links instead of (or as well as) sending a
     * referrer, and a tag survives the referrer being stripped.
     */
    private function fromUtm(string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        parse_str($queryString, $params);

        $source = strtolower((string) ($params['utm_source'] ?? ''));

        if ($source === '') {
            return null;
        }

        foreach (['chatgpt', 'openai', 'claude', 'perplexity', 'gemini', 'copilot', 'grok', 'deepseek', 'mistral'] as $known) {
            if (str_contains($source, $known)) {
                return $known === 'openai' ? 'chatgpt' : $known;
            }
        }

        return null;
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            'chatgpt'    => 'ChatGPT',
            'claude'     => 'Claude',
            'perplexity' => 'Perplexity',
            'gemini'     => 'Gemini',
            'copilot'    => 'Microsoft Copilot',
            'brave'      => 'Brave AI',
            'duckduckgo' => 'Duck.ai',
            'you'        => 'You.com',
            'grok'       => 'Grok',
            'deepseek'   => 'DeepSeek',
            'meta'       => 'Meta AI',
            'mistral'    => 'Mistral',
            'poe'        => 'Poe',
            'phind'      => 'Phind',
            default      => ucfirst($slug),
        };
    }

    /** @return list<string> */
    public static function knownSources(): array
    {
        return array_values(array_unique(array_values(self::HOSTS)));
    }
}
