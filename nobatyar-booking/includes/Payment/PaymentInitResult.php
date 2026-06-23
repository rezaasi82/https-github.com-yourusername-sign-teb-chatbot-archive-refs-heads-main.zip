<?php

namespace Nobatyar\Payment;

if (! defined('ABSPATH')) {
    exit;
}

final class PaymentInitResult
{
    private bool $success;
    private ?string $redirect_url;
    private ?string $authority;
    private ?string $error_message;

    private function __construct(bool $success, ?string $redirect_url, ?string $authority, ?string $error_message)
    {
        $this->success       = $success;
        $this->redirect_url  = $redirect_url;
        $this->authority     = $authority;
        $this->error_message = $error_message;
    }

    public static function success(string $redirect_url, ?string $authority = null): self
    {
        return new self(true, $redirect_url, $authority, null);
    }

    public static function failure(string $error_message): self
    {
        return new self(false, null, null, $error_message);
    }

    public function is_success(): bool
    {
        return $this->success;
    }

    public function redirect_url(): ?string
    {
        return $this->redirect_url;
    }

    /**
     * The gateway's transaction identifier (Zarinpal's Authority, IdPay's id,
     * NextPay's trans_id) - stored on nby_transactions.authority so the
     * callback can be matched back to this transaction later.
     */
    public function authority(): ?string
    {
        return $this->authority;
    }

    public function error_message(): ?string
    {
        return $this->error_message;
    }
}
