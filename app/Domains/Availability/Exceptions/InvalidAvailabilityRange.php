<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAvailabilityRange extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a calendar date written as YYYY-MM-DD.");
    }

    public static function inverted(string $from, string $to): self
    {
        return new self("An availability range must end on or after it starts, got [{$from}] to [{$to}].");
    }

    public function errorCode(): string
    {
        return 'invalid_availability_range';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
