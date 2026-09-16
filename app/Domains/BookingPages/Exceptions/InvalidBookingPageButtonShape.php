<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBookingPageButtonShape extends DomainException implements DomainFailure
{
    public static function withValue(string $value): self
    {
        return new self("[{$value}] is not a button shape a booking page may be given.");
    }

    public function errorCode(): string
    {
        return 'invalid_booking_page_button_shape';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
