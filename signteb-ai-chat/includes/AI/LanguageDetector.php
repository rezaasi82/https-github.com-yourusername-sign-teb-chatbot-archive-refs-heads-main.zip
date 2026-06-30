<?php

namespace STMC_Chat\AI;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the response language. Honors the admin setting; when set to
 * "auto" it detects from the message and falls back to the current Polylang /
 * site locale so multilingual sites answer in the page's language.
 */
class LanguageDetector
{
    public function resolve(string $setting, string $message): string
    {
        if (in_array($setting, ['fa', 'ar', 'en'], true)) {
            return $setting;
        }

        $detected = $this->detect_from_text($message);
        if ($detected !== '') {
            return $detected;
        }

        return $this->site_language();
    }

    private function detect_from_text(string $message): string
    {
        // Arabic-only letters not used in Persian → strong Arabic signal.
        if (preg_match('/[\x{0629}\x{064A}\x{0643}]/u', $message)) {
            return 'ar';
        }
        // Any Persian-specific letters → Persian.
        if (preg_match('/[\x{067E}\x{0686}\x{0698}\x{06AF}\x{06CC}\x{06A9}]/u', $message)) {
            return 'fa';
        }
        // Latin letters with no Arabic script → English.
        if (preg_match('/[a-zA-Z]/', $message) && ! preg_match('/[\x{0600}-\x{06FF}]/u', $message)) {
            return 'en';
        }
        return '';
    }

    private function site_language(): string
    {
        if (function_exists('pll_current_language')) {
            $pll = pll_current_language('slug');
            if (is_string($pll) && $pll !== '') {
                return in_array($pll, ['fa', 'ar', 'en'], true) ? $pll : 'fa';
            }
        }

        $locale = strtolower((string) get_locale());
        if (str_starts_with($locale, 'ar')) {
            return 'ar';
        }
        if (str_starts_with($locale, 'en')) {
            return 'en';
        }
        return 'fa';
    }
}
