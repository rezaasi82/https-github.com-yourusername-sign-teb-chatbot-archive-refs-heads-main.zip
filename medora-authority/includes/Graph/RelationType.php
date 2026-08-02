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

    // Clinical predicates. Each maps onto a real Schema.org medical property,
    // so a curated edge serialises into JSON-LD without inventing vocabulary an
    // AI system has never seen.
    public const DIAGNOSED_BY   = 'typicalTest';
    public const RISK_FACTOR    = 'riskFactor';
    public const AFFECTS_ANATOMY = 'associatedAnatomy';
    public const COMPLICATION_OF = 'possibleComplication';
    public const DRUG_FOR        = 'drug';
    public const SPECIALTY_OF    = 'relevantSpecialty';

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
            self::DIAGNOSED_BY,
            self::RISK_FACTOR,
            self::AFFECTS_ANATOMY,
            self::COMPLICATION_OF,
            self::DRUG_FOR,
            self::SPECIALTY_OF,
        ];
    }

    /**
     * Predicates that assert a clinical claim.
     *
     * These are held to a higher bar than the inferred ones: they are only ever
     * written from a curated ontology, never from co-occurrence, because a
     * wrong "is a treatment for" edge is machine-readable misinformation.
     *
     * @return list<string>
     */
    public static function clinical(): array
    {
        return [
            self::TREATS,
            self::SYMPTOM_OF,
            self::DIAGNOSED_BY,
            self::RISK_FACTOR,
            self::AFFECTS_ANATOMY,
            self::COMPLICATION_OF,
            self::DRUG_FOR,
        ];
    }

    public static function isClinical(string $predicate): bool
    {
        return in_array($predicate, self::clinical(), true);
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
            self::DIAGNOSED_BY  => __('is diagnosed by', 'medora-authority'),
            self::RISK_FACTOR   => __('is a risk factor for', 'medora-authority'),
            self::AFFECTS_ANATOMY  => __('affects', 'medora-authority'),
            self::COMPLICATION_OF  => __('is a complication of', 'medora-authority'),
            self::DRUG_FOR         => __('is a drug for', 'medora-authority'),
            self::SPECIALTY_OF     => __('is treated by the specialty', 'medora-authority'),
            default             => $predicate,
        };
    }
}
