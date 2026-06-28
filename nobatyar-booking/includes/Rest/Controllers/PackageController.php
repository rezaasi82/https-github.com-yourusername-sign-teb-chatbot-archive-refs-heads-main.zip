<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\Packages\PackageEngine;
use Nobatyar\Packages\PackageRepository;

if (! defined('ABSPATH')) {
    exit;
}

class PackageController
{
    private const RATE_LIMIT_MAX_REQUESTS   = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    private PackageEngine $package_engine;
    private PackageRepository $package_repository;

    public function __construct(PackageEngine $package_engine, PackageRepository $package_repository)
    {
        $this->package_engine     = $package_engine;
        $this->package_repository = $package_repository;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/packages', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_packages'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('nobatyar/v1', '/packages/purchase', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'purchase_package'],
            'permission_callback' => [$this, 'check_public_permission'],
            'args'                => [
                'package_id'      => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'customer_name'   => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_phone'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_email'  => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

        register_rest_route('nobatyar/v1', '/packages/purchases/lookup', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'lookup_purchases'],
            'permission_callback' => '__return_true',
            'args'                => [
                'phone' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('nobatyar/v1', '/bookings/package-redeem', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'redeem_package'],
            'permission_callback' => [$this, 'check_public_permission'],
            'args'                => [
                'package_purchase_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'provider_id'         => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'booking_datetime'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_name'       => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_phone'      => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_email'      => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'notes'               => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            ],
        ]);
    }

    /**
     * @return true|\WP_Error
     */
    public function check_public_permission(\WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! $nonce || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('nobatyar_invalid_nonce', __('درخواست نامعتبر است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return true;
    }

    public function list_packages(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->package_repository->all(), 200);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function purchase_package(\WP_REST_Request $request)
    {
        $rate_limit_error = $this->check_rate_limit();

        if (is_wp_error($rate_limit_error)) {
            return $rate_limit_error;
        }

        $result = $this->package_engine->purchase([
            'package_id'     => $request->get_param('package_id'),
            'customer_name'  => $request->get_param('customer_name'),
            'customer_phone' => $request->get_param('customer_phone'),
            'customer_email' => $request->get_param('customer_email'),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['id' => $result], 201);
    }

    public function lookup_purchases(\WP_REST_Request $request): \WP_REST_Response
    {
        $purchases = $this->package_engine->find_active_purchases_by_phone((string) $request->get_param('phone'));

        return new \WP_REST_Response(['purchases' => $purchases], 200);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function redeem_package(\WP_REST_Request $request)
    {
        $rate_limit_error = $this->check_rate_limit();

        if (is_wp_error($rate_limit_error)) {
            return $rate_limit_error;
        }

        $result = $this->package_engine->redeem([
            'package_purchase_id' => $request->get_param('package_purchase_id'),
            'provider_id'          => $request->get_param('provider_id'),
            'booking_datetime'     => $request->get_param('booking_datetime'),
            'customer_name'        => $request->get_param('customer_name'),
            'customer_phone'       => $request->get_param('customer_phone'),
            'customer_email'       => $request->get_param('customer_email'),
            'notes'                => $request->get_param('notes'),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['id' => $result, 'status' => 'pending'], 201);
    }

    /**
     * Shares the exact same per-IP transient bucket as BookingController so
     * package purchase/redeem requests count against the same overall
     * request budget — a separate budget here would effectively double the
     * rate limit for one IP across the two controllers.
     *
     * @return null|\WP_Error
     */
    private function check_rate_limit()
    {
        $key   = 'nobatyar_throttle_' . md5($this->get_client_ip());
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return new \WP_Error(
                'nobatyar_rate_limited',
                __('تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.', 'nobatyar-booking'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW_SECONDS);

        return null;
    }

    private function get_client_ip(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    }
}
