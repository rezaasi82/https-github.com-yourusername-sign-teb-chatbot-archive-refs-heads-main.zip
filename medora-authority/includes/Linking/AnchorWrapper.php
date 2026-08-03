<?php

declare(strict_types=1);

namespace Medora\Authority\Linking;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Wraps a phrase in an anchor, without corrupting the surrounding HTML.
 *
 * Extracted from `LinkApplier` as a pure unit because this is the one place in
 * the plugin that rewrites a customer's published content. A naive
 * `str_replace` here would nest an `<a>` inside another (invalid, and the link
 * silently stops working), or rewrite the inside of an `alt` attribute, or link
 * a word inside a code sample. Each of those is a defect the customer sees on
 * their live site, so the logic is isolated and tested directly rather than
 * only through the module.
 *
 * The content is walked as an alternating sequence of tags and text, tracking
 * which forbidden elements are currently open. Matching only ever happens in
 * text nodes at depth zero.
 */
final class AnchorWrapper
{
    /**
     * Elements whose contents must never be linked.
     *
     * `a` prevents nesting. Headings are excluded because a link in a heading
     * competes with the heading's job of labelling the section. `code`, `pre`
     * and `script` are excluded because their contents are not prose.
     */
    public const FORBIDDEN_CONTEXTS = [
        'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'script', 'style', 'code', 'pre', 'button', 'textarea', 'title',
    ];

    /** Void elements never open a context, even without a trailing slash. */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Wrap the Nth linkable occurrence of `$phrase`.
     *
     * @param int $occurrence 1-based, counted over eligible occurrences only.
     * @return array{content: string, applied: bool, eligible: int}
     */
    public function wrap(string $content, string $phrase, string $href, int $occurrence = 1, string $marker = 'data-medora-link'): array
    {
        $phrase = trim($phrase);

        if ($phrase === '' || $content === '') {
            return ['content' => $content, 'applied' => false, 'eligible' => 0];
        }

        $parts = preg_split('/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return ['content' => $content, 'applied' => false, 'eligible' => 0];
        }

        $open     = [];
        $seen     = 0;
        $applied  = false;
        $occurrence = max(1, $occurrence);

        // Word-boundary anchoring that works for scripts without spaces between
        // letters and digits — \b is ASCII-only and would mis-fire on Persian.
        $pattern = '/(?<![\p{L}\p{N}])(' . preg_quote($phrase, '/') . ')(?![\p{L}\p{N}])/ui';

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($part[0] === '<') {
                $this->track($part, $open);
                continue;
            }

            if ($open !== []) {
                continue;
            }

            $parts[$index] = (string) preg_replace_callback(
                $pattern,
                static function (array $match) use (&$seen, &$applied, $occurrence, $href, $marker): string {
                    $seen++;

                    if ($applied || $seen !== $occurrence) {
                        return $match[0];
                    }

                    $applied = true;

                    return sprintf(
                        '<a href="%s" %s="1">%s</a>',
                        esc_url($href),
                        $marker,
                        $match[1]
                    );
                },
                $part
            );
        }

        return [
            'content'  => implode('', $parts),
            'applied'  => $applied,
            'eligible' => $seen,
        ];
    }

    /**
     * Count linkable occurrences without modifying anything.
     *
     * Used by the UI to say "3 places you could put this" before an editor
     * commits to one.
     */
    public function countEligible(string $content, string $phrase): int
    {
        // Wrapping with an occurrence beyond any plausible count leaves the
        // content untouched while still walking every text node.
        return $this->wrap($content, $phrase, 'https://example.invalid', PHP_INT_MAX)['eligible'];
    }

    /**
     * Remove anchors carrying the marker attribute, keeping their text.
     *
     * @return array{content: string, reverted: int}
     */
    public function unwrap(string $content, string $marker = 'data-medora-link', ?string $href = null): array
    {
        $reverted = 0;

        $result = (string) preg_replace_callback(
            '#<a\b[^>]*\b' . preg_quote($marker, '#') . '\b[^>]*>(.*?)</a>#is',
            static function (array $match) use (&$reverted, $href): string {
                if ($href !== null && ! str_contains($match[0], $href)) {
                    return $match[0];
                }

                $reverted++;

                return $match[1];
            },
            $content
        );

        return ['content' => $result, 'reverted' => $reverted];
    }

    /**
     * Track which forbidden elements are currently open.
     *
     * @param list<string> $open
     */
    private function track(string $tag, array &$open): void
    {
        if (preg_match('#^</?\s*([a-z0-9]+)#i', $tag, $match) !== 1) {
            // Comments, doctypes and block delimiters open nothing.
            return;
        }

        $name = strtolower($match[1]);

        if (! in_array($name, self::FORBIDDEN_CONTEXTS, true)) {
            return;
        }

        if (str_starts_with($tag, '</')) {
            // Close the innermost matching element. Searching from the end
            // handles the (invalid but real) case of nested identical tags
            // without unbalancing the stack.
            $position = array_search($name, array_reverse($open, true), true);

            if ($position !== false) {
                unset($open[$position]);
                $open = array_values($open);
            }

            return;
        }

        if (in_array($name, self::VOID_ELEMENTS, true) || str_ends_with(rtrim($tag, '>'), '/')) {
            return;
        }

        $open[] = $name;
    }
}
