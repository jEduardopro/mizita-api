<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Gateways;

use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\ValueObjects\CalendarScope;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Contracts\BusinessAuthorization;

final class StaffCalendarAccess implements CalendarAccess
{
    private const MANAGE_ALL_CALENDARS = 'manage_all_calendars';

    public function __construct(
        private readonly BusinessAuthorization $authorization,
        private readonly StaffMemberRepository $members,
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
     * @throws PaymentAccountNotFound
     */
    private function staffMemberIdOf(string $businessId, string $accountId): string
    {
        try {
            return $this->members->findForAccount($businessId, $accountId)->id;
        } catch (StaffMemberNotFound) {
            throw PaymentAccountNotFound::withId($accountId);
        }
    }
}
