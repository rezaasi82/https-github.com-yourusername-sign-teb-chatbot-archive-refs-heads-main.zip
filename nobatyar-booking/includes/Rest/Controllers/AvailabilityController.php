<?php

namespace Nobatyar\Rest\Controllers;

use Nobatyar\Booking\SlotCalculator;
use Nobatyar\Service\Service;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

class AvailabilityController
{
    private SlotCalculator $slot_calculator;
    private ServiceRepository $service_repository;

    public function __construct(SlotCalculator $slot_calculator, ServiceRepository $service_repository)
    {
        $this->slot_calculator    = $slot_calculator;
        $this->service_repository = $service_repository;
    }

    public function register_routes(): void
    {
        register_rest_route('nobatyar/v1', '/availability', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_availability'],
            'permission_callback' => '__return_true',
            'args'                => [
                'provider_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'service_id'  => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'date'        => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => static fn ($value) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
                ],
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_availability(\WP_REST_Request $request)
    {
        $service_row = $this->service_repository->find((int) $request->get_param('service_id'));

        if (! $service_row || ! (int) $service_row['is_active']) {
            return new \WP_Error('nobatyar_invalid_service', __('خدمت یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $slots = $this->slot_calculator->get_available_slots(
            (int) $request->get_param('provider_id'),
            Service::from_row($service_row),
            $request->get_param('date')
        );

        return new \WP_REST_Response(['slots' => $slots], 200);
    }
}
