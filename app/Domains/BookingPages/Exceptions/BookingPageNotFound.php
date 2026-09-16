<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BookingPageNotFound extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("Business [{$businessId}] has no booking page.");
    }

    public function errorCode(): string
    {
        return 'booking_page_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
