<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\License\LicenseManager;

if (! defined('ABSPATH')) {
    exit;
}

class LicenseController
{
    private LicenseManager $license_manager;

    public function __construct(LicenseManager $license_manager)
    {
        $this->license_manager = $license_manager;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/license/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'activate'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('nobatyar/v1', '/license/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function activate(\WP_REST_Request $request)
    {
        $result = $this->license_manager->activate((string) $request->get_param('license_key'));

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response($result, 200);
    }

    public function status(): \WP_REST_Response
    {
        $row = $this->license_manager->current_row();

        return new \WP_REST_Response([
            'status'     => $this->license_manager->current_status(),
            'tier'       => $row['tier'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
        ], 200);
    }
}
