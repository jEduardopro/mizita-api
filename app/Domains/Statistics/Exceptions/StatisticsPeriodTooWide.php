<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class StatisticsPeriodTooWide extends DomainException implements DomainFailure
{
    public static function spanning(int $days, int $maximumDays): self
    {
        return new self("A statistics period may span at most [{$maximumDays}] days, got [{$days}].");
    }

    public function errorCode(): string
    {
        return 'statistics_period_too_wide';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
