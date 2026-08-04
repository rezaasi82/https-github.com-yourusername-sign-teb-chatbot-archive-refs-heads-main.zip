<?php

declare(strict_types=1);

namespace Medora\Authority\Score;

use Medora\Authority\Support\Text;
use Throwable;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The AI Authority Score.
 *
 * Runs every applicable scorer, normalises their weights and produces a single
 * 0–100 figure with a complete derivation. Two properties are non-negotiable:
 *
 * 1. **Every deduction is explained.** The score is a diagnostic, not a grade.
 * 2. **Weights re-normalise when a dimension does not apply.** On a
 *    non-medical site, Medical Trust is skipped and the remaining weights are
 *    scaled to sum to 1 — so a general site is never permanently capped below
 *    100 for lacking a clinical reviewer.
 */
final class AuthorityScoreCalculator
{
    /** @var list<ScorerInterface> */
    private array $scorers = [];

    /** Memoised across a request; invalidated whenever a scorer is added. */
    private ?string $signature = null;

    public function __construct(private readonly AnalysisRepository $repository)
    {
    }

    public function addScorer(ScorerInterface $scorer): void
    {
        $this->scorers[] = $scorer;
        $this->signature = null;
    }

    /**
     * A fingerprint of the scoring configuration itself.
     *
     * Caching an analysis on the content hash alone is wrong, and quietly so:
     * the stored score is a function of the *scorer set* as much as of the
     * text. Add a dimension in an upgrade, enable a module that contributes
     * one, or switch the site from general to medical mode, and every page
     * whose content has not changed keeps a score computed under the old
     * configuration — leaving the site report ranking six-dimension scores
     * against seven-dimension ones as though they were comparable.
     *
     * Folding this into the cache key makes any such change self-invalidating.
     * Weights are included as well as ids, because re-weighting a dimension
     * changes every score without changing which scorers ran.
     */
    public function signature(): string
    {
        if ($this->signature !== null) {
            return $this->signature;
        }

        $parts = array_map(
            static fn (ScorerInterface $scorer): string => $scorer->id() . ':' . $scorer->weight(),
            $this->scorers
        );

        // Sorted, so registration order — which the module graph may legitimately
        // change between releases — does not invalidate every analysis on the site.
        sort($parts);

        return $this->signature = substr(Text::hash(implode('|', $parts)), 0, 12);
    }

    /**
     * The cache key for one post: its content *and* the configuration the
     * score would be computed under.
     */
    public function cacheKey(WP_Post $post): string
    {
        return Text::hash($post->post_title . "\n" . $post->post_content . "\n#" . $this->signature());
    }

    /** @return list<ScorerInterface> */
    public function scorers(): array
    {
        return $this->scorers;
    }

    /**
     * @return array{
     *     post_id: int,
     *     overall: float,
     *     grade: string,
     *     components: list<array<string, mixed>>,
     *     deductions: list<array<string, mixed>>,
     *     analyzed_at: string,
     *     cached: bool
     * }
     */
    public function analyze(WP_Post $post, bool $force = false): array
    {
        $hash = $this->cacheKey($post);

        if (! $force) {
            $cached = $this->repository->find('post', $post->ID);

            if ($cached !== null && $cached['content_hash'] === $hash) {
                return $cached['result'] + ['cached' => true];
            }
        }

        $components   = [];
        $totalWeight  = 0.0;
        $weightedSum  = 0.0;
        $allDeductions = [];

        foreach ($this->scorers as $scorer) {
            if (! $scorer->appliesTo($post)) {
                continue;
            }

            try {
                $component = $scorer->score($post);
            } catch (Throwable $exception) {
                // One broken scorer — including a third-party one — must not
                // take down the whole analysis.
                do_action('medora_scorer_failed', $scorer->id(), $exception);

                continue;
            }

            $components[]  = $component;
            $totalWeight  += $component->weight;
            $weightedSum  += $component->weightedScore();

            foreach ($component->deductions as $deduction) {
                $allDeductions[] = $deduction->toArray() + ['component' => $component->id];
            }
        }

        // Re-normalise so skipped dimensions do not silently cap the score.
        $overall = $totalWeight > 0.0 ? $weightedSum / $totalWeight : 0.0;

        /**
         * Filter the overall AI Authority Score.
         *
         * @param float                $overall    0–100.
         * @param list<ScoreComponent> $components
         * @param WP_Post              $post
         */
        $overall = (float) apply_filters('medora_authority_score', $overall, $components, $post);
        $overall = round(min(100.0, max(0.0, $overall)), 1);

        usort($allDeductions, static function (array $a, array $b): int {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return [$rank[$a['severity']] ?? 9, -$a['points']] <=> [$rank[$b['severity']] ?? 9, -$b['points']];
        });

        $result = [
            'post_id'     => $post->ID,
            'overall'     => $overall,
            'grade'       => self::gradeFor($overall),
            'components'  => array_map(static fn (ScoreComponent $c): array => $c->toArray(), $components),
            'deductions'  => $allDeductions,
            'analyzed_at' => current_time('mysql', true),
        ];

        $this->repository->save('post', $post->ID, $overall, $result, $hash);

        /**
         * Fires after a page has been scored.
         *
         * @param WP_Post              $post
         * @param array<string, mixed> $result
         */
        do_action('medora_post_analyzed', $post, $result);

        return $result + ['cached' => false];
    }

    /**
     * Site-level roll-up.
     *
     * @return array{
     *     average: float,
     *     grade: string,
     *     analyzed: int,
     *     distribution: array<string, int>,
     *     weakest: list<array<string, mixed>>,
     *     top_issues: list<array{code: string, label: string, count: int, recommendation: string}>
     * }
     */
    public function siteReport(): array
    {
        $average      = $this->repository->averageScore();
        $distribution = $this->repository->distribution();

        return [
            'average'      => round($average, 1),
            'grade'        => self::gradeFor($average),
            'analyzed'     => $this->repository->count(),
            'distribution' => $distribution,
            'weakest'      => $this->repository->weakest(10),
            'top_issues'   => $this->repository->topIssues(8),
        ];
    }

    public static function gradeFor(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };
    }
}
