<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerRegistrationPeriod extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("The registration date [{$value}] is not a calendar date in the YYYY-MM-DD form.");
    }

    public static function incomplete(): self
    {
        return new self('A registration period needs both a from and a to date, or neither.');
    }

    public static function inverted(string $from, string $to): self
    {
        return new self("A registration period has to start on or before it ends, got [{$from}] to [{$to}].");
    }

    public function errorCode(): string
    {
        return 'invalid_customer_registration_period';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
