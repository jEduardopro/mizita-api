<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TooManyScheduleIntervals extends DomainException implements DomainFailure
{
    public static function submitted(int $count, int $maximum): self
    {
        return new self("A weekly schedule may hold at most [{$maximum}] intervals, got [{$count}].");
    }

    public function errorCode(): string
    {
        return 'too_many_schedule_intervals';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
