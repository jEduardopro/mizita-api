<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ScheduleIntervalInverted extends DomainException implements DomainFailure
{
    public static function between(string $startsAt, string $endsAt): self
    {
        return new self("A schedule interval must end after it starts, got [{$startsAt}] to [{$endsAt}].");
    }

    public function errorCode(): string
    {
        return 'schedule_interval_inverted';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
