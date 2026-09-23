<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerAddress extends DomainException implements DomainFailure
{
    public static function withoutStreet(): self
    {
        return new self('A customer address needs a street.');
    }

    public static function streetTooLong(int $maximum): self
    {
        return new self("A customer address street may not run past {$maximum} characters.");
    }

    public static function cityTooLong(int $maximum): self
    {
        return new self("A customer address city may not run past {$maximum} characters.");
    }

    public static function stateNameTooLong(int $maximum): self
    {
        return new self("A customer address state may not run past {$maximum} characters.");
    }

    public static function postalCodeOutOfBounds(int $minimum, int $maximum): self
    {
        return new self("A customer address postal code runs between {$minimum} and {$maximum} characters.");
    }

    public static function malformedCountryCode(string $countryCode): self
    {
        return new self("[{$countryCode}] is not a two-letter country code.");
    }

    public function errorCode(): string
    {
        return 'invalid_customer_address';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
