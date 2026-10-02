<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPaymentReportPeriod extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("The report date [{$value}] is not a calendar date in the YYYY-MM-DD form.");
    }

    public static function incomplete(): self
    {
        return new self('A report period needs both a from and a to date, or neither.');
    }

    public static function inverted(string $from, string $to): self
    {
        return new self("A report period has to start on or before it ends, got [{$from}] to [{$to}].");
    }

    public static function tooWide(string $from, string $to, int $maximumYears): self
    {
        return new self("A report period may span at most [{$maximumYears}] years, got [{$from}] to [{$to}].");
    }

    public function errorCode(): string
    {
        return 'invalid_payment_report_period';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
