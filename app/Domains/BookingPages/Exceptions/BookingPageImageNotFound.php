<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BookingPageImageNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Booking page image [{$id}] is not on record.");
    }

    public function errorCode(): string
    {
        return 'booking_page_image_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
