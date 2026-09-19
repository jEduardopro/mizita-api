<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AvailabilityRangeTooWide extends DomainException implements DomainFailure
{
    public static function spanning(int $days, int $maximumDays): self
    {
        return new self("An availability range may span at most [{$maximumDays}] days, got [{$days}].");
    }

    public function errorCode(): string
    {
        return 'availability_range_too_wide';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
