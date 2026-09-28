<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BookingRulesRequireCompletePlan extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("Business [{$businessId}] cannot change its booking preferences or contact fields on its current plan.");
    }

    public function errorCode(): string
    {
        return 'booking_rules_require_complete_plan';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
