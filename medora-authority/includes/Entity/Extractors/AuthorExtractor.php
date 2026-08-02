<?php

declare(strict_types=1);

namespace Medora\Authority\Entity\Extractors;

use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Candidate;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Entity\ExtractorInterface;
use WP_Post;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns the author (and, in medical mode, the medical reviewer) into an entity.
 *
 * Authorship is the backbone of E-E-A-T. An answer engine that cannot resolve
 * *who* wrote a health claim has no basis for trusting it, so the author entity
 * carries every external identifier the site knows: ORCID, Google Scholar,
 * ResearchGate, the institutional profile.
 */
final class AuthorExtractor implements ExtractorInterface
{
    public function __construct(private readonly Options $options)
    {
    }

    public function id(): string
    {
        return 'author';
    }

    public function priority(): int
    {
        return 90;
    }

    public function extract(WP_Post $post, string $plainText): array
    {
        $candidates = [];
        $author     = get_userdata((int) $post->post_author);

        if ($author instanceof WP_User) {
            $candidates[] = new Candidate(
                entity: $this->toEntity($author),
                occurrences: 1,
                confidence: 0.98,
                source: $this->id(),
            );
        }

        // A separately credited medical reviewer is a distinct person and a
        // distinct trust signal; it must not be collapsed into the author.
        $reviewerId = (int) get_post_meta($post->ID, '_medora_reviewer_id', true);

        if ($reviewerId > 0 && $reviewerId !== (int) $post->post_author) {
            $reviewer = get_userdata($reviewerId);

            if ($reviewer instanceof WP_User) {
                $candidates[] = new Candidate(
                    entity: $this->toEntity($reviewer),
                    occurrences: 1,
                    confidence: 0.98,
                    source: $this->id(),
                );
            }
        }

        return $candidates;
    }

    private function toEntity(WP_User $user): Entity
    {
        $isMedical = $this->options->getString('site_mode') === 'medical'
            && (string) get_user_meta($user->ID, '_medora_credentials', true) !== '';

        return new Entity(
            name: $user->display_name !== '' ? $user->display_name : $user->user_login,
            type: $isMedical ? EntityType::PHYSICIAN : EntityType::PERSON,
            description: (string) get_user_meta($user->ID, 'description', true),
            objectType: 'user',
            objectId: $user->ID,
            permalink: (string) get_author_posts_url($user->ID),
            sameAs: $this->sameAsFor($user),
            meta: [
                'credentials' => (string) get_user_meta($user->ID, '_medora_credentials', true),
                'job_title'   => (string) get_user_meta($user->ID, '_medora_job_title', true),
                'affiliation' => (string) get_user_meta($user->ID, '_medora_affiliation', true),
            ],
        );
    }

    /** @return list<string> */
    private function sameAsFor(WP_User $user): array
    {
        $links = [];

        $profiles = [
            '_medora_orcid'        => 'https://orcid.org/',
            '_medora_scholar_url'  => '',
            '_medora_researchgate' => '',
            '_medora_linkedin'     => '',
            'user_url'             => '',
        ];

        foreach ($profiles as $metaKey => $prefix) {
            $value = $metaKey === 'user_url' ? $user->user_url : get_user_meta($user->ID, $metaKey, true);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $url = $prefix !== '' && ! str_starts_with($value, 'http') ? $prefix . $value : $value;

            if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }
}
