<?php

namespace Nobatyar\Notifications\Providers;

use Nobatyar\Notifications\SmsProviderInterface;
use Nobatyar\Notifications\SmsSendResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Kavehnegar's REST send endpoint (https://kavenegar.com/rest.html) - the
 * API key travels in the URL path, receptor/message/sender as POST fields.
 */
class KavehnegarProvider implements SmsProviderInterface
{
    private string $api_key;
    private string $sender;

    public function __construct(string $api_key, string $sender = '')
    {
        $this->api_key = $api_key;
        $this->sender  = $sender;
    }

    public function get_name(): string
    {
        return 'kavehnegar';
    }

    public function send(string $to, string $message): SmsSendResult
    {
        $endpoint = sprintf('https://api.kavenegar.com/v1/%s/sms/send.json', rawurlencode($this->api_key));

        $body = [
            'receptor' => $to,
            'message'  => $message,
        ];

        if ($this->sender !== '') {
            $body['sender'] = $this->sender;
        }

        $response = wp_remote_post($endpoint, [
            'body'    => $body,
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
