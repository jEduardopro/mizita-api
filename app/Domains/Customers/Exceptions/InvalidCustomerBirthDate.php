<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerBirthDate extends DomainException implements DomainFailure
{
    public static function malformed(string $value): self
    {
        return new self("The birth date [{$value}] is not a calendar date.");
    }

    public static function inTheFuture(): self
    {
        return new self('A birth date cannot be later than today.');
    }

    public static function tooEarly(int $earliestYear): self
    {
        return new self("A birth date cannot be earlier than the year {$earliestYear}.");
    }

    public function errorCode(): string
    {
        return 'invalid_customer_birth_date';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
