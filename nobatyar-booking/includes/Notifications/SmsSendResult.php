<?php

namespace Nobatyar\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

final class SmsSendResult
{
    private bool $success;
    private ?string $response_payload;
    private ?string $error_message;

    private function __construct(bool $success, ?string $response_payload, ?string $error_message)
    {
        $this->success          = $success;
        $this->response_payload = $response_payload;
        $this->error_message    = $error_message;
    }

    public static function success(?string $response_payload = null): self
    {
        return new self(true, $response_payload, null);
    }

    public static function failure(string $error_message, ?string $response_payload = null): self
    {
        return new self(false, $response_payload, $error_message);
    }

    public function is_success(): bool
    {
        return $this->success;
    }

    public function response_payload(): ?string
    {
        return $this->response_payload;
    }

    public function error_message(): ?string
    {
        return $this->error_message;
    }
}
