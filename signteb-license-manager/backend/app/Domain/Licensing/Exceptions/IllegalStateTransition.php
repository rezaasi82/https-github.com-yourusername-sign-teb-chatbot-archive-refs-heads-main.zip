<?php

namespace App\Domain\Licensing\Exceptions;

use App\Domain\Licensing\LicenseStatus;

class IllegalStateTransition extends LicensingException
{
    public function __construct(LicenseStatus $from, LicenseStatus $to)
    {
        parent::__construct("Cannot transition license from {$from->value} to {$to->value}.");
    }

    public function errorCode(): string
    {
        return 'illegal_state_transition';
    }
}
