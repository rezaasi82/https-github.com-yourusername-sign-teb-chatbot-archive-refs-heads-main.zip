<?php

namespace Nobatyar\Notifications\WhatsApp;

use Nobatyar\Notifications\SmsProviderInterface;
use Nobatyar\Notifications\SmsSendResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Marked "experimental" for the MVP per nobatyar-strategy.md section C -
 * the Meta WhatsApp Cloud API, only activated when explicitly enabled and
 * configured in `nobatyar_sms_settings` (see SmsProviderFactory).
 */
class WhatsAppProvider implements SmsProviderInterface
{
    private string $phone_number_id;
    private string $access_token;

    public function __construct(string $phone_number_id, string $access_token)
    {
        $this->phone_number_id = $phone_number_id;
        $this->access_token    = $access_token;
    }

    public function get_name(): string
    {
        return 'whatsapp';
    }

    public function send(string $to, string $message): SmsSendResult
    {
        $endpoint = sprintf('https://graph.facebook.com/v19.0/%s/messages', rawurlencode($this->phone_number_id));

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return SmsSendResult::failure($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return SmsSendResult::failure("HTTP {$code}", $raw);
        }

        return SmsSendResult::success($raw);
    }
}
