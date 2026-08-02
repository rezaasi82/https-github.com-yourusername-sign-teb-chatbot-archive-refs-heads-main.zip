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

        foreach ($facets as $label => $signals) {
            $hit = false;

            foreach ($signals as $signal) {
                if (str_contains($haystack, Text::normalize($signal))) {
                    $hit = true;
                    break;
                }
            }

            if ($hit) {
                $covered[] = $label;
            } else {
                $missing[] = $label;
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
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $entities
     * @return array<string, list<string>> facet label => surface signals
     */
    private function facetsFor(array $entities): array
    {
        $type = $entities === [] ? '' : $entities[0]['entity']->type;

        $medical = $this->options->getString('site_mode') === 'medical';

        $facets = match (true) {
            $type === \Medora\Authority\Entity\EntityType::MEDICAL_CONDITION => [
                __('Definition', 'medora-authority')  => ['is a', 'defined as', 'چیست', 'تعریف', 'یعنی'],
                __('Symptoms', 'medora-authority')    => ['symptom', 'sign', 'علائم', 'نشانه', 'أعراض'],
                __('Causes', 'medora-authority')      => ['cause', 'risk factor', 'علت', 'عوامل خطر', 'أسباب'],
                __('Diagnosis', 'medora-authority')   => ['diagnos', 'test', 'تشخیص', 'آزمایش'],
                __('Treatment', 'medora-authority')   => ['treatment', 'therapy', 'درمان', 'دارو', 'علاج'],
                __('Prognosis', 'medora-authority')   => ['prognosis', 'outlook', 'recovery', 'پیش‌آگهی', 'بهبود'],
                __('When to seek care', 'medora-authority') => ['see a doctor', 'emergency', 'مراجعه به پزشک', 'اورژانس'],
            ],
            $type === \Medora\Authority\Entity\EntityType::MEDICAL_PROCEDURE => [
                __('What it is', 'medora-authority')     => ['is a procedure', 'involves', 'چیست', 'شامل'],
                __('Who needs it', 'medora-authority')   => ['candidate', 'indicated', 'کاندید', 'نامزد'],
                __('Preparation', 'medora-authority')    => ['prepare', 'before the', 'آمادگی', 'قبل از'],
                __('Recovery', 'medora-authority')       => ['recovery', 'aftercare', 'دوره نقاهت', 'بعد از'],
                __('Risks', 'medora-authority')          => ['risk', 'complication', 'عوارض', 'خطر'],
                __('Cost', 'medora-authority')           => ['cost', 'price', 'هزینه', 'قیمت'],
            ],
            $type === \Medora\Authority\Entity\EntityType::PRODUCT => [
                __('What it does', 'medora-authority') => ['is a', 'designed to', 'چیست'],
                __('Features', 'medora-authority')     => ['feature', 'includes', 'ویژگی', 'امکانات'],
                __('Pricing', 'medora-authority')      => ['price', 'cost', 'plan', 'قیمت', 'هزینه'],
                __('Comparison', 'medora-authority')   => ['versus', 'compared to', 'alternative', 'مقایسه'],
                __('Reviews', 'medora-authority')      => ['review', 'rating', 'نظرات', 'امتیاز'],
            ],
            default => [
                __('Definition', 'medora-authority')   => ['is a', 'refers to', 'means', 'چیست', 'یعنی'],
                __('How it works', 'medora-authority') => ['how', 'process', 'step', 'چگونه', 'مراحل'],
                __('Why it matters', 'medora-authority') => ['because', 'important', 'benefit', 'چرا', 'مزیت'],
                __('Examples', 'medora-authority')     => ['for example', 'such as', 'مثال', 'برای نمونه'],
                __('Common questions', 'medora-authority') => ['?', '؟'],
            ],
        };

        if ($medical && $type !== '' && \Medora\Authority\Entity\EntityType::isMedical($type)) {
            // YMYL content is judged partly on whether it says where its claims
            // come from and when it was last checked.
            $facets[__('Evidence and sources', 'medora-authority')] = ['study', 'guideline', 'reference', 'مطالعه', 'منبع', 'راهنما'];
            $facets[__('Review date', 'medora-authority')]          = ['reviewed', 'updated', 'بازبینی', 'به‌روزرسانی'];
        }

        /**
         * Filter the coverage facets applied to a page.
         *
         * @param array<string, list<string>> $facets
         * @param string                      $type Primary entity type.
         */
        return (array) apply_filters('medora_topic_facets', $facets, $type);
    }
}
