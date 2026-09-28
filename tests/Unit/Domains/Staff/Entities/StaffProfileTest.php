<?php

declare(strict_types=1);

use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\BookingLinkAlreadyExists;
use App\Domains\Staff\Exceptions\BookingLinkNotFound;
use App\Domains\Staff\Exceptions\StaffMemberCannotReceiveBookings;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Domains\Staff\ValueObjects\BookingReadiness;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Domains\Staff\ValueObjects\JobTitle;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

describe('create', function () {
    it('creates a blank profile for a staff member of a business', function () {
        $profile = StaffProfile::create(
            id: StaffFixtures::PROFILE_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: StaffFixtures::MEMBER_ID,
            now: StaffFixtures::now(),
        );

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull()
            ->and($profile->bookingSlug())->toBeNull()
            ->and($profile->createdAt)->toEqual(StaffFixtures::now());
    });

    it('keeps its own identity apart from the staff member it describes', function () {
        $profile = StaffProfile::create(StaffFixtures::PROFILE_ID, FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID, StaffFixtures::now());

        expect($profile->id)->not->toBe($profile->staffMemberId);
    });
});

describe('restore', function () {
    it('rehydrates a profile exactly as it was stored', function () {
        $createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');

        $profile = StaffProfile::restore(
            id: StaffFixtures::PROFILE_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: StaffFixtures::MEMBER_ID,
            jobTitle: JobTitle::restore(StaffFixtures::JOB_TITLE),
            about: About::restore(StaffFixtures::ABOUT),
            bookingSlug: BookingSlug::restore(StaffFixtures::BOOKING_SLUG),
            createdAt: $createdAt,
        );

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT)
            ->and($profile->bookingSlug()?->value)->toBe(StaffFixtures::BOOKING_SLUG)
            ->and($profile->createdAt)->toEqual($createdAt);
    });

    it('rehydrates a profile that never described itself', function () {
        $profile = StaffFixtures::profile(jobTitle: null, about: null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull()
            ->and($profile->bookingSlug())->toBeNull();
    });
});

describe('createForOwner', function () {
    it('creates an undescribed profile that already carries its booking slug', function () {
        $profile = StaffProfile::createForOwner(
            id: StaffFixtures::PROFILE_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: StaffFixtures::MEMBER_ID,
            bookingSlug: BookingSlug::fromName('Ada Lovelace'),
            now: StaffFixtures::now(),
        );

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull()
            ->and($profile->bookingSlug()?->value)->toBe('ada-lovelace')
            ->and($profile->createdAt)->toEqual(StaffFixtures::now());
    });
});

describe('assignBookingSlug', function () {
    beforeEach(function () {
        $this->ready = BookingReadiness::blockedBy();
    });

    it('takes the slug when the member is ready and has none yet', function () {
        $profile = StaffFixtures::profile();

        $profile->assignBookingSlug(BookingSlug::fromString('ada'), $this->ready);

        expect($profile->bookingSlug()?->value)->toBe('ada');
    });

    it('refuses a second link, keeping the first', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        expect(fn () => $profile->assignBookingSlug(BookingSlug::fromString('ada-2'), $this->ready))
            ->toThrow(BookingLinkAlreadyExists::class, 'Staff member ['.StaffFixtures::MEMBER_ID.'] already has a booking link.')
            ->and($profile->bookingSlug()?->value)->toBe(StaffFixtures::BOOKING_SLUG);
    });

    it('refuses a member that cannot receive bookings yet, taking nothing', function (array $blockers) {
        $profile = StaffFixtures::profile();

        expect(fn () => $profile->assignBookingSlug(BookingSlug::fromString('ada'), BookingReadiness::blockedBy(...$blockers)))
            ->toThrow(StaffMemberCannotReceiveBookings::class)
            ->and($profile->bookingSlug())->toBeNull();
    })->with([
        'no services' => [[BookingLinkBlocker::NoServices]],
        'no working hours' => [[BookingLinkBlocker::NoWorkingHours]],
        'neither' => [[BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]],
    ]);

    it('reports an existing link before a blocker, so a linked member who lost their hours still hears about the link', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        expect(fn () => $profile->assignBookingSlug(
            BookingSlug::fromString('ada-2'),
            BookingReadiness::blockedBy(BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours),
        ))->toThrow(BookingLinkAlreadyExists::class);
    });
});

describe('changeBookingSlug', function () {
    it('replaces the slug of a member who already has a link', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        $profile->changeBookingSlug(BookingSlug::fromString('ada-la-barbera'));

        expect($profile->bookingSlug()?->value)->toBe('ada-la-barbera');
    });

    it('accepts the slug it already carries', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        $profile->changeBookingSlug(BookingSlug::fromString(StaffFixtures::BOOKING_SLUG));

        expect($profile->bookingSlug()?->value)->toBe(StaffFixtures::BOOKING_SLUG);
    });

    it('refuses to edit a link that was never generated, so an edit cannot skip the readiness check', function () {
        $profile = StaffFixtures::profile();

        expect(fn () => $profile->changeBookingSlug(BookingSlug::fromString('ada')))
            ->toThrow(BookingLinkNotFound::class, 'Staff member ['.StaffFixtures::MEMBER_ID.'] has no booking link yet.')
            ->and($profile->bookingSlug())->toBeNull();
    });

    it('leaves the description untouched', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        $profile->changeBookingSlug(BookingSlug::fromString('ada'));

        expect($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT);
    });
});

describe('describe', function () {
    it('takes the job title and the description it is handed', function () {
        $profile = StaffFixtures::profile(jobTitle: null, about: null);

        $profile->describe(JobTitle::fromNullable('Colorista'), About::fromNullable('Especialista en rubios.'));

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about()?->value)->toBe('Especialista en rubios.');
    });

    it('clears both when handed nothing', function () {
        $profile = StaffFixtures::profile();

        $profile->describe(null, null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull();
    });

    it('replaces each field on its own, so clearing one does not keep the other', function () {
        $profile = StaffFixtures::profile();

        $profile->describe(JobTitle::fromNullable('Colorista'), null);

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about())->toBeNull();
    });

    it('leaves the identity, the business, the booking slug and the creation instant untouched', function () {
        $profile = StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG);

        $profile->describe(null, null);

        expect($profile->bookingSlug()?->value)->toBe(StaffFixtures::BOOKING_SLUG)
            ->and($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->createdAt)->toEqual(StaffFixtures::now());
    });
});

describe('changing one field at a time', function () {
    it('changes the job title and keeps the description', function () {
        $profile = StaffFixtures::profile();

        $profile->changeJobTitle(JobTitle::fromNullable('Colorista'));

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT);
    });

    it('clears the job title and keeps the description', function () {
        $profile = StaffFixtures::profile();

        $profile->changeJobTitle(null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT);
    });

    it('changes the description and keeps the job title', function () {
        $profile = StaffFixtures::profile();

        $profile->changeAbout(About::fromNullable('Especialista en rubios.'));

        expect($profile->about()?->value)->toBe('Especialista en rubios.')
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });

    it('clears the description and keeps the job title', function () {
        $profile = StaffFixtures::profile();

        $profile->changeAbout(null);

        expect($profile->about())->toBeNull()
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });
});
