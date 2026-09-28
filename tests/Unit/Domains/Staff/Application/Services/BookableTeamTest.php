<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Services\BookableTeam;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeTeamAllowance;
use Tests\Support\Staff\FakeTeamOwnership;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->allowance = new FakeTeamAllowance;
    $this->ownership = (new FakeTeamOwnership)
        ->ownedBy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID)
        ->ownedBy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::FOURTH_MEMBER_ID);

    $this->team = new BookableTeam($this->allowance, $this->ownership);

    $this->wholeTeam = [StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID];
});

describe('on a plan that includes the team', function () {
    it('keeps every member asked about, in the order asked', function () {
        expect($this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, $this->wholeTeam))->toBe($this->wholeTeam);
    });

    it('books a member who does not own the business', function () {
        expect($this->team->isBookable(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))->toBeTrue();
    });

    it('never asks who owns the business', function () {
        $this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, $this->wholeTeam);

        expect($this->ownership->lookups)->toBe([]);
    });
});

describe('on a plan that excludes the team', function () {
    beforeEach(function () {
        $this->allowance->withoutTeamFor(FakeBusinessContext::BUSINESS_ID);
    });

    it('keeps only the owner, wherever it sits in the list', function () {
        expect($this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, $this->wholeTeam))->toBe([StaffFixtures::MEMBER_ID]);
    });

    it('always books the owner', function () {
        expect($this->team->isBookable(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID))->toBeTrue();
    });

    it('does not book a member who does not own the business', function () {
        expect($this->team->isBookable(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))->toBeFalse();
    });

    it('keeps nobody when only members who do not own the business are asked about', function () {
        expect($this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID]))->toBe([]);
    });

    it('keeps nobody in a business with no owner', function () {
        $team = new BookableTeam(
            (new FakeTeamAllowance)->withoutTeamFor(StaffFixtures::THIRD_BUSINESS_ID),
            $this->ownership,
        );

        expect($team->bookableAmong(StaffFixtures::THIRD_BUSINESS_ID, $this->wholeTeam))->toBe([]);
    });

    it('asks who owns the business once for the whole list', function () {
        $this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, $this->wholeTeam);

        expect($this->ownership->lookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('tenant isolation', function () {
    beforeEach(function () {
        $this->allowance->withoutTeamFor(FakeBusinessContext::BUSINESS_ID, StaffFixtures::OTHER_BUSINESS_ID);
    });

    it('does not treat the owner of another business as the owner here', function () {
        expect($this->team->isBookable(FakeBusinessContext::BUSINESS_ID, StaffFixtures::FOURTH_MEMBER_ID))->toBeFalse();
    });

    it('asks about the plan and the owner of the business given', function () {
        $this->team->isBookable(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::FOURTH_MEMBER_ID);

        expect($this->allowance->checks)->toBe([StaffFixtures::OTHER_BUSINESS_ID])
            ->and($this->ownership->lookups)->toBe([StaffFixtures::OTHER_BUSINESS_ID]);
    });

    it('decides each business by its own plan', function () {
        $team = new BookableTeam(
            (new FakeTeamAllowance)->withoutTeamFor(StaffFixtures::OTHER_BUSINESS_ID),
            $this->ownership,
        );

        expect($team->isBookable(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))->toBeTrue()
            ->and($team->isBookable(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))->toBeFalse();
    });
});

it('asks nothing for an empty list', function () {
    expect($this->team->bookableAmong(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
        ->and($this->allowance->checks)->toBe([])
        ->and($this->ownership->lookups)->toBe([]);
});
