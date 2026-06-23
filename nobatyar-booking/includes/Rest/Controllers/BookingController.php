<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\Booking\BookingEngine;
use Nobatyar\Booking\BookingRepository;

if (! defined('ABSPATH')) {
    exit;
}

class BookingController
{
    private const RATE_LIMIT_MAX_REQUESTS   = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    private BookingEngine $booking_engine;
    private BookingRepository $booking_repository;

    public function __construct(BookingEngine $booking_engine, BookingRepository $booking_repository)
    {
        $this->booking_engine     = $booking_engine;
        $this->booking_repository = $booking_repository;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/bookings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_bookings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_booking'],
                'permission_callback' => [$this, 'check_public_create_permission'],
                'args'                => [
                    'provider_id'      => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                    'service_id'       => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                    'booking_datetime' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'customer_name'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'customer_phone'   => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'customer_email'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'notes'            => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                ],
            ],
        ]);

        register_rest_route('nobatyar/v1', '/bookings/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_booking'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('nobatyar/v1', '/bookings/(?P<id>\d+)/status', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'status' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
    }

    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @return true|\WP_Error
     */
    public function check_public_create_permission(\WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! $nonce || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('nobatyar_invalid_nonce', __('درخواست نامعتبر است.', 'nobatyar-booking'), ['status' => 403]);
        }

        return true;
    }

    public function list_bookings(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = array_filter([
            'status'      => $request->get_param('status'),
            'provider_id' => $request->get_param('provider_id'),
            'date_from'   => $request->get_param('date_from'),
            'date_to'     => $request->get_param('date_to'),
        ]);

        return new \WP_REST_Response($this->booking_repository->all($filters), 200);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_booking(\WP_REST_Request $request)
    {
        $booking = $this->booking_repository->find((int) $request->get_param('id'));

        if (! $booking) {
            return new \WP_Error('nobatyar_booking_not_found', __('نوبت یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        return new \WP_REST_Response($booking, 200);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_booking(\WP_REST_Request $request)
    {
        $rate_limit_error = $this->check_rate_limit();

        if (is_wp_error($rate_limit_error)) {
            return $rate_limit_error;
        }

        $result = $this->booking_engine->book([
            'provider_id'      => $request->get_param('provider_id'),
            'service_id'       => $request->get_param('service_id'),
            'booking_datetime' => $request->get_param('booking_datetime'),
            'customer_name'    => $request->get_param('customer_name'),
            'customer_phone'   => $request->get_param('customer_phone'),
            'customer_email'   => $request->get_param('customer_email'),
            'notes'            => $request->get_param('notes'),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['id' => $result, 'status' => 'pending'], 201);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_status(\WP_REST_Request $request)
    {
        $result = $this->booking_engine->change_status(
            (int) $request->get_param('id'),
            $request->get_param('status')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['updated' => true], 200);
    }

    /**
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
