<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use Medora\Authority\Support\Arr;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The page node, plus the `speakable` and `mentions` annotations that make it
 * useful to an answer engine.
 *
 * `mentions` is where the knowledge graph surfaces in public schema: the page
 * declares, with stable `@id`s, exactly which entities it covers. That is the
 * difference between a model inferring a topic from prose and being told.
 */
final class WebPageNode implements NodeInterface
{
    public function id(): string
    {
        return 'webpage';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return $context->post instanceof WP_Post;
    }

    public function build(SchemaContext $context): array
    {
        $post = $context->post;

        if (! $post instanceof WP_Post) {
            return [];
        }

        $type = $this->pageType($context, $post);

        $node = [
            '@type'            => $type,
            '@id'              => $context->id('webpage'),
            'url'              => (string) get_permalink($post),
            'name'             => get_the_title($post),
            'description'      => $this->description($post),
            'isPartOf'         => ['@id' => $context->siteId('website')],
            'datePublished'    => get_post_time('c', true, $post),
            'dateModified'     => get_post_modified_time('c', true, $post),
            'inLanguage'       => str_replace('_', '-', (string) get_locale()),
            'primaryImageOfPage' => $this->primaryImage($post),
            'mentions'         => $this->mentions($context),
            'about'            => $this->about($context),
            // Speakable marks the passages a voice assistant should read out.
            'speakable'        => [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => ['.medora-answer', 'h1', '.entry-summary'],
            ],
        ];

        if ($context->isMedical()) {
            $node = array_merge($node, $this->medicalAnnotations($post));
        }

        return [Arr::compact($node)];
    }

    private function pageType(SchemaContext $context, WP_Post $post): string
    {
        $override = (string) get_post_meta($post->ID, '_medora_schema_type', true);

        if ($override !== '') {
            return $override;
        }

        if ($context->isMedical()) {
            return 'MedicalWebPage';
        }

        return match ($post->post_type) {
            'post'    => 'Article',
            'page'    => is_front_page() ? 'WebPage' : 'WebPage',
            'product' => 'ItemPage',
            default   => 'WebPage',
        };
    }

    private function description(WP_Post $post): string
    {
        $summary = (string) get_post_meta($post->ID, '_medora_ai_summary', true);

        if ($summary !== '') {
            return $summary;
        }

        if ($post->post_excerpt !== '') {
            return Text::plain($post->post_excerpt);
        }

        return Text::truncate(Text::plain($post->post_content), 250);
    }

    /** @return array<string, mixed>|null */
    private function primaryImage(WP_Post $post): ?array
    {
        $thumbnailId = get_post_thumbnail_id($post);

        if ($thumbnailId === 0) {
            return null;
        }

        $image = wp_get_attachment_image_src($thumbnailId, 'full');

        if ($image === false) {
            return null;
        }

        return Arr::compact([
            '@type'   => 'ImageObject',
            'url'     => $image[0],
            'width'   => $image[1],
            'height'  => $image[2],
            // Alt text doubles as the image's machine-readable caption.
            'caption' => (string) get_post_meta($thumbnailId, '_wp_attachment_image_alt', true),
        ]);
    }

    /** @return list<array<string, string>> */
    private function mentions(SchemaContext $context): array
    {
        $mentions = [];

        foreach ($context->entities as $row) {
            // Passing mentions add noise; only entities the page genuinely
            // covers are worth declaring.
            if ($row['salience'] < 0.15) {
                continue;
            }

            $mentions[] = ['@id' => $row['entity']->schemaId()];
        }

        return array_slice($mentions, 0, 15);
    }

    /** @return array<string, string>|null */
    private function about(SchemaContext $context): ?array
    {
        $primary = $context->primaryEntity();

        return $primary === null ? null : ['@id' => $primary->schemaId()];
    }

    /** @return array<string, mixed> */
    private function medicalAnnotations(WP_Post $post): array
    {
        $reviewerId = (int) get_post_meta($post->ID, '_medora_reviewer_id', true);
        $reviewedAt = (string) get_post_meta($post->ID, '_medora_reviewed_at', true);

        $annotations = [
            // Declaring the audience is a documented requirement for Google's
            // health content treatment.
            'audience' => [
                '@type'            => 'Patient',
                'audienceType'     => (string) get_post_meta($post->ID, '_medora_audience', true) ?: 'Patient',
            ],
        ];

        if ($reviewerId > 0) {
            $reviewer = get_userdata($reviewerId);

            if ($reviewer !== false) {
                $annotations['reviewedBy'] = Arr::compact([
                    '@type'    => 'Person',
                    'name'     => $reviewer->display_name,
                    'jobTitle' => (string) get_user_meta($reviewerId, '_medora_job_title', true),
                    'url'      => (string) get_author_posts_url($reviewerId),
                ]);
            }
        }

        if ($reviewedAt !== '') {
            $annotations['lastReviewed'] = $reviewedAt;
        }

        return $annotations;
    }
}
