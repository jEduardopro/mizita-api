<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;
use App\Domains\Subscriptions\Infrastructure\Gateways\BusinessOwnerBillingContacts;
use Tests\Support\Accounts\InvitationFixtures;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeTeamOwnership;
use Tests\Support\Staff\StaffFixtures;

const BILLING_CONTACT_BUSINESS_ID = OnboardingFixtures::GENERATED_BUSINESS_ID;

beforeEach(function () {
    $this->businesses = (new FakeBusinessRepository)->store(OnboardingFixtures::business());
    $this->ownership = (new FakeTeamOwnership)->ownedBy(BILLING_CONTACT_BUSINESS_ID, StaffFixtures::MEMBER_ID);
    $this->staffMembers = (new FakeStaffMemberRepository)->store(StaffFixtures::member(
        accountId: InvitationFixtures::EXISTING_ACCOUNT_ID,
        businessId: BILLING_CONTACT_BUSINESS_ID,
    ));

    $this->accountLookups = [];
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->accounts->shouldReceive('findById')->andReturnUsing(function (string $id) {
        $this->accountLookups[] = $id;

        return $id === InvitationFixtures::EXISTING_ACCOUNT_ID
            ? InvitationFixtures::storedAccount()
            : throw AccountNotFound::withId($id);
    });

    $this->contacts = fn (): BusinessOwnerBillingContacts => new BusinessOwnerBillingContacts(
        $this->businesses,
        $this->ownership,
        $this->staffMembers,
        $this->accounts,
    );
});

it('bills the business under its own name, reaching its owner at the owner account email', function () {
    $contact = ($this->contacts)()->ownerOf(BILLING_CONTACT_BUSINESS_ID);

    expect($contact->businessId)->toBe(BILLING_CONTACT_BUSINESS_ID)
        ->and($contact->name)->toBe(OnboardingFixtures::NAME)
        ->and($contact->email)->toBe(InvitationFixtures::EMAIL);
});

it('looks up the owner of that business and no other', function () {
    ($this->contacts)()->ownerOf(BILLING_CONTACT_BUSINESS_ID);

    expect($this->ownership->lookups)->toBe([BILLING_CONTACT_BUSINESS_ID])
        ->and($this->staffMembers->businessLookups)->toBe([
            ['businessId' => BILLING_CONTACT_BUSINESS_ID, 'id' => StaffFixtures::MEMBER_ID],
        ])
        ->and($this->accountLookups)->toBe([InvitationFixtures::EXISTING_ACCOUNT_ID]);
});

it('reports the business as not found when any link to its owner is missing', function (Closure $breakTheChain) {
    $breakTheChain->call($this);

    expect(fn () => ($this->contacts)()->ownerOf(BILLING_CONTACT_BUSINESS_ID))
        ->toThrow(SubscriptionBusinessNotFound::class);
})->with([
    'the business does not exist' => function () {
        $this->businesses = new FakeBusinessRepository;
    },
    'the business has no owner' => function () {
        $this->ownership = new FakeTeamOwnership;
    },
    'the owner is a staff member of another business' => function () {
        $this->staffMembers = (new FakeStaffMemberRepository)->store(StaffFixtures::member(
            accountId: InvitationFixtures::EXISTING_ACCOUNT_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));
    },
    'the owner account is gone' => function () {
        $this->staffMembers = (new FakeStaffMemberRepository)->store(StaffFixtures::member(
            accountId: StaffFixtures::SECOND_ACCOUNT_ID,
            businessId: BILLING_CONTACT_BUSINESS_ID,
        ));
    },
]);
