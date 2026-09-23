<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAddressStateName extends DomainException implements DomainFailure
{
    public static function tooLong(): self
    {
        return new self('The state name offered is longer than an address state name may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_address_state_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
