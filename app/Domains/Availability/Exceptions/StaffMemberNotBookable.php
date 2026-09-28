<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class StaffMemberNotBookable extends RuntimeException implements DomainFailure
{
    public static function forService(string $staffId, string $serviceId): self
    {
        return new self("Staff member [{$staffId}] does not perform service [{$serviceId}].");
    }

    public static function underCurrentPlan(string $staffId): self
    {
        return new self("Staff member [{$staffId}] is not bookable under the business's current plan.");
    }

    public function errorCode(): string
    {
        return 'staff_member_not_bookable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
