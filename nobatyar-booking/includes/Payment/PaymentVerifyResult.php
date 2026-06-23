<?php

namespace Nobatyar\Payment;

if (! defined('ABSPATH')) {
    exit;
}

final class PaymentVerifyResult
{
    private bool $success;
    private ?string $ref_id;
    private ?string $raw_response;
    private ?string $error_message;

    private function __construct(bool $success, ?string $ref_id, ?string $raw_response, ?string $error_message)
    {
        $this->success       = $success;
        $this->ref_id         = $ref_id;
        $this->raw_response   = $raw_response;
        $this->error_message  = $error_message;
    }

    public static function success(?string $ref_id = null, ?string $raw_response = null): self
    {
        return new self(true, $ref_id, $raw_response, null);
    }

    public static function failure(string $error_message, ?string $raw_response = null): self
    {
        return new self(false, null, $raw_response, $error_message);
    }

    public function is_success(): bool
    {
        return $this->success;
    }

    public function ref_id(): ?string
    {
        return $this->ref_id;
    }

    public function raw_response(): ?string
    {
        return $this->raw_response;
    }

    public function error_message(): ?string
    {
        return $this->error_message;
    }
}
