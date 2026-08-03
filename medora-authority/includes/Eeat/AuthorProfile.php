<?php

declare(strict_types=1);

namespace Medora\Authority\Eeat;

use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The E-E-A-T fields Medora adds to a WordPress user.
 *
 * Stored as individual user meta rather than one serialised blob so other
 * plugins, theme templates and REST consumers can read a single field without
 * unserialising, and so an existing value from another plugin can be mapped in.
 */
final class AuthorProfile
{
    public const FIELDS = [
        '_medora_credentials'  => 'Credentials',
        '_medora_job_title'    => 'Job title',
        '_medora_affiliation'  => 'Affiliation',
        '_medora_license_no'   => 'Professional licence number',
        '_medora_orcid'        => 'ORCID',
        '_medora_scholar_url'  => 'Google Scholar URL',
        '_medora_researchgate' => 'ResearchGate URL',
        '_medora_linkedin'     => 'LinkedIn URL',
        '_medora_years_active' => 'Years of practice',
        '_medora_awards'       => 'Awards',
        '_medora_education'    => 'Education',
    ];

    /** @param array<string, string> $fields */
    public function __construct(
        public readonly int $userId,
        public readonly string $displayName,
        public readonly array $fields = [],
        public readonly string $bio = '',
        public readonly int $publishedPosts = 0,
    ) {
    }

    public static function forUser(WP_User $user): self
    {
        $fields = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $value = get_user_meta($user->ID, $key, true);

            $fields[$key] = is_string($value) ? $value : '';
        }

        return new self(
            userId: $user->ID,
            displayName: $user->display_name,
            fields: $fields,
            bio: (string) get_user_meta($user->ID, 'description', true),
            publishedPosts: (int) count_user_posts($user->ID, 'post', true),
        );
    }

    public function get(string $key): string
    {
        return $this->fields[$key] ?? '';
    }

    public function has(string $key): bool
    {
        return trim($this->get($key)) !== '';
    }

    /** External identifiers that let a third party verify this person exists. */
    public function verifiableProfiles(): array
    {
        $profiles = [];

        foreach (['_medora_orcid', '_medora_scholar_url', '_medora_researchgate', '_medora_linkedin'] as $key) {
            $value = trim($this->get($key));

            if ($value === '') {
                continue;
            }

            $profiles[$key] = $key === '_medora_orcid' && ! str_starts_with($value, 'http')
                ? 'https://orcid.org/' . $value
                : $value;
        }

        return $profiles;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id'         => $this->userId,
            'display_name'    => $this->displayName,
            'bio'             => $this->bio,
            'published_posts' => $this->publishedPosts,
            'fields'          => $this->fields,
            'profiles'        => $this->verifiableProfiles(),
        ];
    }
}
