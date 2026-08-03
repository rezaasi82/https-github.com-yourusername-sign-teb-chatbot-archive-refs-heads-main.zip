<?php

declare(strict_types=1);

namespace Medora\Authority\Llm;

use Medora\Authority\Support\Text;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Checks that generated text says nothing the source page did not.
 *
 * This is the reason Medora is willing to publish model output at all. The
 * product's standing decision is that a paraphrase which introduces claims the
 * publisher never made is a liability on a health site, not a feature — so
 * generation is allowed only where that can be verified mechanically, after
 * the fact, on every single response. Prompt instructions are not verification;
 * they are a request.
 *
 * Two checks, with different severities:
 *
 * 1. **Lexical support.** Every content word in the output should appear
 *    somewhere in the source. Paraphrase legitimately introduces some words —
 *    hence a ratio, not a rule — but a sentence built mostly from vocabulary
 *    absent from the page is describing something else.
 *
 * 2. **Numeric fidelity.** Any figure in the output must appear verbatim in
 *    the source. This is a hard failure with no tolerance, because the specific
 *    hazard on a medical page is a fabricated dose, percentage or duration —
 *    and a single invented number can be lexically well-supported by the
 *    sentence around it.
 *
 * The check is language-agnostic: it runs on `Text::tokens()`, so Persian and
 * Arabic are folded and compared on the same footing as English.
 */
final class Grounding
{
    /** Site-wide support ratio below which output is rejected. */
    public const MIN_SUPPORT = 0.82;

    /** Per-sentence ratio below which a sentence is reported as unsupported. */
    private const SENTENCE_FLOOR = 0.6;

    /**
     * @return array{
     *     supported: bool,
     *     ratio: float,
     *     unsupported_sentences: list<string>,
     *     invented_numbers: list<string>
     * }
     */
    public static function check(string $generated, string $source): array
    {
        $generated = trim($generated);

        if ($generated === '') {
            // Nothing said cannot be unfaithful, but it is also not usable
            // output. Callers gate on emptiness before getting here.
            return [
                'supported'             => true,
                'ratio'                 => 1.0,
                'unsupported_sentences' => [],
                'invented_numbers'      => [],
            ];
        }

        $sourceTokens  = array_flip(Text::tokens($source));
        $sourceNumbers = array_flip(self::numbers($source));

        $total     = 0;
        $supported = 0;
        $flagged   = [];

        foreach (Text::sentences($generated) as $sentence) {
            $tokens = Text::tokens($sentence);

            if ($tokens === []) {
                continue;
            }

            $hits = 0;

            foreach ($tokens as $token) {
                if (isset($sourceTokens[$token])) {
                    $hits++;
                }
            }

            $total     += count($tokens);
            $supported += $hits;

            if (($hits / count($tokens)) < self::SENTENCE_FLOOR) {
                $flagged[] = $sentence;
            }
        }

        $invented = [];

        foreach (self::numbers($generated) as $number) {
            if (! isset($sourceNumbers[$number])) {
                $invented[] = $number;
            }
        }

        $ratio = $total > 0 ? $supported / $total : 1.0;

        return [
            'supported'             => $ratio >= self::MIN_SUPPORT && $invented === [],
            'ratio'                 => round($ratio, 4),
            'unsupported_sentences' => $flagged,
            'invented_numbers'      => array_values(array_unique($invented)),
        ];
    }

    /**
     * Numeric literals, with Persian and Arabic-Indic digits folded to ASCII
     * and trailing zeros normalised, so "۲۵" in the source vouches for "25" in
     * the output and "2.50" does not read as a different figure from "2.5".
     *
     * @return list<string>
     */
    private static function numbers(string $text): array
    {
        $text = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            // Three separate characters, all of which appear in Persian and
            // Arabic copy: the decimal separator U+066B, the thousands
            // separator U+066C, and the ordinary comma U+060C. Miss the
            // thousands separator and "۱٬۵۰۰" reads as the two figures 1 and
            // 500, so a faithful "1500" in the output looks invented.
            '٫' => '.', '٬' => ',', '،' => ',',
        ]);

        // Thousands separators are stripped so 1,500 and 1500 are one figure.
        $text = (string) preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $text);

        preg_match_all('/\d+(?:\.\d+)?/', $text, $matches);

        $numbers = [];

        foreach ($matches[0] as $number) {
            if (str_contains($number, '.')) {
                $number = rtrim(rtrim($number, '0'), '.');
            }

            $numbers[] = ltrim($number, '0') === '' ? '0' : ltrim($number, '0');
        }

        return $numbers;
    }
}
