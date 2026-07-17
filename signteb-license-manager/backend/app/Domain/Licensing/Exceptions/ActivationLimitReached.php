<?php

namespace App\Domain\Licensing\Exceptions;

class ActivationLimitReached extends LicensingException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Activation limit of {$limit} site(s) reached.");
    }

    public function errorCode(): string
    {
        return 'activation_limit_reached';
    }
}
