<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Gateways\StaffMembershipRoles;
use App\Domains\Businesses\ValueObjects\MembershipRole;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->ownedBusinessId = '01930000-0000-7000-8000-0000000000b1';
    $this->staffedBusinessId = StaffFixtures::OTHER_BUSINESS_ID;
    $this->closedBusinessId = StaffFixtures::THIRD_BUSINESS_ID;

    $this->staffMembers = new FakeStaffMemberRepository;
    $this->roles = new StaffMembershipRoles($this->staffMembers);
});

it('keys each role by business uuid, owner for the owned business and staff for the staffed one', function () {
    $this->staffMembers->store(
        StaffFixtures::member(id: StaffFixtures::MEMBER_ID, businessId: $this->ownedBusinessId, role: StaffRole::Owner),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: $this->staffedBusinessId, role: StaffRole::Member),
    );

    expect($this->roles->rolesOf(StaffFixtures::ACCOUNT_ID))->toBe([
        $this->ownedBusinessId => MembershipRole::Owner,
        $this->staffedBusinessId => MembershipRole::Staff,
    ]);
});

it('leaves out the businesses that are closed', function () {
    $this->staffMembers
        ->store(
            StaffFixtures::member(id: StaffFixtures::MEMBER_ID, businessId: $this->closedBusinessId, role: StaffRole::Owner),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: $this->staffedBusinessId, role: StaffRole::Member),
        )
        ->closeBusiness($this->closedBusinessId);

    expect($this->roles->rolesOf(StaffFixtures::ACCOUNT_ID))->toBe([
        $this->staffedBusinessId => MembershipRole::Staff,
    ]);
});

it('reports only the memberships of the account it was asked about', function () {
    $this->staffMembers->store(
        StaffFixtures::member(id: StaffFixtures::MEMBER_ID, businessId: $this->staffedBusinessId, role: StaffRole::Member),
        StaffFixtures::member(
            id: StaffFixtures::SECOND_MEMBER_ID,
            accountId: StaffFixtures::SECOND_ACCOUNT_ID,
            businessId: $this->ownedBusinessId,
            role: StaffRole::Owner,
        ),
    );

    expect($this->roles->rolesOf(StaffFixtures::ACCOUNT_ID))->toBe([
        $this->staffedBusinessId => MembershipRole::Staff,
    ]);
});

it('never reports a member without access as an owner', function () {
    $this->staffMembers->store(
        StaffFixtures::member(businessId: $this->staffedBusinessId, role: StaffRole::NoAccess),
    );

    expect($this->roles->rolesOf(StaffFixtures::ACCOUNT_ID)[$this->staffedBusinessId])
        ->not->toBe(MembershipRole::Owner);
});

it('returns no role for an account with no membership', function () {
    expect($this->roles->rolesOf(StaffFixtures::THIRD_ACCOUNT_ID))->toBe([]);
});
