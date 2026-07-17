<?php

namespace App\Domain\Licensing\Exceptions;

class TransferLimitReached extends LicensingException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Transfer limit of {$limit} per month reached.");
    }

    public function errorCode(): string
    {
        return 'transfer_limit_reached';
    }
}
