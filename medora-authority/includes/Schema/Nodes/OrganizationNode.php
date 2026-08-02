<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The publisher node — the anchor of the whole graph.
 *
 * Every other node references this `@id`, which is what lets an AI system tie
 * scattered pages back to one accountable organisation. On a medical site the
 * type is upgraded to `MedicalOrganization` (or `MedicalClinic`/`Hospital`),
 * because the generic `Organization` type carries none of the trust semantics
 * that YMYL evaluation depends on.
 */
final class OrganizationNode implements NodeInterface
{
    public function id(): string
    {
        return 'organization';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return true;
    }

    public function build(SchemaContext $context): array
    {
        $options = $context->options;
        $type    = $options->getString('organization_type', 'Organization');

        if ($context->isMedical() && $type === 'Organization') {
            $type = 'MedicalOrganization';
        }

        $node = [
            '@type'       => $type,
            '@id'         => $context->siteId('organization'),
            'name'        => $context->organizationName(),
            'url'         => $options->getString('organization_url') ?: home_url('/'),
            'description' => (string) get_bloginfo('description'),
            'logo'        => $this->logo($context),
            'sameAs'      => $this->sameAs($options->getArray('organization_profiles')),
            'address'     => $this->address($options->getArray('organization_address')),
            'telephone'   => $options->getString('organization_phone'),
            'email'       => $options->getString('organization_email'),
        ];

        if ($context->isMedical()) {
            $node['medicalSpecialty'] = $this->specialties($options->getArray('organization_specialties'));
            // A named, verifiable accreditation is the strongest institutional
            // trust signal available in Schema.org.
            $node['hasCredential'] = $options->getString('organization_accreditation') ?: null;
        }

        return [Arr::compact($node)];
    }

    /** @return array<string, mixed>|null */
    private function logo(SchemaContext $context): ?array
    {
        $logoId = $context->options->getInt('organization_logo_id');

        if ($logoId <= 0) {
            return null;
        }

        $image = wp_get_attachment_image_src($logoId, 'full');

        if ($image === false) {
            return null;
        }

        return [
            '@type'  => 'ImageObject',
            '@id'    => $context->siteId('logo'),
            'url'    => $image[0],
            'width'  => $image[1],
            'height' => $image[2],
        ];
    }

    /**
     * @param array<mixed> $profiles
     * @return list<string>
     */
    private function sameAs(array $profiles): array
    {
        $urls = [];

        foreach ($profiles as $profile) {
            $url = is_string($profile) ? trim($profile) : '';

            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array<mixed> $address
     * @return array<string, mixed>|null
     */
    private function address(array $address): ?array
    {
        if ($address === []) {
            return null;
        }

        return Arr::compact([
            '@type'           => 'PostalAddress',
            'streetAddress'   => (string) ($address['street'] ?? ''),
            'addressLocality' => (string) ($address['city'] ?? ''),
            'addressRegion'   => (string) ($address['region'] ?? ''),
            'postalCode'      => (string) ($address['postal_code'] ?? ''),
            'addressCountry'  => (string) ($address['country'] ?? ''),
        ]) ?: null;
    }

    /**
     * @param array<mixed> $specialties
     * @return list<string>
     */
    private function specialties(array $specialties): array
    {
        return array_values(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            $specialties
        )));
    }
}
