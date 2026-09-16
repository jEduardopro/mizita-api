<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBookingPageTheme extends DomainException implements DomainFailure
{
    public static function withValue(string $value): self
    {
        return new self("[{$value}] is not a theme a booking page may be given.");
    }

    public function errorCode(): string
    {
        return 'invalid_booking_page_theme';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
