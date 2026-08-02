<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One dimension of the AI Authority Score.
 */
final class ScoreComponent
{
    /**
     * @param list<Deduction>      $deductions
     * @param array<string, mixed> $metrics Raw numbers behind the score, for the UI.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly float $score,
        public readonly float $weight,
        public readonly array $deductions = [],
        public readonly array $metrics = [],
    ) {
    }

    public function weightedScore(): float
    {
        return $this->score * $this->weight;
    }

    public function grade(): string
    {
        return match (true) {
            $this->score >= 90 => 'A',
            $this->score >= 80 => 'B',
            $this->score >= 65 => 'C',
            $this->score >= 50 => 'D',
            default            => 'F',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $deductions = $this->deductions;
        usort($deductions, [Deduction::class, 'compare']);

        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'score'      => round($this->score, 1),
            'weight'     => $this->weight,
            'grade'      => $this->grade(),
            'metrics'    => $this->metrics,
            'deductions' => array_map(static fn (Deduction $d): array => $d->toArray(), $deductions),
        ];
    }
}
