<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Citation\CitationFormatter;
use Medora\Authority\Citation\CitationRepository;
use Medora\Authority\Citation\CrossRefResolver;
use Medora\Authority\Core\Container;
use Medora\Authority\Rest\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

final class CitationController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/citations/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'listForPost'],
                'permission_callback' => [$this, 'isPublic'],
                'args'                => [
                    'id'    => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'style' => ['type' => 'string', 'default' => 'apa', 'enum' => ['apa', 'vancouver', 'harvard']],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'attach'],
                'permission_callback' => [$this, 'canAnalyze'],
                'args'                => [
                    'id'         => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'identifier' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'A DOI, a doi.org URL, a PMID or a PubMed URL.',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/citations/entry/(?P<citation_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'canAnalyze'],
            'args'                => ['citation_id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);
    }

    public function listForPost(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->resolvePost($request, 'id', true);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $style     = (string) $request->get_param('style');
        $formatter = $this->container->get(CitationFormatter::class);

        $items = [];

        foreach ($this->container->get(CitationRepository::class)->forObject('post', $post->ID) as $citation) {
            $items[] = $citation->toArray() + ['formatted' => $formatter->format($citation, $style)];
        }

        return $this->ok([
            'post_id' => $post->ID,
            'style'   => $style,
            'styles'  => CitationFormatter::styles(),
            'items'   => $items,
        ]);
    }

    public function attach(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $identifier = (string) $request->get_param('identifier');
        $citation   = $this->container->get(CrossRefResolver::class)->resolve($identifier);

        if ($citation === null) {
            return $this->badRequest(sprintf(
                /* translators: %s: the identifier the user supplied. */
                __('Could not resolve "%s". Supply a DOI or a PubMed ID.', 'medora-authority'),
                $identifier
            ));
        }

        $id = $this->container->get(CitationRepository::class)->save('post', $post->ID, $citation);

        do_action('medora_citation_attached', $post->ID, $citation);

        return $this->ok(
            $citation->toArray() + [
                'id'        => $id,
                'formatted' => $this->container->get(CitationFormatter::class)->format($citation),
            ],
            201
        );
    }

    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('citation_id');

        if (! $this->container->get(CitationRepository::class)->delete($id)) {
            return $this->notFound(__('Citation not found.', 'medora-authority'));
        }

        return $this->ok(['deleted' => true, 'id' => $id]);
    }
}
