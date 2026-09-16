<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessCoordinates extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a decimal coordinate.");
    }

    public static function incomplete(): self
    {
        return new self('A latitude and a longitude are given together or not at all.');
    }

    public static function latitudeOutOfRange(string $value): self
    {
        return new self("[{$value}] is outside the -90 to 90 range a latitude lives in.");
    }

    public static function longitudeOutOfRange(string $value): self
    {
        return new self("[{$value}] is outside the -180 to 180 range a longitude lives in.");
    }

    public function errorCode(): string
    {
        return 'invalid_business_coordinates';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
