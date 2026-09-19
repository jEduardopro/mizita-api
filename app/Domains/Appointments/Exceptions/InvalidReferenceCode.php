<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidReferenceCode extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('A reservation code is eight characters from the booking alphabet.');
    }

    public function errorCode(): string
    {
        return 'invalid_reference_code';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
