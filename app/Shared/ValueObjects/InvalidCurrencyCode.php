<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Contracts\DomainFailure;
use DomainException;

final class InvalidCurrencyCode extends DomainException implements DomainFailure
{
    public static function forValue(string $value): self
    {
        return new self("[{$value}] is not a three letter ISO 4217 currency code.");
    }

    public function errorCode(): string
    {
        return 'invalid_business_currency';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
