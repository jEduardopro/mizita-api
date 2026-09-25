<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Gateways;

use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final class StaffCalendarOwners implements CalendarOwners
{
    public function __construct(
        private readonly StaffMemberRepository $members,
    ) {}

    public function staffMemberIdOf(string $businessId, string $accountId): string
    {
        try {
            return $this->members->findForAccount($businessId, $accountId)->id;
        } catch (StaffMemberNotFound $missing) {
            throw CalendarOwnerNotFound::forAccount($accountId, $missing);
        }
    }
}
