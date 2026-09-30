<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidStatisticsPeriod extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("The statistics date [{$value}] is not a calendar date in the YYYY-MM-DD form.");
    }

    public static function incomplete(): self
    {
        return new self('A statistics period needs both a from and a to date, or neither.');
    }

    public static function inverted(string $from, string $to): self
    {
        return new self("A statistics period has to start on or before it ends, got [{$from}] to [{$to}].");
    }

    public static function endsAfterToday(string $to, string $today): self
    {
        return new self("A statistics period may not end after today [{$today}], got [{$to}].");
    }

    public function errorCode(): string
    {
        return 'invalid_statistics_period';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
