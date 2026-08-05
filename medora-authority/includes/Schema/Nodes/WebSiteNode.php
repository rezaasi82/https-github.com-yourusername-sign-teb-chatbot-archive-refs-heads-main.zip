<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;
use Medora\Authority\Support\Arr;

if (! defined('ABSPATH')) {
    exit;
}

final class WebSiteNode implements NodeInterface
{
    public function id(): string
    {
        return 'website';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return true;
    }

    public function build(SchemaContext $context): array
    {
        $node = [
            '@type'           => 'WebSite',
            '@id'             => $context->siteId('website'),
            'url'             => home_url('/'),
            'name'            => $context->organizationName(),
            'description'     => (string) get_bloginfo('description'),
            'publisher'       => ['@id' => $context->siteId('organization')],
            'inLanguage'      => $context->language(),
            // Declaring the search endpoint lets assistants query the site
            // directly instead of guessing URLs.
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return [Arr::compact($node)];
    }

}
