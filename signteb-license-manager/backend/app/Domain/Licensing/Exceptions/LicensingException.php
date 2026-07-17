<?php

namespace App\Domain\Licensing\Exceptions;

abstract class LicensingException extends \DomainException
{
    abstract public function errorCode(): string;
}
