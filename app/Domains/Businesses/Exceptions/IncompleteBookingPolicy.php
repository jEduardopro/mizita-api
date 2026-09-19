<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class IncompleteBookingPolicy extends DomainException implements DomainFailure
{
    /**
     * @param  list<string>  $keys
     */
    public static function missing(array $keys): self
    {
        return new self('The booking policy was submitted without ['.implode(', ', $keys).'].');
    }

    public function errorCode(): string
    {
        return 'incomplete_booking_policy';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
