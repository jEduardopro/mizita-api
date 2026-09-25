<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\ValueObjects\CalendarScope;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Contracts\BusinessAuthorization;

final class StaffCalendarAccess implements CalendarAccess
{
    private const MANAGE_ALL_CALENDARS = 'manage_all_calendars';

    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly BusinessAuthorization $authorization,
    ) {}

    public function scopeFor(string $businessId, string $accountId): CalendarScope
    {
        $staffMemberId = $this->staffMemberIdOf($businessId, $accountId);

        if ($this->authorization->grants($accountId, $businessId, self::MANAGE_ALL_CALENDARS)) {
            return CalendarScope::everyone();
        }

        return CalendarScope::ownedBy($staffMemberId);
    }

    /**
     * @throws CalendarNotAccessible
     */
    private function staffMemberIdOf(string $businessId, string $accountId): string
    {
        try {
            return $this->members->findForAccount($businessId, $accountId)->id;
        } catch (StaffMemberNotFound $missing) {
            throw CalendarNotAccessible::forAccount($accountId, $missing);
        }
    }
}
