<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Is it unambiguous what this page is about, and can that subject be resolved
 * to a known entity?
 *
 * Named `EntityScorer` rather than `EntityAuthorityScorer` to keep it distinct
 * from {@see \Medora\Authority\Entity\EntityAuthorityScorer}, which scores a
 * single entity's site-wide standing. This one scores a *page* on how well it
 * establishes its subject.
 */
final class EntityScorer implements ScorerInterface
{
    public function __construct(private readonly EntityRepository $entities)
    {
    }

    public function id(): string
    {
        return 'entity_authority';
    }

    public function label(): string
    {
        return __('Entity Authority', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.18;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $indexed    = $this->entities->forObject('post', $post->ID, 30);
        $score      = 100.0;
        $deductions = [];

        $metrics = [
            'entity_count'    => count($indexed),
            'primary_entity'  => $indexed === [] ? '' : $indexed[0]['entity']->name,
            'primary_salience' => $indexed === [] ? 0.0 : round($indexed[0]['salience'], 3),
        ];

        if ($indexed === []) {
            return new ScoreComponent(
                $this->id(),
                $this->label(),
                0.0,
                $this->weight(),
                [new Deduction(
                    'no_entities',
                    __('No entities detected', 'medora-authority'),
                    100,
                    __('Assign categories or tags, and name the subject explicitly in the title and opening paragraph.', 'medora-authority'),
                    Deduction::SEVERITY_CRITICAL
                )],
                $metrics
            );
        }

        $primary = $indexed[0];

        // --- A clear subject -------------------------------------------------
        if ($primary['salience'] < 0.35) {
            $penalty = 30.0;
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'weak_primary_entity',
                __('No clear primary subject', 'medora-authority'),
                $penalty,
                __('The page touches several topics without committing to one. Name the main subject in the title and first sentence.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        // --- External reconciliation ------------------------------------------
        $withSameAs = 0;

        foreach ($indexed as $row) {
            if ($row['entity']->sameAs !== []) {
                $withSameAs++;
            }
        }

        $metrics['entities_with_sameas'] = $withSameAs;

        if ($withSameAs === 0) {
            $score -= 25;
            $deductions[] = new Deduction(
                'no_reconciliation',
                __('No entity is linked to an external identifier', 'medora-authority'),
                25,
                __('Add Wikidata, MeSH or official-site links to your key terms so AI systems can match them to their own knowledge base.', 'medora-authority'),
                Deduction::SEVERITY_HIGH,
                'entity-editor'
            );
        }

        // --- Type specificity --------------------------------------------------
        $generic = 0;

        foreach ($indexed as $row) {
            if ($row['entity']->type === EntityType::THING || $row['entity']->type === EntityType::TOPIC) {
                $generic++;
            }
        }

        $genericRatio           = $generic / count($indexed);
        $metrics['generic_ratio'] = round($genericRatio, 2);

        if ($genericRatio > 0.8) {
            $penalty = 20.0;
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'generic_types',
                __('Entities are untyped', 'medora-authority'),
                $penalty,
                __('Assign specific Schema.org types (Person, MedicalCondition, Product…) instead of leaving everything as a generic topic.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'entity-editor'
            );
        }

        // --- Site-wide standing on this subject ---------------------------------
        $authority                    = $primary['entity']->authorityScore;
        $metrics['primary_authority'] = $authority;

        if ($authority < 40) {
            $penalty = 15.0;
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'shallow_entity_coverage',
                __('The site has thin coverage of this subject', 'medora-authority'),
                $penalty,
                __('One page rarely establishes authority. Publish supporting pages that cover adjacent questions and link them together.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            max(0.0, $score),
            $this->weight(),
            $deductions,
            $metrics
        );
    }
}
