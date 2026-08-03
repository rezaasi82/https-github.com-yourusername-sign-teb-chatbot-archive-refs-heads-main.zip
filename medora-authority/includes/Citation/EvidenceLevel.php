<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Evidence hierarchy, following the conventional levels used in
 * evidence-based medicine.
 *
 * Surfacing this matters because an AI system weighing two contradictory
 * claims has no way to tell a randomised trial from a case report unless the
 * publisher says so.
 */
final class EvidenceLevel
{
    /** Systematic review or meta-analysis of RCTs. */
    public const LEVEL_1A = '1a';
    /** Individual randomised controlled trial. */
    public const LEVEL_1B = '1b';
    /** Systematic review of cohort studies. */
    public const LEVEL_2A = '2a';
    /** Individual cohort study. */
    public const LEVEL_2B = '2b';
    /** Case-control studies. */
    public const LEVEL_3  = '3';
    /** Case series. */
    public const LEVEL_4  = '4';
    /** Expert opinion. */
    public const LEVEL_5  = '5';
    /** Clinical practice guideline. */
    public const GUIDELINE = 'guideline';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::LEVEL_1A,
            self::LEVEL_1B,
            self::LEVEL_2A,
            self::LEVEL_2B,
            self::LEVEL_3,
            self::LEVEL_4,
            self::LEVEL_5,
            self::GUIDELINE,
        ];
    }

    public static function label(string $level): string
    {
        return match ($level) {
            self::LEVEL_1A  => __('Systematic review / meta-analysis', 'medora-authority'),
            self::LEVEL_1B  => __('Randomised controlled trial', 'medora-authority'),
            self::LEVEL_2A  => __('Systematic review of cohort studies', 'medora-authority'),
            self::LEVEL_2B  => __('Cohort study', 'medora-authority'),
            self::LEVEL_3   => __('Case-control study', 'medora-authority'),
            self::LEVEL_4   => __('Case series', 'medora-authority'),
            self::LEVEL_5   => __('Expert opinion', 'medora-authority'),
            self::GUIDELINE => __('Clinical practice guideline', 'medora-authority'),
            default         => __('Unclassified', 'medora-authority'),
        };
    }

    /** Relative strength, 0–100, used by the quality scorer. */
    public static function strength(string $level): float
    {
        return match ($level) {
            self::LEVEL_1A  => 100.0,
            self::GUIDELINE => 95.0,
            self::LEVEL_1B  => 90.0,
            self::LEVEL_2A  => 75.0,
            self::LEVEL_2B  => 65.0,
            self::LEVEL_3   => 50.0,
            self::LEVEL_4   => 35.0,
            self::LEVEL_5   => 20.0,
            default         => 40.0,
        };
    }

    /**
     * Best-effort classification from a publication-type list.
     *
     * @param list<string>|string $types
     */
    public static function fromPublicationType(array|string $types): string
    {
        $haystack = strtolower(is_array($types) ? implode(' ', array_map('strval', $types)) : $types);

        return match (true) {
            str_contains($haystack, 'meta-analysis'), str_contains($haystack, 'systematic review') => self::LEVEL_1A,
            str_contains($haystack, 'guideline')                                                   => self::GUIDELINE,
            str_contains($haystack, 'randomized'), str_contains($haystack, 'randomised'),
            str_contains($haystack, 'clinical trial')                                              => self::LEVEL_1B,
            str_contains($haystack, 'cohort')                                                      => self::LEVEL_2B,
            str_contains($haystack, 'case-control')                                                => self::LEVEL_3,
            str_contains($haystack, 'case report'), str_contains($haystack, 'case series')         => self::LEVEL_4,
            str_contains($haystack, 'editorial'), str_contains($haystack, 'comment')               => self::LEVEL_5,
            default                                                                                => '',
        };
    }
}
