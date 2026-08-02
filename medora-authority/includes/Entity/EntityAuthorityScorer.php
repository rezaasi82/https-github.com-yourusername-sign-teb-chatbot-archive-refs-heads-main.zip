<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Core\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scores how authoritative the site is *on a given entity*, 0–100.
 *
 * The question this answers is not "does the page mention X" but "if an AI
 * system needed a source about X, is this site a defensible choice?". Six
 * signals feed it, each capped so no single one can carry a weak entity:
 *
 * | Signal              | Max | Why it matters                                    |
 * |---------------------|-----|---------------------------------------------------|
 * | Topical depth       |  25 | Several pages beat one page; depth reads as expertise |
 * | Focus / salience    |  20 | At least one page must be genuinely *about* it     |
 * | Reconciliation      |  20 | sameAs links let an LLM match it to its own graph   |
 * | Graph connectivity  |  15 | Isolated entities carry no context                 |
 * | Definition          |  10 | A description is what gets quoted                  |
 * | Type specificity    |  10 | `MedicalCondition` says more than `Thing`          |
 */
final class EntityAuthorityScorer
{
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function score(Entity $entity): float
    {
        $score = 0.0;

        $score += $this->depthScore($entity);
        $score += $this->focusScore($entity);
        $score += $this->reconciliationScore($entity);
        $score += $this->connectivityScore($entity);
        $score += $this->definitionScore($entity);
        $score += $this->typeScore($entity);

        /**
         * Filter an entity's authority score.
         *
         * @param float  $score  0–100.
         * @param Entity $entity
         */
        return (float) min(100.0, max(0.0, apply_filters('medora_entity_authority_score', $score, $entity)));
    }

    /**
     * Explain the score, component by component. The dashboard renders this
     * verbatim — a score without a reason is not actionable.
     *
     * @return array{score: float, components: list<array{id: string, label: string, points: float, max: float, note: string}>}
     */
    public function explain(Entity $entity): array
    {
        $documents = $this->repository->documentFrequency($entity->id);
        $relations = $this->relationCount($entity->id);

        $components = [
            [
                'id'     => 'depth',
                'label'  => __('Topical depth', 'medora-authority'),
                'points' => round($this->depthScore($entity), 1),
                'max'    => 25.0,
                'note'   => sprintf(
                    /* translators: %d: number of pages. */
                    _n('%d page covers this entity.', '%d pages cover this entity.', $documents, 'medora-authority'),
                    $documents
                ),
            ],
            [
                'id'     => 'focus',
                'label'  => __('Editorial focus', 'medora-authority'),
                'points' => round($this->focusScore($entity), 1),
                'max'    => 20.0,
                'note'   => $this->maxSalience($entity->id) >= 0.5
                    ? __('At least one page is primarily about this entity.', 'medora-authority')
                    : __('No page treats this entity as its main subject.', 'medora-authority'),
            ],
            [
                'id'     => 'reconciliation',
                'label'  => __('External reconciliation', 'medora-authority'),
                'points' => round($this->reconciliationScore($entity), 1),
                'max'    => 20.0,
                'note'   => $entity->sameAs === []
                    ? __('No sameAs identifiers. AI systems cannot match this to a known entity.', 'medora-authority')
                    : sprintf(
                        /* translators: %d: number of identifiers. */
                        _n('%d external identifier linked.', '%d external identifiers linked.', count($entity->sameAs), 'medora-authority'),
                        count($entity->sameAs)
                    ),
            ],
            [
                'id'     => 'connectivity',
                'label'  => __('Graph connectivity', 'medora-authority'),
                'points' => round($this->connectivityScore($entity), 1),
                'max'    => 15.0,
                'note'   => sprintf(
                    /* translators: %d: number of relationships. */
                    _n('%d relationship in the knowledge graph.', '%d relationships in the knowledge graph.', $relations, 'medora-authority'),
                    $relations
                ),
            ],
            [
                'id'     => 'definition',
                'label'  => __('Definition', 'medora-authority'),
                'points' => round($this->definitionScore($entity), 1),
                'max'    => 10.0,
                'note'   => $entity->description === ''
                    ? __('No description. Add one — this is the text an answer engine quotes.', 'medora-authority')
                    : __('Description present.', 'medora-authority'),
            ],
            [
                'id'     => 'type',
                'label'  => __('Type specificity', 'medora-authority'),
                'points' => round($this->typeScore($entity), 1),
                'max'    => 10.0,
                'note'   => $entity->type === EntityType::THING
                    ? __('Generic type. Assign a specific Schema.org type.', 'medora-authority')
                    : sprintf(
                        /* translators: %s: Schema.org type. */
                        __('Typed as %s.', 'medora-authority'),
                        $entity->type
                    ),
            ],
        ];

        return [
            'score'      => round($this->score($entity), 1),
            'components' => $components,
        ];
    }

    /** Recompute and persist scores for a batch of entities. */
    public function rescoreBatch(int $limit = 200, int $page = 1): int
    {
        $result  = $this->repository->query(['per_page' => $limit, 'page' => $page, 'orderby' => 'recent']);
        $updated = 0;

        foreach ($result['items'] as $entity) {
            $this->repository->updateAuthorityScore($entity->id, $this->score($entity));
            $updated++;
        }

        return $updated;
    }

    /**
     * Diminishing returns on page count: the jump from one page to three is
     * meaningful, from thirty to forty is not.
     */
    private function depthScore(Entity $entity): float
    {
        $documents = $this->repository->documentFrequency($entity->id);

        if ($documents === 0) {
            return 0.0;
        }

        return min(25.0, 25.0 * (log($documents + 1, 2) / log(17, 2)));
    }

    private function focusScore(Entity $entity): float
    {
        return min(20.0, $this->maxSalience($entity->id) * 20.0);
    }

    private function reconciliationScore(Entity $entity): float
    {
        $links = count($entity->sameAs);

        if ($links === 0) {
            return 0.0;
        }

        // One good identifier does most of the work; the rest are corroboration.
        return min(20.0, 12.0 + (($links - 1) * 4.0));
    }

    private function connectivityScore(Entity $entity): float
    {
        $relations = $this->relationCount($entity->id);

        if ($relations === 0) {
            return 0.0;
        }

        return min(15.0, 15.0 * (log($relations + 1, 2) / log(9, 2)));
    }

    private function definitionScore(Entity $entity): float
    {
        $length = mb_strlen(trim($entity->description), 'UTF-8');

        return match (true) {
            $length === 0 => 0.0,
            $length < 50  => 4.0,
            $length < 120 => 7.0,
            default       => 10.0,
        };
    }

    private function typeScore(Entity $entity): float
    {
        if ($entity->type === EntityType::THING) {
            return 0.0;
        }

        // Domain-specific types carry more machine meaning than the generic
        // top-level ones.
        return EntityType::isMedical($entity->type) || in_array($entity->type, [
            EntityType::PERSON,
            EntityType::ORGANIZATION,
            EntityType::PLACE,
            EntityType::PRODUCT,
        ], true) ? 10.0 : 6.0;
    }

    private function maxSalience(int $entityId): float
    {
        global $wpdb;

        $table = Tables::name(Tables::ENTITY_INDEX);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (float) $wpdb->get_var(
            $wpdb->prepare("SELECT MAX(salience) FROM {$table} WHERE entity_id = %d", $entityId)
        );
    }

    private function relationCount(int $entityId): int
    {
        global $wpdb;

        $table = Tables::name(Tables::RELATIONS);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE subject_id = %d OR object_id = %d",
                $entityId,
                $entityId
            )
        );
    }
}
