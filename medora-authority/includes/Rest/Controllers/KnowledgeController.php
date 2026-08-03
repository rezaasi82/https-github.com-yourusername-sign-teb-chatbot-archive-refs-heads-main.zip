<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Core\Container;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityAuthorityScorer;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Graph\GraphExporter;
use Medora\Authority\Graph\RelationRepository;
use Medora\Authority\Graph\RelationType;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Prompt\PromptContextBuilder;
use Medora\Authority\Rest\AbstractController;
use Medora\Authority\Vector\VectorIndex;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The public Knowledge API: entities, graph, prompt packs and semantic search.
 *
 * These endpoints are readable without authentication by design — they are how
 * an AI system consumes the site's structured knowledge, and locking them
 * behind a key would defeat the entire product. They expose only what is
 * already published; the write side is separately capability-gated.
 */
final class KnowledgeController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/entities', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'listEntities'],
                'permission_callback' => [$this, 'isPublic'],
                'args'                => $this->paginationArgs() + [
                    'type'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'search'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'min_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                    'orderby'   => [
                        'type'    => 'string',
                        'default' => 'authority',
                        'enum'    => ['authority', 'name', 'occurrences', 'recent'],
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/entities/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getEntity'],
                'permission_callback' => [$this, 'isPublic'],
                'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateEntity'],
                'permission_callback' => [$this, 'canEditEntities'],
                'args'                => [
                    'id'          => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'name'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'type'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'description' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'same_as'     => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteEntity'],
                'permission_callback' => [$this, 'canEditEntities'],
                'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/graph', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'graph'],
            'permission_callback' => [$this, 'isPublic'],
            'args'                => [
                'format' => ['type' => 'string', 'default' => 'jsonld', 'enum' => ['jsonld', 'nodes']],
                'limit'  => ['type' => 'integer', 'default' => 300, 'minimum' => 1, 'maximum' => 2000, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/prompt/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'promptPack'],
            'permission_callback' => [$this, 'isPublic'],
            'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE, '/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'isPublic'],
            'args'                => [
                'q'     => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    public function listEntities(WP_REST_Request $request): WP_REST_Response
    {
        $repository = $this->container->get(EntityRepository::class);

        $result = $repository->query([
            'type'      => (string) $request->get_param('type'),
            'search'    => (string) $request->get_param('search'),
            'min_score' => $request->get_param('min_score'),
            'orderby'   => (string) $request->get_param('orderby'),
            'per_page'  => (int) $request->get_param('per_page'),
            'page'      => (int) $request->get_param('page'),
        ]);

        $response = $this->ok([
            'items' => array_map(static fn (Entity $e): array => $e->toArray(), $result['items']),
            'total' => $result['total'],
            'page'  => (int) $request->get_param('page'),
        ]);

        $response->header('X-WP-Total', (string) $result['total']);

        return $response;
    }

    public function getEntity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $entity = $this->container->get(EntityRepository::class)->find((int) $request->get_param('id'));

        if ($entity === null) {
            return $this->notFound(__('Entity not found.', 'medora-authority'));
        }

        $payload = $entity->toArray();

        if ($this->container->get(ModuleRegistry::class)->isBooted('graph')) {
            $payload['relations'] = $this->relations($entity->id);
        }

        $payload['authority'] = $this->container->get(EntityAuthorityScorer::class)->explain($entity);

        return $this->ok($payload);
    }

    public function updateEntity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $repository = $this->container->get(EntityRepository::class);
        $existing   = $repository->find((int) $request->get_param('id'));

        if ($existing === null) {
            return $this->notFound(__('Entity not found.', 'medora-authority'));
        }

        $type = (string) ($request->get_param('type') ?? $existing->type);

        if (! EntityType::isValid($type)) {
            return $this->badRequest(__('Unknown entity type.', 'medora-authority'));
        }

        // Only accept well-formed absolute URLs as sameAs: a malformed
        // identifier in published JSON-LD is worse than no identifier.
        $sameAs = $existing->sameAs;

        if ($request->has_param('same_as')) {
            $sameAs = array_values(array_filter(
                array_map(static fn ($url): string => esc_url_raw((string) $url), (array) $request->get_param('same_as')),
                static fn (string $url): bool => $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false
            ));
        }

        $updated = new Entity(
            name: (string) ($request->get_param('name') ?? $existing->name),
            type: $type,
            id: $existing->id,
            uid: $existing->uid,
            description: (string) ($request->get_param('description') ?? $existing->description),
            objectType: $existing->objectType,
            objectId: $existing->objectId,
            permalink: $existing->permalink,
            sameAs: $sameAs,
            meta: $existing->meta,
            authorityScore: $existing->authorityScore,
            confidence: 1.0, // Hand-curated: the highest confidence there is.
            occurrences: $existing->occurrences,
            isPrimary: $existing->isPrimary,
        );

        $stored = $repository->upsert($updated);

        $repository->updateAuthorityScore(
            $stored->id,
            $this->container->get(EntityAuthorityScorer::class)->score($stored)
        );

        do_action('medora_entity_updated', $stored);

        return $this->ok(($repository->find($stored->id) ?? $stored)->toArray());
    }

    public function deleteEntity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        if (! $this->container->get(EntityRepository::class)->delete($id)) {
            return $this->notFound(__('Entity not found.', 'medora-authority'));
        }

        do_action('medora_entity_deleted', $id);

        return $this->ok(['deleted' => true, 'id' => $id]);
    }

    public function graph(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('graph')) {
            return $this->badRequest(__('The Knowledge Graph module is not active.', 'medora-authority'));
        }

        $exporter = $this->container->get(GraphExporter::class);
        $limit    = (int) $request->get_param('limit');

        if ((string) $request->get_param('format') === 'nodes') {
            return $this->ok($exporter->toVisualisation($limit));
        }

        $response = $this->ok($exporter->toJsonLd($limit));
        // Advertise the JSON-LD content type so a crawler treats the payload as
        // structured data rather than as an arbitrary API response.
        $response->header('Content-Type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    public function promptPack(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('prompt')) {
            return $this->badRequest(__('The AI Prompt Engine module is not active.', 'medora-authority'));
        }

        // Public route: unpublished posts must stay invisible.
        $post = $this->resolvePost($request, 'id', true);

        if ($post instanceof WP_Error) {
            return $post;
        }

        return $this->ok($this->container->get(PromptContextBuilder::class)->build($post));
    }

    public function search(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('vector')) {
            return $this->badRequest(__('The Vector Engine module is not active.', 'medora-authority'));
        }

        $results = $this->container->get(VectorIndex::class)->search(
            (string) $request->get_param('q'),
            (int) $request->get_param('limit'),
            ['object_type' => 'post']
        );

        return $this->ok([
            'query'   => (string) $request->get_param('q'),
            'results' => $results,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function relations(int $entityId): array
    {
        $repository = $this->container->get(EntityRepository::class);
        $relations  = [];

        foreach ($this->container->get(RelationRepository::class)->forEntity($entityId, 40) as $edge) {
            $other = $repository->find($edge['other_id']);

            if ($other === null) {
                continue;
            }

            $relations[] = [
                'predicate'       => $edge['predicate'],
                'predicate_label' => RelationType::label($edge['predicate']),
                'direction'       => $edge['direction'],
                'weight'          => $edge['weight'],
                'entity'          => $other->toArray(),
            ];
        }

        return $relations;
    }
}
