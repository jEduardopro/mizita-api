<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\ChangeStaffBookingSlugInput;
use App\Domains\Staff\Application\UseCases\ChangeStaffBookingSlug;
use App\Domains\Staff\Exceptions\BookingSlugAlreadyTaken;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeBookingSlugRegistry;
use Tests\Support\Staff\FakeServiceAssignments;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeWorkingHours;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::FOURTH_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->profiles = (new FakeStaffProfileRepository)->store(
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID, bookingSlug: 'jose-pablo'),
        StaffFixtures::profile(id: StaffFixtures::THIRD_PROFILE_ID, staffMemberId: StaffFixtures::THIRD_MEMBER_ID),
    );
    $this->registry = (new FakeBookingSlugRegistry)
        ->held(FakeBusinessContext::BUSINESS_ID, 'jose-pablo', StaffFixtures::SECOND_MEMBER_ID)
        ->held(FakeBusinessContext::BUSINESS_ID, 'ada', StaffFixtures::MEMBER_ID)
        ->held(StaffFixtures::OTHER_BUSINESS_ID, 'grace', StaffFixtures::FOURTH_MEMBER_ID);
    $this->services = new FakeServiceAssignments;
    $this->workingHours = new FakeWorkingHours;

    $this->build = fn (?FakeBusinessContext $business = null): ChangeStaffBookingSlug => new ChangeStaffBookingSlug(
        $this->members,
        $this->profiles,
        $this->registry,
        StaffFixtures::bookingLinkPresenter($this->services, $this->workingHours),
        $business ?? new FakeBusinessContext,
    );

    $this->change = fn (string $slug, string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?ChangeStaffBookingSlug $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(ChangeStaffBookingSlugInput::fromRequest(['slug' => $slug], $staffMemberId));

    $this->refusal = function (string $slug, string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?ChangeStaffBookingSlug $useCase = null): UseCaseError {
        $response = ($this->change)($slug, $staffMemberId, $useCase);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('changing the slug', function () {
    it('answers with the new slug and its public url', function () {
        $link = ($this->change)('jose-el-barbero')->value();

        expect($link)->toBeInstanceOf(BookingLinkData::class)
            ->and($link->slug)->toBe('jose-el-barbero')
            ->and($link->url)->toBe(StaffFixtures::BOOKING_BASE_URL.'/'.StaffFixtures::BUSINESS_SLUG.'/equipo/jose-el-barbero');
    });

    it('stores the new slug on the profile of the member', function () {
        ($this->change)('jose-el-barbero');

        expect($this->profiles->saved)->toHaveCount(1)
            ->and($this->profiles->saved[0]->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->saved[0]->bookingSlug()?->value)->toBe('jose-el-barbero')
            ->and($this->profiles->saved[0]->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });

    it('trims the slug it was handed', function () {
        expect(($this->change)('  jose-el-barbero  ')->value()->slug)->toBe('jose-el-barbero');
    });

    it('accepts the slug the member already has, as a no-op', function () {
        $link = ($this->change)('jose-pablo')->value();

        expect($link->slug)->toBe('jose-pablo')
            ->and($this->profiles->stored(StaffFixtures::SECOND_PROFILE_ID)?->bookingSlug()?->value)->toBe('jose-pablo');
    });

    it('asks whether another member of the current business holds the slug', function () {
        ($this->change)('jose-el-barbero');

        expect($this->registry->holderChecks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'bookingSlug' => 'jose-el-barbero',
            'staffMemberId' => StaffFixtures::SECOND_MEMBER_ID,
        ]]);
    });

    it('does not ask whether the member can receive bookings, since the link already exists', function () {
        ($this->change)('jose-el-barbero');

        expect($this->services->lookups)->toBe([])
            ->and($this->workingHours->lookups)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('looks the member and the profile up in the current business', function () {
        ($this->change)('jose-el-barbero');

        expect($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID]])
            ->and($this->profiles->lookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'staffMemberId' => StaffFixtures::SECOND_MEMBER_ID]]);
    });

    it('lets a member take a slug another business uses', function () {
        expect(($this->change)('grace')->value()->slug)->toBe('grace');
    });

    it('treats a member of another business as a member that is not there', function () {
        $error = ($this->refusal)('grace-2', StaffFixtures::FOURTH_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([]);
    });
});

describe('refusals', function () {
    it('refuses a slug a teammate already books under, without numbering it', function () {
        $error = ($this->refusal)('ada');

        expect($error->code)->toBe('booking_slug_taken')
            ->and($error->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->profiles->saved)->toBe([])
            ->and($this->profiles->stored(StaffFixtures::SECOND_PROFILE_ID)?->bookingSlug()?->value)->toBe('jose-pablo');
    });

    it('refuses to edit a link that was never generated', function () {
        $error = ($this->refusal)('grace', StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('booking_link_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([])
            ->and($this->registry->holderChecks)->toBe([]);
    });

    it('refuses a malformed slug before looking anything up', function (string $slug) {
        $error = ($this->refusal)($slug);

        expect($error->code)->toBe('invalid_booking_slug')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->members->businessLookups)->toBe([])
            ->and($this->profiles->saved)->toBe([]);
    })->with([
        'empty' => '',
        'uppercase' => 'Jose',
        'accented' => 'josé',
        'spaces inside' => 'jose pablo',
        'too long' => str_repeat('a', 61),
    ]);

    it('refuses a member it does not know', function () {
        $error = ($this->refusal)('jose', StaffFixtures::MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->profiles->saved)->toBe([]);
    });

    it('answers with the conflict when a racing teammate took the slug first', function () {
        $race = BookingSlugAlreadyTaken::for('jose-el-barbero', new RuntimeException('23505'));
        $this->profiles->refuseSaveWith($race);

        $error = ($this->refusal)('jose-el-barbero');

        expect($error->code)->toBe('booking_slug_taken')
            ->and($error->cause())->toBe($race);
    });
});

describe('the shape of the answer', function () {
    it('succeeds with no warnings when everything holds', function () {
        $response = ($this->change)('jose-el-barbero');

        expect($response->succeeded())->toBeTrue()
            ->and($response->warnings())->toBe([]);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new LogicException('the profiles table is gone');
        $this->profiles->refuseSaveWith($bug);

        expect(fn () => ($this->change)('jose-el-barbero'))->toThrow($bug);
    });
});
