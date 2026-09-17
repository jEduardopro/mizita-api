<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AddressPostalCodeCannotBeCleared extends DomainException implements DomainFailure
{
    public static function alreadySet(): self
    {
        return new self('An address postal code that already has a value cannot be emptied.');
    }

    public function errorCode(): string
    {
        return 'address_postal_code_cannot_be_cleared';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
