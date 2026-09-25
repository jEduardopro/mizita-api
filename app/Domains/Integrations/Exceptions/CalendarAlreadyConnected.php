<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CalendarAlreadyConnected extends DomainException implements DomainFailure
{
    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] already has a connected calendar.");
    }

    public function errorCode(): string
    {
        return 'calendar_already_connected';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
