<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAddressCity extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An address city cannot be empty.');
    }

    public static function tooLong(): self
    {
        return new self('The city offered is longer than an address city may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_address_city';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
