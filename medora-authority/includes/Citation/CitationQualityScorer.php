<?php

declare(strict_types=1);

namespace Medora\Authority\Citation;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scores a single reference 0–100.
 *
 * Four signals: how strong the study design is, whether the reference is
 * durably identified, how recent it is, and how much the field has engaged
 * with it. Recency is weighted but never dominant — a 2003 landmark trial
 * should still outrank a 2024 case report.
 */
final class CitationQualityScorer
{
    public function score(
        int $year,
        bool $hasDoi,
        string $container = '',
        int $citedBy = 0,
        string $evidenceLevel = ''
    ): float {
        // Design quality (max 40).
        $score = $evidenceLevel !== ''
            ? EvidenceLevel::strength($evidenceLevel) * 0.40
            : 20.0;

        // Persistent identifier (max 20). A reference without one may become
        // unresolvable, which makes the claim uncheckable.
        if ($hasDoi) {
            $score += 20.0;
        }

        // Recency (max 20), on a five-year half-life.
        if ($year > 0) {
            $age    = max(0, (int) gmdate('Y') - $year);
            $score += 20.0 * exp(-$age / 8);
        }

        // Field engagement (max 15), log-scaled.
        if ($citedBy > 0) {
            $score += min(15.0, 15.0 * (log($citedBy + 1, 10) / log(1001, 10)));
        }

        // Named venue (max 5).
        if (trim($container) !== '') {
            $score += 5.0;
        }

        return round(min(100.0, $score), 1);
    }
}
