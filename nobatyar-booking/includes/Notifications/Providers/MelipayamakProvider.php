<?php

namespace Nobatyar\Notifications\Providers;

use Nobatyar\Notifications\SmsProviderInterface;
use Nobatyar\Notifications\SmsSendResult;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Melipayamak's REST SendSMS endpoint - JSON body, username/password
 * credentials rather than a single API key.
 */
class MelipayamakProvider implements SmsProviderInterface
{
    private string $username;
    private string $password;
    private string $sender;

    public function __construct(string $username, string $password, string $sender = '')
    {
        $this->username = $username;
        $this->password = $password;
        $this->sender   = $sender;
    }

    public function get_name(): string
    {
        return 'melipayamak';
    }

    public function send(string $to, string $message): SmsSendResult
    {
        $response = wp_remote_post('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => wp_json_encode([
                'username' => $this->username,
                'password' => $this->password,
                'to'       => $to,
                'from'     => $this->sender,
                'text'     => $message,
                'isflash'  => false,
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
