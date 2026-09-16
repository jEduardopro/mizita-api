<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAddressPostalCode extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An address postal code cannot be empty.');
    }

    public static function malformed(): self
    {
        return new self('The postal code offered is not a run of digits an address may carry.');
    }

    public function errorCode(): string
    {
        return 'invalid_address_postal_code';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
