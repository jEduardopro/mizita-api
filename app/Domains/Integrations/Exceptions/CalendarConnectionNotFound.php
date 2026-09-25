<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class CalendarConnectionNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Calendar connection [{$id}] was not found.");
    }

    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] has no calendar connection.");
    }

    public function errorCode(): string
    {
        return 'calendar_connection_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
