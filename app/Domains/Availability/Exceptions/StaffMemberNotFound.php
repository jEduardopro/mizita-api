<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class StaffMemberNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] was not found.");
    }

    public static function inBusiness(string $staffMemberId, string $businessId, ?Throwable $previous = null): self
    {
        return new self("Staff member [{$staffMemberId}] does not belong to business [{$businessId}].", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'staff_member_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
