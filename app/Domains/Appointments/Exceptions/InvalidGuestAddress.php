<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidGuestAddress extends DomainException implements DomainFailure
{
    public static function withoutStreet(): self
    {
        return new self('A guest address needs a street.');
    }

    public static function streetTooLong(int $maximumLength): self
    {
        return new self("A guest address street is at most {$maximumLength} characters long.");
    }

    public static function cityTooLong(int $maximumLength): self
    {
        return new self("A guest address city is at most {$maximumLength} characters long.");
    }

    public static function stateNameTooLong(int $maximumLength): self
    {
        return new self("A guest address state is at most {$maximumLength} characters long.");
    }

    public static function postalCodeOutOfBounds(int $minimumLength, int $maximumLength): self
    {
        return new self("A guest address postal code runs between {$minimumLength} and {$maximumLength} characters.");
    }

    public static function malformedCountryCode(string $countryCode): self
    {
        return new self("[{$countryCode}] is not a two-letter country code.");
    }

    public static function rejected(Throwable $previous): self
    {
        return new self('That address was not accepted.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'invalid_guest_address';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
