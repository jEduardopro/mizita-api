<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAddressStreet extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An address street cannot be empty.');
    }

    public static function tooLong(): self
    {
        return new self('The street offered is longer than an address street may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_address_street';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
