<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidWeekday extends DomainException implements DomainFailure
{
    public static function withNumber(int $number): self
    {
        return new self("[{$number}] is not a day of the week.");
    }

    public function errorCode(): string
    {
        return 'invalid_weekday';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
