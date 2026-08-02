<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One dimension of the AI Authority Score.
 *
 * Scorers start at 100 and deduct, rather than accumulating from zero. That
 * inversion is what makes every result explainable: the deduction list *is*
 * the derivation, so the UI never has to reverse-engineer why a page scored
 * what it scored.
 */
interface ScorerInterface
{
    public function id(): string;

    public function label(): string;

    /** Relative weight in the overall score; the calculator normalises these. */
    public function weight(): float;

    /** False when the dimension does not apply, e.g. medical trust on a non-medical site. */
    public function appliesTo(WP_Post $post): bool;

    public function score(WP_Post $post): ScoreComponent;
}
