<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTimeOfDay extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a time of day written as HH:MM.");
    }

    public static function outOfRange(string $value): self
    {
        return new self("[{$value}] is outside the hours a day holds.");
    }

    public function errorCode(): string
    {
        return 'invalid_time_of_day';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
