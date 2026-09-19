<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessCurrentlyClosed extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("Business [{$businessId}] is closed right now and takes no public bookings.");
    }

    public function errorCode(): string
    {
        return 'business_currently_closed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
