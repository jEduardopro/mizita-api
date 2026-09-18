<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCalendarRange extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('The calendar range offered is not a readable pair of instants.');
    }

    public static function inverted(): self
    {
        return new self('A calendar range has to end after it starts.');
    }

    public static function tooWide(int $maximumDays): self
    {
        return new self("A calendar range may not span more than {$maximumDays} days.");
    }

    public function errorCode(): string
    {
        return 'invalid_calendar_range';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
