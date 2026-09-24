<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class StaffMembershipNotFound extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId, string $businessId): self
    {
        return new self("Account [{$accountId}] is not a staff member of business [{$businessId}].");
    }

    public function errorCode(): string
    {
        return 'business_not_accessible';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
