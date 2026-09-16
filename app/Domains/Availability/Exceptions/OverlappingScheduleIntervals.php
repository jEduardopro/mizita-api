<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class OverlappingScheduleIntervals extends DomainException implements DomainFailure
{
    public static function onWeekday(int $weekday): self
    {
        return new self("Two schedule intervals overlap on weekday [{$weekday}].");
    }

    public function errorCode(): string
    {
        return 'overlapping_schedule_intervals';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
