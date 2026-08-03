<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use Medora\Authority\Support\Arr;
use Medora\Authority\Support\Text;
use WP_Post;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Article node with a fully described author.
 *
 * The author is emitted inline with credentials, affiliation and `sameAs`
 * identifiers rather than as a bare name string. An unresolvable author is the
 * most common reason otherwise-good health content fails E-E-A-T evaluation.
 */
final class ArticleNode implements NodeInterface
{
    public function id(): string
    {
        return 'article';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return $context->post instanceof WP_Post
            && $context->isSingular
            && $context->post->post_type !== 'page';
    }

    public function build(SchemaContext $context): array
    {
        $post = $context->post;

        if (! $post instanceof WP_Post) {
            return [];
        }

        $type = $context->isMedical() ? 'MedicalScholarlyArticle' : 'Article';
        $type = (string) apply_filters('medora_article_schema_type', $type, $post);

        $node = [
            '@type'            => $type,
            '@id'              => $context->id('article'),
            'headline'         => Text::truncate(get_the_title($post), 110, ''),
            'description'      => (string) get_post_meta($post->ID, '_medora_ai_summary', true),
            'mainEntityOfPage' => ['@id' => $context->id('webpage')],
            'datePublished'    => get_post_time('c', true, $post),
            'dateModified'     => get_post_modified_time('c', true, $post),
            'author'           => $this->author($post),
            'publisher'        => ['@id' => $context->siteId('organization')],
            'wordCount'        => Text::wordCount($post->post_content),
            'articleSection'   => $this->sections($post),
            'keywords'         => $this->keywords($context),
            'citation'         => $this->citations($post),
        ];

        return [Arr::compact($node)];
    }

    /** @return array<string, mixed>|null */
    private function author(WP_Post $post): ?array
    {
        $user = get_userdata((int) $post->post_author);

        if (! $user instanceof WP_User) {
            return null;
        }

        $credentials = (string) get_user_meta($user->ID, '_medora_credentials', true);

        return Arr::compact([
            '@type'       => $credentials !== '' ? 'Person' : 'Person',
            '@id'         => home_url('/#/entity/' . \Medora\Authority\Entity\Entity::uidFor(
                $credentials !== '' ? \Medora\Authority\Entity\EntityType::PHYSICIAN : \Medora\Authority\Entity\EntityType::PERSON,
                $user->display_name
            )),
            'name'        => $user->display_name,
            'url'         => (string) get_author_posts_url($user->ID),
            'description' => (string) get_user_meta($user->ID, 'description', true),
            'jobTitle'    => (string) get_user_meta($user->ID, '_medora_job_title', true),
            'honorificSuffix' => $credentials,
            'affiliation' => $this->affiliation($user),
            'sameAs'      => $this->sameAs($user),
        ]);
    }

    /** @return array<string, string>|null */
    private function affiliation(WP_User $user): ?array
    {
        $affiliation = (string) get_user_meta($user->ID, '_medora_affiliation', true);

        return $affiliation === '' ? null : ['@type' => 'Organization', 'name' => $affiliation];
    }

    /** @return list<string> */
    private function sameAs(WP_User $user): array
    {
        $links = [];

        $orcid = (string) get_user_meta($user->ID, '_medora_orcid', true);

        if ($orcid !== '') {
            $links[] = str_starts_with($orcid, 'http') ? $orcid : 'https://orcid.org/' . $orcid;
        }

        foreach (['_medora_scholar_url', '_medora_researchgate', '_medora_linkedin'] as $key) {
            $value = (string) get_user_meta($user->ID, $key, true);

            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                $links[] = $value;
            }
        }

        if ($user->user_url !== '') {
            $links[] = $user->user_url;
        }

        return array_values(array_unique($links));
    }

    /** @return list<string> */
    private function sections(WP_Post $post): array
    {
        $terms = get_the_terms($post, 'category');

        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_map(static fn ($term): string => (string) $term->name, $terms));
    }

    /** @return list<string> */
    private function keywords(SchemaContext $context): array
    {
        $keywords = [];

        foreach ($context->entities as $row) {
            if ($row['salience'] >= 0.2) {
                $keywords[] = $row['entity']->name;
            }
        }

        return array_slice(array_values(array_unique($keywords)), 0, 12);
    }

    /**
     * Citations stored by the Citation Engine, emitted so an answer engine can
     * follow the evidence chain rather than take the claim on faith.
     *
     * @return list<array<string, mixed>>
     */
    private function citations(WP_Post $post): array
    {
        /**
         * Filter the citation nodes attached to an article. The Citation module
         * populates this; returning an empty array is valid.
         *
         * @param list<array<string, mixed>> $citations
         * @param WP_Post                    $post
         */
        return (array) apply_filters('medora_schema_citations', [], $post);
    }
}
