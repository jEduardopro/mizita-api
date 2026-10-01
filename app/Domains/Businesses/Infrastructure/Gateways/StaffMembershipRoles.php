<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\MembershipRoles;
use App\Domains\Businesses\ValueObjects\MembershipRole;
use App\Domains\Staff\Contracts\StaffMemberRepository;

final class StaffMembershipRoles implements MembershipRoles
{
    public function __construct(
        private readonly StaffMemberRepository $staffMembers,
    ) {}

    /**
     * @return array<string, MembershipRole>
     */
    public function rolesOf(string $accountId): array
    {
        $roles = [];

        foreach ($this->staffMembers->allInOpenBusinessesForAccount($accountId) as $member) {
            $roles[$member->businessId] = $member->ownsBusiness() ? MembershipRole::Owner : MembershipRole::Staff;
        }

        return $roles;
    }
}
