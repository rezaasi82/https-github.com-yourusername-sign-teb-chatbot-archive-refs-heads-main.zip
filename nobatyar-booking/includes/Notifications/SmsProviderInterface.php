<?php

namespace Nobatyar\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

interface SmsProviderInterface
{
    /**
     * Machine-readable identifier stored in nby_sms_logs.provider_name.
     */
    public function get_name(): string;

    public function send(string $to, string $message): SmsSendResult;
}
