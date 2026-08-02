<?php

declare(strict_types=1);

namespace Medora\Authority\Graph;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Predicates used in the knowledge graph.
 *
 * Chosen to map cleanly onto Schema.org properties so the graph can be
 * serialised as JSON-LD without inventing vocabulary an AI system has never
 * seen.
 */
final class RelationType
{
    public const MENTIONS      = 'mentions';
    public const ABOUT         = 'about';
    public const AUTHORED_BY   = 'author';
    public const REVIEWED_BY   = 'reviewedBy';
    public const PART_OF       = 'isPartOf';
    public const RELATED_TO    = 'relatedTo';
    public const SAME_AS       = 'sameAs';
    public const LOCATED_IN    = 'containedInPlace';
    public const PROVIDES      = 'availableService';
    public const TREATS        = 'possibleTreatment';
    public const SYMPTOM_OF    = 'signOrSymptom';
    public const AFFILIATED_TO = 'affiliation';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MENTIONS,
            self::ABOUT,
            self::AUTHORED_BY,
            self::REVIEWED_BY,
            self::PART_OF,
            self::RELATED_TO,
            self::SAME_AS,
            self::LOCATED_IN,
            self::PROVIDES,
            self::TREATS,
            self::SYMPTOM_OF,
            self::AFFILIATED_TO,
        ];
    }

    public static function isValid(string $predicate): bool
    {
        return in_array($predicate, self::all(), true);
    }

    /** Predicates whose inverse is itself. */
    public static function isSymmetric(string $predicate): bool
    {
        return in_array($predicate, [self::RELATED_TO, self::SAME_AS], true);
    }

    public static function label(string $predicate): string
    {
        return match ($predicate) {
            self::MENTIONS      => __('mentions', 'medora-authority'),
            self::ABOUT         => __('is about', 'medora-authority'),
            self::AUTHORED_BY   => __('written by', 'medora-authority'),
            self::REVIEWED_BY   => __('reviewed by', 'medora-authority'),
            self::PART_OF       => __('is part of', 'medora-authority'),
            self::RELATED_TO    => __('is related to', 'medora-authority'),
            self::SAME_AS       => __('is the same as', 'medora-authority'),
            self::LOCATED_IN    => __('is located in', 'medora-authority'),
            self::PROVIDES      => __('provides', 'medora-authority'),
            self::TREATS        => __('is a treatment for', 'medora-authority'),
            self::SYMPTOM_OF    => __('is a symptom of', 'medora-authority'),
            self::AFFILIATED_TO => __('is affiliated with', 'medora-authority'),
            default             => $predicate,
        };
    }
}
