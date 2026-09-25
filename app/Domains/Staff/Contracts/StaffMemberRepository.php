<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;

interface StaffMemberRepository
{
    /**
     * @throws AccountAlreadyOwnsBusiness
     * @throws TeamMemberAlreadyExists
     */
    public function save(StaffMember $member): void;

    /**
     * @throws StaffMemberNotFound
     */
    public function findById(string $id): StaffMember;

    /**
     * @throws StaffMemberNotFound
     */
    public function findForBusiness(string $businessId, string $id): StaffMember;

    /**
     * @throws StaffMemberNotFound
     */
    public function findForAccount(string $businessId, string $accountId): StaffMember;

    /**
     * @return list<StaffMember>
     */
    public function allForBusiness(string $businessId): array;

    /**
     * @param  list<string>  $ids
     * @return list<StaffMember>
     */
    public function findManyIncludingArchived(string $businessId, array $ids): array;

    /**
     * @return list<StaffMember>
     */
    public function allForAccount(string $accountId): array;

    /**
     * @return list<StaffMember>
     */
    public function allInOpenBusinessesForAccount(string $accountId): array;

    public function ownsAnyBusiness(string $accountId): bool;

    public function ownedBusinessIdOf(string $accountId): ?string;

    /**
     * @throws StaffMemberNotFound
     */
    public function delete(string $businessId, string $id): void;
}
