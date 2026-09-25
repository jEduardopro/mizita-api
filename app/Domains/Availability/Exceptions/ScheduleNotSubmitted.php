<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ScheduleNotSubmitted extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] asked to replace its schedule without submitting one.");
    }

    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] was asked to have its schedule replaced without one being submitted.");
    }

    public function errorCode(): string
    {
        return 'schedule_not_submitted';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
