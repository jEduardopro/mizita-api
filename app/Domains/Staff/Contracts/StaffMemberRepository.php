<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

/**
 * Deliberately three methods: exactly what this domain calls today. Inviting
 * and managing a team is later work, and the queries it needs arrive with it.
 */
interface StaffMemberRepository
{
    /**
     * @throws AccountAlreadyOwnsBusiness when the owner uniqueness index rejects the row
     */
    public function save(StaffMember $member): void;

    /**
     * @throws StaffMemberNotFound
     */
    public function findById(string $id): StaffMember;

    /**
     * Deliberately not scoped to a business: the rule it answers - one owned
     * business per account - is platform-wide.
     */
    public function ownsAnyBusiness(string $accountId): bool;
}
