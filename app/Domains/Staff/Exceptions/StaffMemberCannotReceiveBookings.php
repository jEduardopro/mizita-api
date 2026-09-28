<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class StaffMemberCannotReceiveBookings extends DomainException implements DomainFailure
{
    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] needs services and working hours before a booking link.");
    }

    public function errorCode(): string
    {
        return 'staff_member_cannot_receive_bookings';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
