<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

use Medora\Authority\Core\Options;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What language the *content* is in — which is not necessarily what language
 * the admin is in.
 *
 * Everything used to read `get_locale()`, and that is the wrong source. A
 * Persian clinic running an English-locale WordPress is an ordinary setup, not
 * an edge case, and it produced `inLanguage: "en-US"` on every Persian page —
 * a wrong signal handed to exactly the AI crawlers this product exists to send
 * the right signals to. It also told the AI Writer to summarise Persian pages
 * in English.
 *
 * Resolution order:
 *
 * 1. the `medora_content_language` filter, which carries the post — this is
 *    where Polylang or WPML belongs, because on a multilingual site the
 *    language is a property of the page and no site-wide setting can be right;
 * 2. the `default_language` setting, when the publisher has stated one;
 * 3. `get_locale()`, unchanged from previous behaviour.
 */
final class ContentLanguage
{
    /**
     * Human-readable names for the LLM prompt, keyed by the two-letter subtag.
     *
     * A model asked to "write in fa-IR" behaves less reliably than one asked to
     * write in Persian, so the tag is not passed through raw.
     */
    private const NAMES = [
        'ar' => 'Arabic (العربية)',
        'de' => 'German',
        'en' => 'English',
        'es' => 'Spanish',
        'fa' => 'Persian (فارسی)',
        'fr' => 'French',
        'hi' => 'Hindi',
        'id' => 'Indonesian',
        'it' => 'Italian',
        'ku' => 'Kurdish',
        'nl' => 'Dutch',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'tr' => 'Turkish',
        'ur' => 'Urdu',
        'zh' => 'Chinese',
    ];

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * A BCP-47 tag, for `inLanguage` and the `lang` attribute.
     */
    public function tag(?WP_Post $post = null): string
    {
        $configured = trim($this->options->getString('default_language'));

        $tag = $configured !== '' ? $configured : (string) get_locale();

        /**
         * Filter the language of the content.
         *
         * Multilingual plugins hook here: they know the language of an
         * individual post, which no site-wide setting can.
         *
         * @param string       $tag  BCP-47 or a WordPress locale.
         * @param WP_Post|null $post Null for site-level output.
         */
        $tag = (string) apply_filters('medora_content_language', $tag, $post);

        // WordPress stores `fa_IR`; schema and HTML both want `fa-IR`.
        return str_replace('_', '-', trim($tag));
    }

    /**
     * The two-letter subtag, for anything that keys on language rather than
     * locale.
     */
    public function code(?WP_Post $post = null): string
    {
        return strtolower(substr($this->tag($post), 0, 2));
    }

    /**
     * The language named the way a person would name it, for prompts.
     */
    public function name(?WP_Post $post = null): string
    {
        return self::NAMES[$this->code($post)] ?? 'English';
    }

    /**
     * True when the content is written right-to-left.
     *
     * Read from the content language rather than from `is_rtl()`, which
     * describes the admin.
     */
    public function isRtl(?WP_Post $post = null): bool
    {
        return in_array($this->code($post), ['ar', 'fa', 'he', 'ur', 'ku', 'ps', 'sd', 'ug', 'yi'], true);
    }

    /**
     * Languages offered in the settings UI. The empty entry means "whatever
     * WordPress is set to", which is the default and the safe answer.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function choices(): array
    {
        $choices = [
            ['value' => '', 'label' => __('Follow the WordPress site language', 'medora-authority')],
        ];

        foreach (self::NAMES as $code => $name) {
            $choices[] = ['value' => $code, 'label' => $name];
        }

        return $choices;
    }
}
