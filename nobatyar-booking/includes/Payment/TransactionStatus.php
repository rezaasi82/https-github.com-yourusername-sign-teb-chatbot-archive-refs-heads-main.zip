<?php

namespace Nobatyar\Payment;

if (! defined('ABSPATH')) {
    exit;
}

class TransactionStatus
{
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED  = 'failed';
}
