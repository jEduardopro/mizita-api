<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\StaffRoster;
use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound as StaffMemberMissingFromStaff;

final class StaffStaffRoster implements StaffRoster
{
    public function __construct(
        private readonly StaffMemberRepository $members,
    ) {}

    public function confirmMembership(string $businessId, string $staffMemberId): void
    {
        try {
            $this->members->findForBusiness($businessId, $staffMemberId);
        } catch (StaffMemberMissingFromStaff $missing) {
            throw StaffMemberNotFound::inBusiness($staffMemberId, $businessId, $missing);
        }
    }
}
