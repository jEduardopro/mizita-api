<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Services\BookingReadinessAssessor;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeServiceAssignments;
use Tests\Support\Staff\FakeWorkingHours;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->services = new FakeServiceAssignments;
    $this->workingHours = new FakeWorkingHours;
    $this->assessor = new BookingReadinessAssessor($this->services, $this->workingHours);
});

describe('one member', function () {
    it('lets a member who offers a service and has working hours receive a link', function () {
        $this->services->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);
        $this->workingHours->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        $readiness = $this->assessor->assess(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($readiness->allowsBookingLink())->toBeTrue()
            ->and($readiness->blockers)->toBe([]);
    });

    it('blocks a member who offers no service', function () {
        $this->workingHours->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($this->assessor->assess(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID)->blockers)
            ->toBe([BookingLinkBlocker::NoServices]);
    });

    it('blocks a member with no working hours', function () {
        $this->services->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($this->assessor->assess(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID)->blockers)
            ->toBe([BookingLinkBlocker::NoWorkingHours]);
    });

    it('reports the missing services before the missing hours', function () {
        expect($this->assessor->assess(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID)->blockers)
            ->toBe([BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]);
    });
});

describe('many members at once', function () {
    it('assesses every member, keyed by the member uuid', function () {
        $this->services->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID);
        $this->workingHours->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID);

        $readiness = $this->assessor->assessMany(FakeBusinessContext::BUSINESS_ID, [
            StaffFixtures::MEMBER_ID,
            StaffFixtures::SECOND_MEMBER_ID,
            StaffFixtures::THIRD_MEMBER_ID,
        ]);

        expect(array_keys($readiness))->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID])
            ->and($readiness[StaffFixtures::MEMBER_ID]->blockers)->toBe([])
            ->and($readiness[StaffFixtures::SECOND_MEMBER_ID]->blockers)->toBe([BookingLinkBlocker::NoWorkingHours])
            ->and($readiness[StaffFixtures::THIRD_MEMBER_ID]->blockers)->toBe([BookingLinkBlocker::NoServices]);
    });

    it('asks each port once for the whole batch, so a team list costs two lookups', function () {
        $this->assessor->assessMany(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]);

        $batch = ['businessId' => FakeBusinessContext::BUSINESS_ID, 'staffMemberIds' => [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]];

        expect($this->services->lookups)->toBe([$batch])
            ->and($this->workingHours->lookups)->toBe([$batch]);
    });

    it('asks nothing and answers nothing for an empty team', function () {
        expect($this->assessor->assessMany(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
            ->and($this->services->lookups)->toBe([])
            ->and($this->workingHours->lookups)->toBe([]);
    });

    it('asks about the business it was handed, so another tenant cannot make a member ready', function () {
        $this->services->offering(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::MEMBER_ID);
        $this->workingHours->working(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::MEMBER_ID);

        $readiness = $this->assessor->assess(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($readiness->blockers)->toBe([BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours])
            ->and($this->services->lookups[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->workingHours->lookups[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});
