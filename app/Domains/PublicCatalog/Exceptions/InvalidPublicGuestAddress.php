<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPublicGuestAddress extends DomainException implements DomainFailure
{
    public static function streetTooLong(int $maximumLength): self
    {
        return new self("A guest address street is at most {$maximumLength} characters long.");
    }

    public static function cityTooLong(int $maximumLength): self
    {
        return new self("A guest address city is at most {$maximumLength} characters long.");
    }

    public static function stateTooLong(int $maximumLength): self
    {
        return new self("A guest address state is at most {$maximumLength} characters long.");
    }

    public static function postalCodeOutOfBounds(int $minimumLength, int $maximumLength): self
    {
        return new self("A guest address postal code runs between {$minimumLength} and {$maximumLength} characters.");
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
