<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\StaffMembership;
use App\Domains\Availability\Exceptions\StaffMembershipNotFound;
use App\Domains\Staff\Contracts\StaffMemberRepository;

final class StaffStaffMembership implements StaffMembership
{
    public function __construct(
        private readonly StaffMemberRepository $members,
    ) {}

    public function staffMemberIdOf(string $businessId, string $accountId): string
    {
        foreach ($this->members->allForBusiness($businessId) as $member) {
            if ($member->accountId === $accountId) {
                return $member->id;
            }
        }

        throw StaffMembershipNotFound::forAccount($accountId, $businessId);
    }
}
