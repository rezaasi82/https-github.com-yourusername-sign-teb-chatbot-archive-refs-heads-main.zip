<?php
/**
 * Admin REST API for leads and exports.
 *
 * All routes require the manage_options capability. Read routes return lead
 * data; write routes trigger an export target through ExportManager.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Rest;

if (! defined('ABSPATH')) {
    exit;
}

class ExportController
{
    private const REST_NAMESPACE = 'signteb-web-chat/v1';

    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(self::REST_NAMESPACE, '/leads', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_leads'],
                'permission_callback' => [$this, 'can_manage'],
            ]);

            register_rest_route(self::REST_NAMESPACE, '/lead/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_lead'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => ['id' => ['validate_callback' => 'is_numeric']],
            ]);

            foreach (
                [
                    'pdf'          => 'export_pdf',
                    'webhook'      => 'export_webhook',
                    'google-sheet' => 'export_google_sheet',
                ] as $slug => $method
            ) {
                register_rest_route(self::REST_NAMESPACE, '/export/' . $slug, [
                    'methods'             => 'POST',
                    'callback'            => [$this, $method],
                    'permission_callback' => [$this, 'can_manage'],
                    'args'                => ['lead_id' => ['required' => true, 'type' => 'integer']],
                ]);
            }
        });
    }

    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public function get_leads(\WP_REST_Request $request): \WP_REST_Response
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();
        $repo     = new \SignTeb\WebChat\Database\ConversationRepository();
        $page     = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $filters  = ['leads_only' => (bool) $request->get_param('leads_only')];

        $items = array_map(static function ($c) {
            return [
                'lead_id'      => (int) $c->id,
                'patient_name' => (string) ($c->patient_name ?? ''),
                'phone'        => (string) ($c->patient_phone ?? ''),
                'lead_score'   => (string) ($c->lead_score ?? ''),
                'is_lead'      => (bool) $c->is_lead,
                'created_at'   => (string) $c->created_at,
                'pdf_url'      => (string) ($c->pdf_url ?? ''),
            ];
        }, $repo->paginate($page, $per_page, $filters));

        return new \WP_REST_Response([
            'ok'    => true,
            'total' => $repo->count($filters),
            'page'  => $page,
            'items' => $items,
        ]);
    }

    public function get_lead(\WP_REST_Request $request): \WP_REST_Response
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();
        $payload = (new \SignTeb\WebChat\Export\LeadPayload())->build((int) $request['id']);
        if ($payload === null) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'not_found'], 404);
        }
        return new \WP_REST_Response(['ok' => true, 'lead' => $payload]);
    }

    public function export_pdf(\WP_REST_Request $request): \WP_REST_Response
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();
        return new \WP_REST_Response((new \SignTeb\WebChat\Export\ExportManager())->export_pdf((int) $request->get_param('lead_id')));
    }

    public function export_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();
        return new \WP_REST_Response((new \SignTeb\WebChat\Export\ExportManager())->export_webhook((int) $request->get_param('lead_id'), 'manual'));
    }

    public function export_google_sheet(\WP_REST_Request $request): \WP_REST_Response
    {
        \SignTeb\WebChat\Core\JsonGuard::arm();
        return new \WP_REST_Response((new \SignTeb\WebChat\Export\ExportManager())->export_google_sheet((int) $request->get_param('lead_id')));
    }
}
