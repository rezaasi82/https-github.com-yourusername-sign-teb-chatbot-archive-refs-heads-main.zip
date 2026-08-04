<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Score\AnalysisRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * The analysis cache is keyed on the content *and* the scoring configuration.
 *
 * Keying on content alone fails silently, which is what makes it worth a test:
 * nothing errors, no page looks wrong on its own, and the site report quietly
 * ranks scores computed under two different configurations against each other
 * until someone notices the numbers do not add up.
 */
final class ScoreCacheKeyTest extends TestCase
{
    public function testAddingADimensionChangesTheKey(): void
    {
        $this->assertNotSame(
            $this->calculator(['a' => 0.2, 'b' => 0.3])->cacheKey($this->post()),
            $this->calculator(['a' => 0.2, 'b' => 0.3, 'c' => 0.14])->cacheKey($this->post())
        );
    }

    public function testReweightingADimensionChangesTheKey(): void
    {
        // Same scorers, different weights: every score on the site moves.
        $this->assertNotSame(
            $this->calculator(['a' => 0.2, 'b' => 0.3])->cacheKey($this->post()),
            $this->calculator(['a' => 0.25, 'b' => 0.3])->cacheKey($this->post())
        );
    }

    public function testReplacingADimensionChangesTheKey(): void
    {
        $this->assertNotSame(
            $this->calculator(['a' => 0.2, 'b' => 0.3])->cacheKey($this->post()),
            $this->calculator(['a' => 0.2, 'z' => 0.3])->cacheKey($this->post())
        );
    }

    public function testRegistrationOrderDoesNotChangeTheKey(): void
    {
        // Order comes from the module dependency graph, which may legitimately
        // change between releases without the scoring changing at all. If that
        // invalidated the cache, every upgrade would re-score the whole site.
        $ordered = $this->calculator([]);
        $ordered->addScorer($this->scorer('a', 0.2));
        $ordered->addScorer($this->scorer('b', 0.3));

        $reversed = $this->calculator([]);
        $reversed->addScorer($this->scorer('b', 0.3));
        $reversed->addScorer($this->scorer('a', 0.2));

        $this->assertSame(
            $ordered->cacheKey($this->post()),
            $reversed->cacheKey($this->post())
        );
    }

    public function testIdenticalConfigurationsAgreeAcrossInstances(): void
    {
        $this->assertSame(
            $this->calculator(['a' => 0.2, 'b' => 0.3])->cacheKey($this->post()),
            $this->calculator(['a' => 0.2, 'b' => 0.3])->cacheKey($this->post())
        );
    }

    public function testEditingTheContentStillChangesTheKey(): void
    {
        $calculator = $this->calculator(['a' => 0.2]);

        $this->assertNotSame(
            $calculator->cacheKey($this->post('Title', '<p>One.</p>')),
            $calculator->cacheKey($this->post('Title', '<p>Two.</p>'))
        );
    }

    public function testEditingTheTitleStillChangesTheKey(): void
    {
        $calculator = $this->calculator(['a' => 0.2]);

        $this->assertNotSame(
            $calculator->cacheKey($this->post('One', '<p>Body.</p>')),
            $calculator->cacheKey($this->post('Two', '<p>Body.</p>'))
        );
    }

    public function testSignatureMemoIsInvalidatedWhenAScorerIsAdded(): void
    {
        $calculator = $this->calculator(['a' => 0.2]);
        $before     = $calculator->signature();

        $calculator->addScorer($this->scorer('b', 0.3));

        $this->assertNotSame($before, $calculator->signature());
    }

    /**
     * @param array<string, float> $scorers
     */
    private function calculator(array $scorers): AuthorityScoreCalculator
    {
        $calculator = new AuthorityScoreCalculator(new AnalysisRepository());

        foreach ($scorers as $id => $weight) {
            $calculator->addScorer($this->scorer($id, $weight));
        }

        return $calculator;
    }

    private function scorer(string $id, float $weight): ScorerInterface
    {
        return new class ($id, $weight) implements ScorerInterface {
            public function __construct(
                private readonly string $id,
                private readonly float $weight,
            ) {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function label(): string
            {
                return $this->id;
            }

            public function weight(): float
            {
                return $this->weight;
            }

            public function appliesTo(WP_Post $post): bool
            {
                return true;
            }

            public function score(WP_Post $post): ScoreComponent
            {
                return new ScoreComponent($this->id, $this->id, 100.0, $this->weight);
            }
        };
    }

    private function post(string $title = 'Fatty liver disease', string $content = '<p>Body.</p>'): WP_Post
    {
        return new WP_Post((object) [
            'ID'           => 1,
            'post_title'   => $title,
            'post_content' => $content,
        ]);
    }
}
