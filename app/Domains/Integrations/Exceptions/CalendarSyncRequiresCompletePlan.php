<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CalendarSyncRequiresCompletePlan extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("Business [{$businessId}] cannot sync a calendar on its current plan.");
    }

    public function errorCode(): string
    {
        return 'calendar_sync_requires_complete_plan';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
