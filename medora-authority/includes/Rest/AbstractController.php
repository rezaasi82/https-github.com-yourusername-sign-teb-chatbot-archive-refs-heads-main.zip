<?php

declare(strict_types=1);

namespace Medora\Authority\Rest;

use Medora\Authority\Core\Capabilities;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared plumbing for Medora's REST controllers.
 *
 * Two rules are enforced here rather than repeated per route:
 *
 * 1. Every mutating route requires an explicit Medora capability. Nothing
 *    falls back to `manage_options` or `is_user_logged_in()`.
 * 2. Public routes are read-only and return only data already visible on the
 *    front end — the knowledge graph and prompt packs describe published
 *    content, so exposing them is the point, but they must never leak a draft.
 */
abstract class AbstractController
{
    public const NAMESPACE = 'medora/v1';

    abstract public function registerRoutes(): void;

    /** Capability check for read routes in the dashboard. */
    public function canView(): bool|WP_Error
    {
        return current_user_can(Capabilities::VIEW_DASHBOARD)
            ? true
            : $this->forbidden(__('You do not have permission to view Medora data.', 'medora-authority'));
    }

    public function canManage(): bool|WP_Error
    {
        return current_user_can(Capabilities::MANAGE_SETTINGS)
            ? true
            : $this->forbidden(__('You do not have permission to change Medora settings.', 'medora-authority'));
    }

    public function canAnalyze(): bool|WP_Error
    {
        return current_user_can(Capabilities::RUN_ANALYSIS)
            ? true
            : $this->forbidden(__('You do not have permission to run analysis.', 'medora-authority'));
    }

    public function canEditEntities(): bool|WP_Error
    {
        return current_user_can(Capabilities::MANAGE_ENTITIES)
            ? true
            : $this->forbidden(__('You do not have permission to edit entities.', 'medora-authority'));
    }

    /**
     * Public read access. Kept as a named method rather than
     * `'__return_true'` so the intent is greppable and one filter can lock
     * every public endpoint down at once.
     */
    public function isPublic(): bool
    {
        /**
         * Filter whether Medora's public knowledge endpoints are open.
         *
         * @param bool $open
         */
        return (bool) apply_filters('medora_public_api_enabled', true);
    }

    protected function forbidden(string $message): WP_Error
    {
        return new WP_Error(
            'medora_forbidden',
            $message,
            ['status' => is_user_logged_in() ? 403 : 401]
        );
    }

    protected function notFound(string $message): WP_Error
    {
        return new WP_Error('medora_not_found', $message, ['status' => 404]);
    }

    protected function badRequest(string $message): WP_Error
    {
        return new WP_Error('medora_bad_request', $message, ['status' => 400]);
    }

    /** @param array<string, mixed>|list<mixed> $data */
    protected function ok(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    /**
     * Resolve a post id from the request, rejecting anything the caller is not
     * allowed to see.
     */
    protected function resolvePost(WP_REST_Request $request, string $param = 'id', bool $publicOnly = false): WP_Post|WP_Error
    {
        $postId = (int) $request->get_param($param);
        $post   = $postId > 0 ? get_post($postId) : null;

        if (! $post instanceof WP_Post) {
            return $this->notFound(__('Post not found.', 'medora-authority'));
        }

        if ($post->post_status !== 'publish') {
            // Unpublished content is only ever visible to someone who could
            // already read it in the editor.
            if ($publicOnly || ! current_user_can('read_post', $post->ID)) {
                return $this->notFound(__('Post not found.', 'medora-authority'));
            }
        }

        return $post;
    }

    /**
     * Standard pagination arguments.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function paginationArgs(int $defaultPerPage = 50, int $maxPerPage = 200): array
    {
        return [
            'page' => [
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => $defaultPerPage,
                'minimum'           => 1,
                'maximum'           => $maxPerPage,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /** Standard `days` window argument shared by the reporting endpoints. */
    protected function daysArg(int $default = 30): array
    {
        return [
            'days' => [
                'type'              => 'integer',
                'default'           => $default,
                'minimum'           => 1,
                'maximum'           => 365,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
