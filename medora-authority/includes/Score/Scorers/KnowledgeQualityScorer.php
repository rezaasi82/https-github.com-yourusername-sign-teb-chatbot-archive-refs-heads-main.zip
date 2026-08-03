<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Freshness, provenance and supporting material.
 *
 * These are the signals an evaluator uses to decide whether content is
 * *maintained* rather than merely published — which for anything time-sensitive
 * matters more than depth.
 */
final class KnowledgeQualityScorer implements ScorerInterface
{
    public function id(): string
    {
        return 'knowledge_quality';
    }

    public function label(): string
    {
        return __('Knowledge Quality', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.12;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $score      = 100.0;
        $deductions = [];

        $modified = (int) get_post_modified_time('U', true, $post);
        $ageDays  = (int) floor((time() - $modified) / DAY_IN_SECONDS);

        $html          = $post->post_content;
        $internalLinks = $this->countLinks($html, true);
        $externalLinks = $this->countLinks($html, false);
        $images        = (int) preg_match_all('#<img[\s>]#i', $html);

        $metrics = [
            'age_days'       => $ageDays,
            'internal_links' => $internalLinks,
            'external_links' => $externalLinks,
            'images'         => $images,
            'has_thumbnail'  => has_post_thumbnail($post),
            'word_count'     => Text::wordCount($html),
        ];

        // --- Freshness --------------------------------------------------------
        if ($ageDays > 730) {
            $score -= 25;
            $deductions[] = new Deduction(
                'very_stale',
                __('Not updated in over two years', 'medora-authority'),
                25,
                __('Review and re-date the page. Assistants strongly prefer recently maintained sources for anything that can change.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        } elseif ($ageDays > 365) {
            $score -= 12;
            $deductions[] = new Deduction(
                'stale',
                __('Not updated in over a year', 'medora-authority'),
                12,
                __('Schedule a content review.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        // --- Provenance --------------------------------------------------------
        if ($externalLinks === 0) {
            $score -= 20;
            $deductions[] = new Deduction(
                'no_sources',
                __('No outbound sources', 'medora-authority'),
                20,
                __('Cite where your claims come from. A page that asserts without sourcing is a weak candidate for citation itself.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        // --- Context within the site --------------------------------------------
        if ($internalLinks < 2) {
            $score -= 18;
            $deductions[] = new Deduction(
                'orphan_page',
                __('Barely linked to the rest of the site', 'medora-authority'),
                18,
                __('Link to related pages. Isolated pages read as one-off answers rather than part of a body of expertise.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM
            );
        }

        // --- Supporting media ----------------------------------------------------
        if ($images === 0 && ! has_post_thumbnail($post)) {
            $score -= 10;
            $deductions[] = new Deduction(
                'no_media',
                __('No images or diagrams', 'medora-authority'),
                10,
                __('Add a labelled image or diagram. Multimodal models increasingly index visual content alongside text.', 'medora-authority'),
                Deduction::SEVERITY_LOW
            );
        }

        // --- Alt text -------------------------------------------------------------
        $missingAlt = $this->countImagesWithoutAlt($html);

        if ($missingAlt > 0) {
            $penalty = min(10.0, $missingAlt * 2.5);
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'missing_alt',
                sprintf(
                    /* translators: %d: number of images. */
                    _n('%d image has no alt text', '%d images have no alt text', $missingAlt, 'medora-authority'),
                    $missingAlt
                ),
                $penalty,
                __('Alt text is the only machine-readable description of an image. Without it the image contributes nothing.', 'medora-authority'),
                Deduction::SEVERITY_LOW
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

    private function countLinks(string $html, bool $internal): int
    {
        if (preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\']#i', $html, $matches) === 0) {
            return 0;
        }

        $host  = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $count = 0;

        foreach ($matches[1] as $href) {
            if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $linkHost = (string) wp_parse_url($href, PHP_URL_HOST);
            $isSelf   = $linkHost === '' || $linkHost === $host;

            if ($isSelf === $internal) {
                $count++;
            }
        }

        return $count;
    }

    private function countImagesWithoutAlt(string $html): int
    {
        if (preg_match_all('#<img\s[^>]*>#i', $html, $matches) === 0) {
            return 0;
        }

        $missing = 0;

        foreach ($matches[0] as $tag) {
            if (preg_match('#alt=["\']\s*["\']#i', $tag) === 1 || stripos($tag, 'alt=') === false) {
                $missing++;
            }
        }

        return $missing;
    }
}
