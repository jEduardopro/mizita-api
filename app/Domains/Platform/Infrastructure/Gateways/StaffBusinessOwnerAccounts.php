<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Platform\Contracts\BusinessOwnerAccounts;
use App\Domains\Platform\Exceptions\BusinessHasNoOwner;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;
use App\Domains\Platform\ValueObjects\BusinessOwnerAccount;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\TeamOwnership;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final class StaffBusinessOwnerAccounts implements BusinessOwnerAccounts
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly TeamOwnership $ownership,
        private readonly StaffMemberRepository $staffMembers,
        private readonly AccountRepository $accounts,
    ) {}

    public function ownerOf(string $businessId): BusinessOwnerAccount
    {
        $businessName = $this->nameOfBusiness($businessId);
        $owner = $this->ownerAccountOf($businessId);

        if ($owner->isScheduledForDeletion()) {
            throw BusinessOwnerDeactivated::forBusiness($businessId);
        }

        return new BusinessOwnerAccount(
            businessId: $businessId,
            businessName: $businessName,
            accountId: $owner->id,
            ownerName: $owner->name(),
        );
    }

    /**
     * @throws ImpersonatedBusinessNotFound
     */
    private function nameOfBusiness(string $businessId): string
    {
        try {
            return $this->businesses->findById($businessId)->name();
        } catch (BusinessNotFound) {
            throw ImpersonatedBusinessNotFound::withId($businessId);
        }
    }

    /**
     * @throws BusinessHasNoOwner
     */
    private function ownerAccountOf(string $businessId): Account
    {
        $ownerStaffMemberId = $this->ownership->ownerStaffMemberIdOf($businessId)
            ?? throw BusinessHasNoOwner::forBusiness($businessId);

        try {
            $accountId = $this->staffMembers->findForBusiness($businessId, $ownerStaffMemberId)->accountId;

            return $this->accounts->findById($accountId);
        } catch (StaffMemberNotFound|AccountNotFound) {
            throw BusinessHasNoOwner::forBusiness($businessId);
        }
    }
}
