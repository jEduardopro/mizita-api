<?php

declare(strict_types=1);

use App\Domains\Notifications\Infrastructure\Gateways\StaffBusinessOwners;
use Tests\Support\Staff\FakeTeamOwnership;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

beforeEach(function () {
    $this->ownership = (new FakeTeamOwnership)
        ->ownedBy(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID);

    $this->owners = new StaffBusinessOwners($this->ownership);
});

it('answers the staff member uuid of the owner of the business it was asked about', function () {
    expect($this->owners->ownerStaffMemberIdOf(NotificationsFixtures::BUSINESS_ID))->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
        ->and($this->ownership->lookups)->toBe([NotificationsFixtures::BUSINESS_ID]);
});

it('answers null for a business that has no owner', function () {
    expect($this->owners->ownerStaffMemberIdOf(NotificationsFixtures::OTHER_BUSINESS_ID))->toBeNull();
});
