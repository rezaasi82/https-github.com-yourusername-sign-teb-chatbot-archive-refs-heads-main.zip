<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Analytics\TrendCalculator;
use Medora\Authority\Core\Container;
use Medora\Authority\Crawler\CrawlAnalytics;
use Medora\Authority\Eeat\AuthorProfile;
use Medora\Authority\Eeat\TrustScorer;
use Medora\Authority\Linking\LinkApplier;
use Medora\Authority\Linking\LinkSuggestionEngine;
use Medora\Authority\Medical\MedicalGraph;
use Medora\Authority\Medical\MedicalOntology;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Rest\AbstractController;
use Medora\Authority\Schema\SchemaGraph;
use Medora\Authority\Schema\SchemaModule;
use Medora\Authority\Schema\SchemaValidator;
use Medora\Authority\Security\AuditLogRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only reporting endpoints: analytics, crawler activity, schema
 * inspection, link suggestions, author trust and the audit log.
 */
final class InsightController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/analytics', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'analytics'],
            'permission_callback' => [$this, 'canView'],
            'args'                => $this->daysArg(),
        ]);

        register_rest_route(self::NAMESPACE, '/crawler-activity', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'crawlerActivity'],
            'permission_callback' => [$this, 'canView'],
            'args'                => $this->daysArg(),
        ]);

        register_rest_route(self::NAMESPACE, '/schema/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'schema'],
            'permission_callback' => [$this, 'canView'],
            'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE, '/links/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'links'],
                'permission_callback' => [$this, 'canView'],
                'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
            [
                // Applying a link edits published content, so this is gated on
                // `edit_post` inside the applier, not on a Medora capability
                // alone — someone who can analyse must not thereby be able to
                // edit.
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'applyLink'],
                'permission_callback' => [$this, 'canAnalyze'],
                'args'                => [
                    'id'         => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'target_id'  => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                    'anchor'     => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                    'occurrence' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint'],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'revertLinks'],
                'permission_callback' => [$this, 'canAnalyze'],
                'args'                => [
                    'id'        => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'target_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/authors/(?P<user_id>\d+)/trust', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'authorTrust'],
            'permission_callback' => [$this, 'canView'],
            'args'                => ['user_id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE, '/medical/profile', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'medicalProfile'],
            'permission_callback' => [$this, 'isPublic'],
            'args'                => [
                'condition' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Condition name, in any of the languages the ontology declares.',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/medical/coverage', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'medicalCoverage'],
            'permission_callback' => [$this, 'canView'],
        ]);

        register_rest_route(self::NAMESPACE, '/audit-log', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'auditLog'],
            'permission_callback' => static fn (): bool => current_user_can(\Medora\Authority\Core\Capabilities::VIEW_AUDIT_LOG),
            'args'                => $this->paginationArgs(50, 200),
        ]);
    }

    public function analytics(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('analytics')) {
            return $this->badRequest(__('The AI Analytics module is not active.', 'medora-authority'));
        }

        return $this->ok($this->container->get(TrendCalculator::class)->report((int) $request->get_param('days')));
    }

    public function crawlerActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('crawler')) {
            return $this->badRequest(__('The AI Crawler Manager module is not active.', 'medora-authority'));
        }

        return $this->ok($this->container->get(CrawlAnalytics::class)->report((int) $request->get_param('days')));
    }

    public function schema(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('schema')) {
            return $this->badRequest(__('The Schema Intelligence module is not active.', 'medora-authority'));
        }

        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $context  = $this->container->get(SchemaModule::class)->contextFor($this->container, $post);
        $document = $this->container->get(SchemaGraph::class)->build($context);

        return $this->ok([
            'post_id'    => $post->ID,
            'document'   => $document,
            'validation' => $this->container->get(SchemaValidator::class)->validate($document),
        ]);
    }

    public function links(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('linking')) {
            return $this->badRequest(__('The Internal Linking module is not active.', 'medora-authority'));
        }

        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $engine = $this->container->get(LinkSuggestionEngine::class);

        return $this->ok([
            'post_id'  => $post->ID,
            'outbound' => $engine->suggestFor($post),
            'inbound'  => $engine->inboundOpportunities($post),
            'applied'  => $this->container->get(LinkApplier::class)->countApplied($post),
        ]);
    }

    /**
     * Wrap an existing phrase in a suggested internal link.
     *
     * One link, chosen by a human, wrapping words already present. The applier
     * refuses anything else.
     */
    public function applyLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('linking')) {
            return $this->badRequest(__('The Internal Linking module is not active.', 'medora-authority'));
        }

        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $result = $this->container->get(LinkApplier::class)->apply(
            $post,
            (int) $request->get_param('target_id'),
            (string) $request->get_param('anchor'),
            (int) $request->get_param('occurrence')
        );

        if ($result instanceof WP_Error) {
            return $result;
        }

        return $this->ok($result);
    }

    public function revertLinks(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('linking')) {
            return $this->badRequest(__('The Internal Linking module is not active.', 'medora-authority'));
        }

        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $targetId = (int) $request->get_param('target_id');

        $result = $this->container->get(LinkApplier::class)->revert(
            $post,
            $targetId > 0 ? $targetId : null
        );

        if ($result instanceof WP_Error) {
            return $result;
        }

        return $this->ok($result);
    }

    public function authorTrust(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = get_userdata((int) $request->get_param('user_id'));

        if (! $user instanceof WP_User) {
            return $this->notFound(__('User not found.', 'medora-authority'));
        }

        $profile = AuthorProfile::forUser($user);

        return $this->ok(
            $profile->toArray() + ['trust' => $this->container->get(TrustScorer::class)->score($profile)]
        );
    }

    /**
     * The structured clinical picture for one condition.
     *
     * This is what an answer engine asking "what does this site know about X"
     * should receive: symptoms, treatments, diagnostics and risk factors as
     * data, rather than a page it has to parse.
     */
    public function medicalProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('medical')) {
            return $this->badRequest(__('The Medical Intelligence module is not active.', 'medora-authority'));
        }

        $profile = $this->container->get(MedicalGraph::class)
            ->profileFor((string) $request->get_param('condition'));

        if ($profile === null) {
            return $this->notFound(__('This site does not cover that condition.', 'medora-authority'));
        }

        return $this->ok($profile);
    }

    public function medicalCoverage(): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('medical')) {
            return $this->badRequest(__('The Medical Intelligence module is not active.', 'medora-authority'));
        }

        $graph    = $this->container->get(MedicalGraph::class);
        $ontology = $this->container->get(MedicalOntology::class);

        return $this->ok(
            $graph->coverage() + [
                'ontology_terms'     => $ontology->stats(),
                'unresolved_relations' => count($ontology->validatedRelations()['unresolved']),
            ]
        );
    }

    public function auditLog(WP_REST_Request $request): WP_REST_Response
    {
        $repository = $this->container->get(AuditLogRepository::class);
        $perPage    = (int) $request->get_param('per_page');
        $page       = (int) $request->get_param('page');

        return $this->ok([
            'items' => $repository->recent($perPage, ($page - 1) * $perPage),
            'total' => $repository->count(),
        ]);
    }
}
