<?php

declare(strict_types=1);

namespace Medora\Authority\Crawler;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Identifies the AI crawler behind the current request from its user agent.
 *
 * Detection is intentionally user-agent based and is treated as a *hint*, not
 * as authentication: user agents are trivially spoofed. Nothing security
 * relevant is gated on the result — it drives analytics and content
 * negotiation only. Access control that actually needs to hold up is enforced
 * through robots directives and, optionally, reverse-DNS verification.
 */
final class CrawlerDetector
{
    /** @var array<string, mixed>|false|null */
    private array|false|null $current = null;

    /**
     * The crawler making the current request, or null for a human visitor.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        if ($this->current === null) {
            $this->current = $this->detect($this->userAgent()) ?? false;
        }

        return $this->current === false ? null : $this->current;
    }

    public function isAiCrawler(): bool
    {
        return $this->current() !== null;
    }

    public function currentSlug(): string
    {
        return (string) ($this->current()['slug'] ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detect(string $userAgent): ?array
    {
        if ($userAgent === '') {
            return null;
        }

        $haystack = strtolower($userAgent);
        $match    = null;
        $matchLen = 0;

        foreach (CrawlerRegistry::all() as $crawler) {
            $token = (string) $crawler['ua_token'];

            if ($token === '') {
                // Robots-only tokens such as Google-Extended never appear in a
                // real request, so they can never match.
                continue;
            }

            $needle = strtolower($token);

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            // Prefer the longest match so "Claude-SearchBot" is not swallowed
            // by a shorter, overlapping token.
            if (strlen($needle) > $matchLen) {
                $match    = $crawler;
                $matchLen = strlen($needle);
            }
        }

        return $match;
    }

    public function userAgent(): string
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']));
    }

    public function requestUri(): string
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return '/';
        }

        $uri = esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI']));

        return substr($uri, 0, 255);
    }

    /**
     * Confirm a claimed crawler really is who it says by forward-confirmed
     * reverse DNS, the method Google and OpenAI both document.
     *
     * Expensive (two DNS lookups), so callers should cache the outcome; it is
     * used for the "verified" badge in the crawler report, never inline.
     */
    public function verifyReverseDns(string $ip, string $expectedDomainSuffix): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $host = gethostbyaddr($ip);

        if ($host === false || $host === $ip) {
            return false;
        }

        if (! str_ends_with(strtolower($host), strtolower($expectedDomainSuffix))) {
            return false;
        }

        return in_array($ip, gethostbynamel($host) ?: [], true);
    }
}
