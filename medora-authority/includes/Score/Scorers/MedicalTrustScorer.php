<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Citation\CitationRepository;
use Medora\Authority\Core\Options;
use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * YMYL trust signals for health content.
 *
 * Only applies when the site is in medical mode. The checks mirror what health
 * quality frameworks (and, in practice, Google's health treatment) actually
 * look for: a named, credentialed author; independent clinical review with a
 * date; primary-literature citations; and an explicit statement that the page
 * is not individual medical advice.
 */
final class MedicalTrustScorer implements ScorerInterface
{
    public function __construct(
        private readonly Options $options,
        private readonly CitationRepository $citations,
    ) {
    }

    public function id(): string
    {
        return 'medical_trust';
    }

    public function label(): string
    {
        return __('Medical Trust', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.20;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return $this->options->getString('site_mode') === 'medical';
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $score      = 100.0;
        $deductions = [];

        $authorId    = (int) $post->post_author;
        $credentials = (string) get_user_meta($authorId, '_medora_credentials', true);
        $reviewerId  = (int) get_post_meta($post->ID, '_medora_reviewer_id', true);
        $reviewedAt  = (string) get_post_meta($post->ID, '_medora_reviewed_at', true);
        $citations   = $this->citations->countForObject('post', $post->ID);
        $plain       = Text::plain($post->post_content);

        $metrics = [
            'author_credentials' => $credentials,
            'has_reviewer'       => $reviewerId > 0,
            'reviewed_at'        => $reviewedAt,
            'citation_count'     => $citations,
        ];

        // --- Author credentials ---------------------------------------------
        if ($credentials === '') {
            $score -= 30;
            $deductions[] = new Deduction(
                'no_credentials',
                __('Author has no stated credentials', 'medora-authority'),
                30,
                __('Add the author\'s qualification and licence number to their profile. Anonymous health content is heavily discounted.', 'medora-authority'),
                Deduction::SEVERITY_CRITICAL,
                'author-profile'
            );
        }

        $orcid = (string) get_user_meta($authorId, '_medora_orcid', true);

        if ($orcid === '' && (string) get_user_meta($authorId, '_medora_scholar_url', true) === '') {
            $score -= 10;
            $deductions[] = new Deduction(
                'no_scholarly_profile',
                __('Author has no verifiable scholarly profile', 'medora-authority'),
                10,
                __('Add an ORCID or Google Scholar link so the author can be independently verified.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'author-profile'
            );
        }

        // --- Independent review -----------------------------------------------
        if ($reviewerId === 0) {
            $score -= 25;
            $deductions[] = new Deduction(
                'no_medical_review',
                __('No named medical reviewer', 'medora-authority'),
                25,
                __('Assign a clinical reviewer. Independent review is the strongest YMYL trust signal available.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        if ($reviewedAt === '') {
            $score -= 10;
            $deductions[] = new Deduction(
                'no_review_date',
                __('No review date', 'medora-authority'),
                10,
                __('Record when the page was last clinically reviewed, and surface it on the page.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        } else {
            $reviewedTimestamp = strtotime($reviewedAt);

            if ($reviewedTimestamp !== false && (time() - $reviewedTimestamp) > (2 * YEAR_IN_SECONDS)) {
                $score -= 15;
                $deductions[] = new Deduction(
                    'review_expired',
                    __('Clinical review is over two years old', 'medora-authority'),
                    15,
                    __('Re-review the page. Guidance changes, and a stale review date is worse than none for evaluator trust.', 'medora-authority'),
                    Deduction::SEVERITY_HIGH
                );
            }
        }

        // --- Evidence base ------------------------------------------------------
        if ($citations === 0) {
            $score -= 20;
            $deductions[] = new Deduction(
                'no_citations',
                __('No clinical citations', 'medora-authority'),
                20,
                __('Attach the primary literature behind your claims — DOI or PubMed identifiers, not just links to other blogs.', 'medora-authority'),
                Deduction::SEVERITY_HIGH,
                'citations'
            );
        }

        // --- Safety framing -------------------------------------------------------
        $hasDisclaimer = preg_match(
            '/(not (a substitute|intended as) (for )?(individual |professional )?medical advice|consult (your|a) (doctor|physician|healthcare)|جایگزین (توصیه|مشاوره) پزشک|با پزشک خود مشورت)/iu',
            $plain
        ) === 1;

        $metrics['has_disclaimer'] = $hasDisclaimer;

        if (! $hasDisclaimer) {
            $score -= 10;
            $deductions[] = new Deduction(
                'no_disclaimer',
                __('No medical disclaimer', 'medora-authority'),
                10,
                __('State plainly that the page is informational and does not replace individual medical advice.', 'medora-authority'),
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
