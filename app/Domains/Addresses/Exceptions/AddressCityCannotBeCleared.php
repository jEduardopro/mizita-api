<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AddressCityCannotBeCleared extends DomainException implements DomainFailure
{
    public static function alreadySet(): self
    {
        return new self('An address city that already has a value cannot be emptied.');
    }

    public function errorCode(): string
    {
        return 'address_city_cannot_be_cleared';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
