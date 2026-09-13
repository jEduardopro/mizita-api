<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

/**
 * Port for StaffMember persistence. It speaks entities, never Eloquent models
 * or query builders, so use cases stay independent of the database.
 *
 * Deliberately three methods: exactly what this domain calls today. Inviting
 * and managing a team is later work, and the queries it needs arrive with it.
 */
interface StaffMemberRepository
{
    /**
     * Writes the membership.
     *
     * @throws AccountAlreadyOwnsBusiness when the owner uniqueness index rejects the row
     */
    public function save(StaffMember $member): void;

    /**
     * @throws StaffMemberNotFound
     */
    public function findById(string $id): StaffMember;

    /**
     * Whether this account already holds an owner membership anywhere on the
     * platform. Deliberately not scoped to a business: the rule it answers -
     * one owned business per account - is platform-wide.
     */
    public function ownsAnyBusiness(string $accountId): bool;
}
