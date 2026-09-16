<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedCountry extends DomainException implements DomainFailure
{
    public static function withCode(string $code): self
    {
        return new self("[{$code}] is not a country this platform operates in.");
    }

    public function errorCode(): string
    {
        return 'unsupported_country';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
