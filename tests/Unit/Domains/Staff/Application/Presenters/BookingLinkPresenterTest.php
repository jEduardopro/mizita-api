<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\BookingLinkStatus;
use App\Domains\Staff\Exceptions\BookingLinkNotFound;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeBusinessSlugs;
use Tests\Support\Staff\FakeServiceAssignments;
use Tests\Support\Staff\FakeWorkingHours;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->services = new FakeServiceAssignments;
    $this->workingHours = new FakeWorkingHours;
    $this->businesses = new FakeBusinessSlugs;
    $this->presenter = StaffFixtures::bookingLinkPresenter($this->services, $this->workingHours, $this->businesses);

    $this->linked = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);
    $this->unlinked = StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID);
});

describe('the link of one profile', function () {
    it('answers with the slug and the absolute url under the business page', function () {
        $link = $this->presenter->linkOf($this->linked);

        expect($link)->toBeInstanceOf(BookingLinkData::class)
            ->and($link->slug)->toBe(StaffFixtures::BOOKING_SLUG)
            ->and($link->url)->toBe(StaffFixtures::BOOKING_URL)
            ->and($this->businesses->lookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('refuses a profile that has no link yet', function () {
        expect(fn () => $this->presenter->linkOf($this->unlinked))->toThrow(BookingLinkNotFound::class);
    });
});

describe('the status of one profile', function () {
    it('carries the link and no blocker for a ready member who has one', function () {
        $this->services->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);
        $this->workingHours->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        $status = $this->presenter->statusOf($this->linked);

        expect($status)->toBeInstanceOf(BookingLinkStatus::class)
            ->and($status->link?->slug)->toBe(StaffFixtures::BOOKING_SLUG)
            ->and($status->link?->url)->toBe(StaffFixtures::BOOKING_URL)
            ->and($status->blockers)->toBe([]);
    });

    it('carries no link and every blocker for a member who is not ready yet', function () {
        $status = $this->presenter->statusOf($this->unlinked);

        expect($status->link)->toBeNull()
            ->and($status->blockers)->toBe([BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]);
    });

    it('keeps the link of a member who has since lost what made them ready', function () {
        $status = $this->presenter->statusOf($this->linked);

        expect($status->link?->slug)->toBe(StaffFixtures::BOOKING_SLUG)
            ->and($status->blockers)->toBe([BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]);
    });

    it('does not look the business slug up for a member with no link', function () {
        $this->presenter->statusOf($this->unlinked);

        expect($this->businesses->lookups)->toBe([]);
    });
});

describe('the statuses of a team', function () {
    it('answers for every member, including one with no profile at all', function () {
        $statuses = $this->presenter->statusesOf(
            FakeBusinessContext::BUSINESS_ID,
            [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID],
            [StaffFixtures::MEMBER_ID => $this->linked, StaffFixtures::SECOND_MEMBER_ID => $this->unlinked],
        );

        expect(array_keys($statuses))->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID])
            ->and($statuses[StaffFixtures::MEMBER_ID]->link?->url)->toBe(StaffFixtures::BOOKING_URL)
            ->and($statuses[StaffFixtures::SECOND_MEMBER_ID]->link)->toBeNull()
            ->and($statuses[StaffFixtures::THIRD_MEMBER_ID]->link)->toBeNull()
            ->and($statuses[StaffFixtures::THIRD_MEMBER_ID]->blockers)->toBe([BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]);
    });

    it('reads the business slug once however many members are linked', function () {
        $second = StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID, bookingSlug: 'grace');

        $statuses = $this->presenter->statusesOf(
            FakeBusinessContext::BUSINESS_ID,
            [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID],
            [StaffFixtures::MEMBER_ID => $this->linked, StaffFixtures::SECOND_MEMBER_ID => $second],
        );

        expect($this->businesses->lookups)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($statuses[StaffFixtures::SECOND_MEMBER_ID]->link?->url)
            ->toBe(StaffFixtures::BOOKING_BASE_URL.'/'.StaffFixtures::BUSINESS_SLUG.'/equipo/grace');
    });

    it('assesses the whole team in one batch', function () {
        $this->presenter->statusesOf(
            FakeBusinessContext::BUSINESS_ID,
            [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID],
            [StaffFixtures::MEMBER_ID => $this->linked],
        );

        expect($this->services->lookups)->toHaveCount(1)
            ->and($this->workingHours->lookups)->toHaveCount(1)
            ->and($this->services->lookups[0]['staffMemberIds'])->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]);
    });

    it('never builds a link under another business slug', function () {
        $presenter = StaffFixtures::bookingLinkPresenter(businesses: new FakeBusinessSlugs([
            FakeBusinessContext::BUSINESS_ID => StaffFixtures::BUSINESS_SLUG,
            StaffFixtures::OTHER_BUSINESS_ID => 'otra-barberia',
        ]));

        $status = $presenter->statusesOf(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID], [StaffFixtures::MEMBER_ID => $this->linked]);

        expect($status[StaffFixtures::MEMBER_ID]->link?->url)->toBe(StaffFixtures::BOOKING_URL)
            ->and($status[StaffFixtures::MEMBER_ID]->link?->url)->not->toContain('otra-barberia');
    });
});
