<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBookingWindow extends DomainException implements DomainFailure
{
    public static function notPositive(int $minutes): self
    {
        return new self("[{$minutes}] is not a booking window a customer can book within.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is further ahead than a booking window may reach.");
    }

    public function errorCode(): string
    {
        return 'invalid_booking_window';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
