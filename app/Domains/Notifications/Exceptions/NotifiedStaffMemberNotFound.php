<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class NotifiedStaffMemberNotFound extends RuntimeException implements DomainFailure
{
    public static function inBusiness(string $businessId, string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] of business [{$businessId}] to notify about was not found.");
    }

    public function errorCode(): string
    {
        return 'notified_staff_member_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
