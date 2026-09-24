<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class StaffProfileNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Staff profile [{$id}] was not found.");
    }

    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] has no profile.");
    }

    public function errorCode(): string
    {
        return 'staff_profile_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
