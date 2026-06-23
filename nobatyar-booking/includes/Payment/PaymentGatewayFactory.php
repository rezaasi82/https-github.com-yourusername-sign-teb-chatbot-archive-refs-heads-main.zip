<?php

namespace Nobatyar\Payment;

use Nobatyar\Payment\Gateways\IdPayGateway;
use Nobatyar\Payment\Gateways\NextPayGateway;
use Nobatyar\Payment\Gateways\ZarinpalGateway;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds payment gateways from the `nobatyar_payment_settings` option - only
 * one gateway is "active" (selected in settings) at a time, but the callback
 * route needs every known gateway available to figure out which one a given
 * inbound callback came from (see PaymentEngine::verify_from_callback()).
 */
class PaymentGatewayFactory
{
    public static function active_gateway(): ?PaymentGatewayInterface
    {
        $settings = self::settings();

        switch ($settings['provider']) {
            case 'zarinpal':
                if (empty($settings['zarinpal']['merchant_id'])) {
                    return null;
                }

                return new ZarinpalGateway($settings['zarinpal']['merchant_id'], ! empty($settings['zarinpal']['sandbox']));

            case 'idpay':
                if (empty($settings['idpay']['api_key'])) {
                    return null;
                }

                return new IdPayGateway($settings['idpay']['api_key'], ! empty($settings['idpay']['sandbox']));

            case 'nextpay':
                if (empty($settings['nextpay']['api_key'])) {
                    return null;
                }

                return new NextPayGateway($settings['nextpay']['api_key']);

            default:
                return null;
        }
    }

    /**
     * @return array<int,PaymentGatewayInterface>
     */
    public static function all_known_gateways(): array
    {
        $settings = self::settings();

        return [
            new ZarinpalGateway($settings['zarinpal']['merchant_id'] ?? '', ! empty($settings['zarinpal']['sandbox'])),
            new IdPayGateway($settings['idpay']['api_key'] ?? '', ! empty($settings['idpay']['sandbox'])),
            new NextPayGateway($settings['nextpay']['api_key'] ?? ''),
        ];
    }

    /**
     * @return array{provider:string,zarinpal:array{merchant_id:string,sandbox:bool},idpay:array{api_key:string,sandbox:bool},nextpay:array{api_key:string}}
     */
    public static function settings(): array
    {
        $defaults = [
            'provider' => '',
            'zarinpal' => ['merchant_id' => '', 'sandbox' => false],
            'idpay'    => ['api_key' => '', 'sandbox' => false],
            'nextpay'  => ['api_key' => ''],
        ];

        $stored = get_option('nobatyar_payment_settings', []);

        return apply_filters('nobatyar_payment_settings', array_replace_recursive($defaults, $stored));
    }
}
