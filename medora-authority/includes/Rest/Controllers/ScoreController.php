<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Content\RecommendationEngine;
use Medora\Authority\Core\Container;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\Jobs\IndexPostJob;
use Medora\Authority\Rest\AbstractController;
use Medora\Authority\Score\AuthorityScoreCalculator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

final class ScoreController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/score/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => [$this, 'canView'],
            'args'                => [
                'id'      => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'refresh' => ['type' => 'boolean', 'default' => false],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/score/(?P<id>\d+)/recommendations', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'recommendations'],
            'permission_callback' => [$this, 'canView'],
            'args'                => ['id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE, '/score/(?P<id>\d+)/analyze', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'analyze'],
            'permission_callback' => [$this, 'canAnalyze'],
            'args'                => [
                'id'   => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'sync' => [
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Run inline instead of queueing. Slower, but returns the result immediately.',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/score', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'siteReport'],
            'permission_callback' => [$this, 'canView'],
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        return $this->ok(
            $this->container->get(AuthorityScoreCalculator::class)->analyze($post, (bool) $request->get_param('refresh'))
        );
    }

    public function recommendations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! $this->container->get(ModuleRegistry::class)->isBooted('content')) {
            return $this->badRequest(__('The AI Content Optimizer module is not active.', 'medora-authority'));
        }

        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        $engine = $this->container->get(RecommendationEngine::class);

        return $this->ok(
            $engine->forPost($post) + ['answer_first' => $engine->answerFirstGuidance($post)]
        );
    }

    public function analyze(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->resolvePost($request);

        if ($post instanceof WP_Error) {
            return $post;
        }

        // Synchronous analysis on a long page can take seconds; queueing is the
        // default so the editor UI stays responsive.
        if (! $request->get_param('sync')) {
            $this->container->get(JobQueue::class)->push(IndexPostJob::class, ['post_id' => $post->ID]);

            return $this->ok(['queued' => true, 'post_id' => $post->ID], 202);
        }

        return $this->ok(
            $this->container->get(AuthorityScoreCalculator::class)->analyze($post, true)
        );
    }

    public function siteReport(): WP_REST_Response
    {
        return $this->ok($this->container->get(AuthorityScoreCalculator::class)->siteReport());
    }
}
