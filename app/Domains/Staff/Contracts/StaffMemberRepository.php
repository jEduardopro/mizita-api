<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

interface StaffMemberRepository
{
    /**
     * @throws AccountAlreadyOwnsBusiness
     */
    public function save(StaffMember $member): void;

    /**
     * @throws StaffMemberNotFound
     */
    public function findById(string $id): StaffMember;

    /**
     * @return list<StaffMember>
     */
    public function allForBusiness(string $businessId): array;

    public function ownsAnyBusiness(string $accountId): bool;
}
