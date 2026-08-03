<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders a citation in the common academic styles.
 *
 * Plain text out; the caller escapes for its own context.
 */
final class CitationFormatter
{
    public const APA       = 'apa';
    public const VANCOUVER = 'vancouver';
    public const HARVARD   = 'harvard';

    /** @return array<string, string> style key => translated label */
    public static function styles(): array
    {
        return [
            self::APA       => __('APA (7th edition)', 'medora-authority'),
            self::VANCOUVER => __('Vancouver', 'medora-authority'),
            self::HARVARD   => __('Harvard', 'medora-authority'),
        ];
    }

    public function format(Citation $citation, string $style = self::APA): string
    {
        return match ($style) {
            self::VANCOUVER => $this->vancouver($citation),
            self::HARVARD   => $this->harvard($citation),
            default         => $this->apa($citation),
        };
    }

    private function apa(Citation $citation): string
    {
        $authors = $this->apaAuthors($citation->authors);
        $year    = $citation->year > 0 ? sprintf('(%d).', $citation->year) : '(n.d.).';
        $parts   = array_filter([
            $authors,
            $year,
            rtrim($citation->title, '.') . '.',
            $citation->container !== '' ? $citation->container . '.' : '',
            $citation->doi !== '' ? 'https://doi.org/' . $citation->doi : $citation->url,
        ]);

        return trim(implode(' ', $parts));
    }

    private function vancouver(Citation $citation): string
    {
        // Vancouver lists up to six authors, then "et al."
        $authors = array_slice($citation->authors, 0, 6);
        $names   = implode(', ', array_map([$this, 'surnameInitials'], $authors));

        if (count($citation->authors) > 6) {
            $names .= ', et al';
        }

        $parts = array_filter([
            $names !== '' ? $names . '.' : '',
            rtrim($citation->title, '.') . '.',
            $citation->container !== '' ? $citation->container . '.' : '',
            $citation->year > 0 ? $citation->year . '.' : '',
            $citation->doi !== '' ? 'doi:' . $citation->doi : '',
            $citation->pmid !== '' ? 'PMID: ' . $citation->pmid : '',
        ]);

        return trim(implode(' ', $parts));
    }

    private function harvard(Citation $citation): string
    {
        $authors = $this->apaAuthors($citation->authors);
        $parts   = array_filter([
            $authors,
            $citation->year > 0 ? $citation->year . ',' : '',
            "'" . rtrim($citation->title, '.') . "',",
            $citation->container !== '' ? $citation->container . ',' : '',
            $citation->canonicalUrl(),
        ]);

        return trim(implode(' ', $parts));
    }

    /** @param list<string> $authors */
    private function apaAuthors(array $authors): string
    {
        if ($authors === []) {
            return '';
        }

        $formatted = array_map([$this, 'surnameInitialsPeriod'], array_slice($authors, 0, 20));

        if (count($formatted) === 1) {
            return $formatted[0];
        }

        $last = array_pop($formatted);

        return implode(', ', $formatted) . ', & ' . $last;
    }

    private function surnameInitials(string $name): string
    {
        [$surname, $initials] = $this->splitName($name);

        return trim($surname . ' ' . $initials);
    }

    private function surnameInitialsPeriod(string $name): string
    {
        [$surname, $initials] = $this->splitName($name);

        $spaced = implode('. ', str_split($initials));

        return trim($surname . ', ' . ($spaced !== '' ? $spaced . '.' : ''));
    }

    /**
     * @return array{0: string, 1: string} surname, initials
     */
    private function splitName(string $name): array
    {
        $name  = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            return [$name, ''];
        }

        // PubMed already returns "Surname AB"; CrossRef returns "Given Family".
        // A trailing all-caps token of one to three letters is the initials.
        $last = end($parts);

        if (preg_match('/^\p{Lu}{1,3}$/u', $last) === 1) {
            array_pop($parts);

            return [implode(' ', $parts), $last];
        }

        $surname  = array_pop($parts);
        $initials = implode('', array_map(
            static fn (string $part): string => mb_substr($part, 0, 1, 'UTF-8'),
            $parts
        ));

        return [$surname, mb_strtoupper($initials, 'UTF-8')];
    }
}
