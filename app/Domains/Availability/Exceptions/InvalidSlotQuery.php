<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSlotQuery extends DomainException implements DomainFailure
{
    public static function malformedServiceId(string $serviceId): self
    {
        return new self("[{$serviceId}] is not a service identifier.");
    }

    public static function malformedStaffId(string $staffId): self
    {
        return new self("[{$staffId}] is not a staff member identifier.");
    }

    public function errorCode(): string
    {
        return 'invalid_slot_query';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
