<?php

declare(strict_types=1);

namespace Medora\Authority\Semantic;

use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Checks a page against the facets a complete treatment of its subject needs.
 *
 * The facet sets are intentionally *question-shaped*, because that is how
 * people query assistants. A page about a medical condition that never
 * mentions causes or treatment will lose to one that does, regardless of
 * keyword optimisation — the model simply has nothing to cite for those
 * questions.
 */
final class TopicCoverage
{
    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     * `covered` and `missing` hold facet slugs, not labels — see
     * {@see self::facetsFor()}. Call {@see self::label()} at the point of
     * display.
     *
     * @return array{score: float, covered: list<string>, missing: list<string>}
     */
    public function forPost(WP_Post $post, array $entities): array
    {
        $facets = $this->facetsFor($entities);

        if ($facets === []) {
            return ['score' => 60.0, 'covered' => [], 'missing' => []];
        }

        $haystack = Text::normalize($post->post_title . ' ' . Text::plain($post->post_content));
        $covered  = [];
        $missing  = [];

        foreach ($facets as $facet => $signals) {
            $hit = false;

            foreach ($signals as $signal) {
                if (str_contains($haystack, Text::normalize($signal))) {
                    $hit = true;
                    break;
                }
            }

            if ($hit) {
                $covered[] = $facet;
            } else {
                $missing[] = $facet;
            }
        }

        $score = (count($covered) / count($facets)) * 100;

        return [
            'score'   => $score,
            'covered' => $covered,
            'missing' => $missing,
        ];
    }

    /**
     * Facet set chosen from the page's primary entity type.
     *
     * Keyed by a stable slug, never by a label. A translated string used as an
     * array key makes a facet's identity locale-dependent: the same page yields
     * `Symptoms` in English and `علائم` in Persian, so anything that matches on
     * the key — the brief's heading map, a recommendation code, a stored
     * result — silently stops matching the moment the site is translated.
     *
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     * @return array<string, list<string>> facet slug => surface signals
     */
    private function facetsFor(array $entities): array
    {
        $type = $entities === [] ? '' : $entities[0]['entity']->type;

        $medical = $this->options->getString('site_mode') === 'medical';

        $facets = match (true) {
            $type === \Medora\Authority\Entity\EntityType::MEDICAL_CONDITION => [
                'definition'         => ['is a', 'defined as', 'چیست', 'تعریف', 'یعنی'],
                'symptoms'           => ['symptom', 'sign', 'علائم', 'نشانه', 'أعراض'],
                'causes'             => ['cause', 'risk factor', 'علت', 'عوامل خطر', 'أسباب'],
                'diagnosis'          => ['diagnos', 'test', 'تشخیص', 'آزمایش'],
                'treatment'          => ['treatment', 'therapy', 'درمان', 'دارو', 'علاج'],
                'prognosis'          => ['prognosis', 'outlook', 'recovery', 'پیش‌آگهی', 'بهبود'],
                'when_to_seek_care'  => ['see a doctor', 'emergency', 'مراجعه به پزشک', 'اورژانس'],
            ],
            $type === \Medora\Authority\Entity\EntityType::MEDICAL_PROCEDURE => [
                'what_it_is'   => ['is a procedure', 'involves', 'چیست', 'شامل'],
                'who_needs_it' => ['candidate', 'indicated', 'کاندید', 'نامزد'],
                'preparation'  => ['prepare', 'before the', 'آمادگی', 'قبل از'],
                'recovery'     => ['recovery', 'aftercare', 'دوره نقاهت', 'بعد از'],
                'risks'        => ['risk', 'complication', 'عوارض', 'خطر'],
                'cost'         => ['cost', 'price', 'هزینه', 'قیمت'],
            ],
            $type === \Medora\Authority\Entity\EntityType::PRODUCT => [
                'what_it_does' => ['is a', 'designed to', 'چیست'],
                'features'     => ['feature', 'includes', 'ویژگی', 'امکانات'],
                'pricing'      => ['price', 'cost', 'plan', 'قیمت', 'هزینه'],
                'comparison'   => ['versus', 'compared to', 'alternative', 'مقایسه'],
                'reviews'      => ['review', 'rating', 'نظرات', 'امتیاز'],
            ],
            default => [
                'definition'       => ['is a', 'refers to', 'means', 'چیست', 'یعنی'],
                'how_it_works'     => ['how', 'process', 'step', 'چگونه', 'مراحل'],
                'why_it_matters'   => ['because', 'important', 'benefit', 'چرا', 'مزیت'],
                'examples'         => ['for example', 'such as', 'مثال', 'برای نمونه'],
                'common_questions' => ['?', '؟'],
            ],
        };

        if ($medical && $type !== '' && \Medora\Authority\Entity\EntityType::isMedical($type)) {
            // YMYL content is judged partly on whether it says where its claims
            // come from and when it was last checked.
            $facets['evidence_and_sources'] = ['study', 'guideline', 'reference', 'مطالعه', 'منبع', 'راهنما'];
            $facets['review_date']          = ['reviewed', 'updated', 'بازبینی', 'به‌روزرسانی'];
        }

        /**
         * Filter the coverage facets applied to a page.
         *
         * Keys are stable slugs, not labels. Register a label for a custom
         * facet through `medora_topic_facet_label`.
         *
         * @param array<string, list<string>> $facets slug => surface signals
         * @param string                      $type   Primary entity type.
         */
        return (array) apply_filters('medora_topic_facets', $facets, $type);
    }

    /**
     * The display label for a facet slug.
     *
     * The only place facet text is translated. An unknown slug — one added
     * through the filter — falls back to its own humanised form rather than
     * rendering blank, so a third-party facet is usable without registering
     * anything.
     */
    public static function label(string $facet): string
    {
        $labels = [
            'definition'           => __('Definition', 'medora-authority'),
            'symptoms'             => __('Symptoms', 'medora-authority'),
            'causes'               => __('Causes', 'medora-authority'),
            'diagnosis'            => __('Diagnosis', 'medora-authority'),
            'treatment'            => __('Treatment', 'medora-authority'),
            'prognosis'            => __('Prognosis', 'medora-authority'),
            'when_to_seek_care'    => __('When to seek care', 'medora-authority'),
            'what_it_is'           => __('What it is', 'medora-authority'),
            'who_needs_it'         => __('Who needs it', 'medora-authority'),
            'preparation'          => __('Preparation', 'medora-authority'),
            'recovery'             => __('Recovery', 'medora-authority'),
            'risks'                => __('Risks', 'medora-authority'),
            'cost'                 => __('Cost', 'medora-authority'),
            'what_it_does'         => __('What it does', 'medora-authority'),
            'features'             => __('Features', 'medora-authority'),
            'pricing'              => __('Pricing', 'medora-authority'),
            'comparison'           => __('Comparison', 'medora-authority'),
            'reviews'              => __('Reviews', 'medora-authority'),
            'how_it_works'         => __('How it works', 'medora-authority'),
            'why_it_matters'       => __('Why it matters', 'medora-authority'),
            'examples'             => __('Examples', 'medora-authority'),
            'common_questions'     => __('Common questions', 'medora-authority'),
            'evidence_and_sources' => __('Evidence and sources', 'medora-authority'),
            'review_date'          => __('Review date', 'medora-authority'),
        ];

        /**
         * Filter the label shown for a facet slug.
         *
         * @param string $label
         * @param string $facet Slug.
         */
        return (string) apply_filters(
            'medora_topic_facet_label',
            $labels[$facet] ?? ucfirst(str_replace('_', ' ', $facet)),
            $facet
        );
    }

    /**
     * @param list<string> $facets
     * @return list<string>
     */
    public static function labels(array $facets): array
    {
        return array_map(static fn (string $facet): string => self::label($facet), $facets);
    }
}
