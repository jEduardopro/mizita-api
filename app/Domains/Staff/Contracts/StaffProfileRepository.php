<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;

interface StaffProfileRepository
{
    /**
     * @throws StaffProfileNotFound
     */
    public function findForStaffMember(string $businessId, string $staffMemberId): StaffProfile;

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, StaffProfile>
     */
    public function findForStaffMembers(string $businessId, array $staffMemberIds): array;

    /**
     * @throws StaffMemberNotFound
     */
    public function save(StaffProfile $profile): void;
}
