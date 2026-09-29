<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\TeamOwnership;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Subscriptions\Contracts\BillingContacts;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingContact;

final class BusinessOwnerBillingContacts implements BillingContacts
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly TeamOwnership $ownership,
        private readonly StaffMemberRepository $staffMembers,
        private readonly AccountRepository $accounts,
    ) {}

    public function ownerOf(string $businessId): BillingContact
    {
        try {
            return new BillingContact(
                businessId: $businessId,
                name: $this->businesses->findById($businessId)->name(),
                email: $this->accounts->findById($this->ownerAccountIdOf($businessId))->email(),
            );
        } catch (BusinessNotFound|StaffMemberNotFound|AccountNotFound) {
            throw SubscriptionBusinessNotFound::withId($businessId);
        }
    }

    /**
     * @throws StaffMemberNotFound
     * @throws SubscriptionBusinessNotFound
     */
    private function ownerAccountIdOf(string $businessId): string
    {
        $ownerStaffMemberId = $this->ownership->ownerStaffMemberIdOf($businessId)
            ?? throw SubscriptionBusinessNotFound::withId($businessId);

        return $this->staffMembers->findForBusiness($businessId, $ownerStaffMemberId)->accountId;
    }
}
