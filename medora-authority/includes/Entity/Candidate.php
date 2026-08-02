<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An entity proposed by an extractor, before merging and scoring.
 *
 * Extractors do not write to the database; they emit candidates and let
 * `EntityExtractor` merge duplicates, weigh confidence and decide what is
 * worth persisting.
 */
final class Candidate
{
    public function __construct(
        public readonly Entity $entity,
        public readonly int $occurrences = 1,
        /** 0..1 — how sure the extractor is that this is a real entity. */
        public readonly float $confidence = 0.5,
        /** Extractor id, kept for provenance and debugging. */
        public readonly string $source = '',
    ) {
    }

    public function uid(): string
    {
        return $this->entity->uid !== ''
            ? $this->entity->uid
            : Entity::uidFor($this->entity->type, $this->entity->name);
    }

    public function mergedWith(self $other): self
    {
        // Keep the record from whichever extractor was more confident, but sum
        // the evidence: two extractors independently finding the same entity is
        // a stronger signal than either alone.
        $winner = $this->confidence >= $other->confidence ? $this : $other;

        return new self(
            $winner->entity,
            $this->occurrences + $other->occurrences,
            min(1.0, max($this->confidence, $other->confidence) + 0.1),
            $winner->source,
        );
    }
}
