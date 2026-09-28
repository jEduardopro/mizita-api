<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\GenerateStaffBookingLinkInput;
use App\Domains\Staff\Application\Services\BookingReadinessAssessor;
use App\Domains\Staff\Application\UseCases\GenerateStaffBookingLink;
use App\Domains\Staff\Exceptions\BookingSlugAlreadyTaken;
use App\Domains\Staff\Exceptions\StaffMemberCannotReceiveBookings;
use App\Domains\Staff\Services\SlugAllocator;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeBookingSlugRegistry;
use Tests\Support\Staff\FakeServiceAssignments;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeWorkingHours;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->profiles = (new FakeStaffProfileRepository)->store(
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
        StaffFixtures::profile(id: StaffFixtures::THIRD_PROFILE_ID, staffMemberId: StaffFixtures::THIRD_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID),
    );
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'José Pablo Núñez', email: 'jose@example.com'),
        StaffFixtures::account(id: StaffFixtures::THIRD_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
    );
    $this->registry = new FakeBookingSlugRegistry;
    $this->services = (new FakeServiceAssignments)->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);
    $this->workingHours = (new FakeWorkingHours)->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    $this->build = fn (?FakeBusinessContext $business = null, ?FakeAccountDirectory $accounts = null): GenerateStaffBookingLink => new GenerateStaffBookingLink(
        $this->members,
        $this->profiles,
        $this->registry,
        $accounts ?? $this->accounts,
        new BookingReadinessAssessor($this->services, $this->workingHours),
        new SlugAllocator,
        StaffFixtures::bookingLinkPresenter($this->services, $this->workingHours),
        $business ?? new FakeBusinessContext,
    );

    $this->generate = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?GenerateStaffBookingLink $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new GenerateStaffBookingLinkInput($staffMemberId));

    $this->refusal = function (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?GenerateStaffBookingLink $useCase = null): UseCaseError {
        $response = ($this->generate)($staffMemberId, $useCase);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->bookingUrlFor = fn (string $bookingSlug): string => StaffFixtures::BOOKING_BASE_URL.'/'.StaffFixtures::BUSINESS_SLUG.'/equipo/'.$bookingSlug;
});

describe('generating the link', function () {
    it('answers with the slug built from the account name and its public url', function () {
        $link = ($this->generate)()->value();

        expect($link)->toBeInstanceOf(BookingLinkData::class)
            ->and($link->slug)->toBe('jose-pablo-nunez')
            ->and($link->url)->toBe(($this->bookingUrlFor)('jose-pablo-nunez'));
    });

    it('stores the slug on the profile of the member', function () {
        ($this->generate)();

        expect($this->profiles->saved)->toHaveCount(1)
            ->and($this->profiles->saved[0]->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->saved[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->profiles->saved[0]->bookingSlug()?->value)->toBe('jose-pablo-nunez');
    });

    it('keeps the description of the profile it links', function () {
        ($this->generate)();

        expect($this->profiles->saved[0]->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($this->profiles->saved[0]->about()?->value)->toBe(StaffFixtures::ABOUT);
    });

    it('numbers the slug from two when a teammate already books under the name', function () {
        $this->registry->held(FakeBusinessContext::BUSINESS_ID, 'jose-pablo-nunez', StaffFixtures::MEMBER_ID);

        $link = ($this->generate)()->value();

        expect($link->slug)->toBe('jose-pablo-nunez-2')
            ->and($link->url)->toBe(($this->bookingUrlFor)('jose-pablo-nunez-2'))
            ->and($this->profiles->saved[0]->bookingSlug()?->value)->toBe('jose-pablo-nunez-2');
    });

    it('takes the lowest free number when several are taken', function () {
        $this->registry
            ->held(FakeBusinessContext::BUSINESS_ID, 'jose-pablo-nunez', StaffFixtures::MEMBER_ID)
            ->held(FakeBusinessContext::BUSINESS_ID, 'jose-pablo-nunez-2', StaffFixtures::FOURTH_MEMBER_ID)
            ->held(FakeBusinessContext::BUSINESS_ID, 'jose-pablo-nunez-4', StaffFixtures::THIRD_MEMBER_ID);

        expect(($this->generate)()->value()->slug)->toBe('jose-pablo-nunez-3');
    });

    it('skips a truncated number a teammate already holds when the name runs to the full length', function () {
        $longName = str_repeat('a', 60);
        $accounts = new FakeAccountDirectory(StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: $longName));
        $this->registry
            ->held(FakeBusinessContext::BUSINESS_ID, $longName, StaffFixtures::MEMBER_ID)
            ->held(FakeBusinessContext::BUSINESS_ID, str_repeat('a', 58).'-2', StaffFixtures::FOURTH_MEMBER_ID);

        $link = ($this->generate)(useCase: ($this->build)(accounts: $accounts))->value();

        expect($link->slug)->toBe(str_repeat('a', 58).'-3')
            ->and(strlen($link->slug))->toBe(60)
            ->and($this->profiles->saved[0]->bookingSlug()?->value)->toBe(str_repeat('a', 58).'-3');
    });

    it('asks the registry for the slugs of the current business that match the base', function () {
        ($this->generate)();

        expect($this->registry->matchLookups)->toBe([
            ['businessId' => FakeBusinessContext::BUSINESS_ID, 'base' => 'jose-pablo-nunez'],
        ]);
    });

    it('falls back to a generic slug for a name that slugifies to nothing', function () {
        $accounts = new FakeAccountDirectory(StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: '李小龍'));

        expect(($this->generate)(useCase: ($this->build)(accounts: $accounts))->value()->slug)->toBe('staff');
    });

    it('answers with a uuid-free, int-free link', function () {
        $link = ($this->generate)()->value();

        expect($link->url)->not->toContain(StaffFixtures::SECOND_MEMBER_ID)
            ->and($link->url)->not->toContain(FakeBusinessContext::BUSINESS_ID)
            ->and($link->url)->toStartWith(StaffFixtures::BOOKING_BASE_URL.'/'.StaffFixtures::BUSINESS_SLUG.'/equipo/');
    });
});

describe('tenant isolation', function () {
    it('looks the member and the profile up in the current business', function () {
        ($this->generate)();

        expect($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID]])
            ->and($this->profiles->lookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'staffMemberId' => StaffFixtures::SECOND_MEMBER_ID]]);
    });

    it('treats a member of another business as a member that is not there', function () {
        $error = ($this->refusal)(StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([]);
    });

    it('ignores a slug another business uses, since links live under the business page', function () {
        $this->registry->held(StaffFixtures::OTHER_BUSINESS_ID, 'jose-pablo-nunez', StaffFixtures::THIRD_MEMBER_ID);

        expect(($this->generate)()->value()->slug)->toBe('jose-pablo-nunez');
    });
});

describe('refusals', function () {
    it('refuses a second link as a conflict, keeping the first', function () {
        $this->profiles->store(StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID, bookingSlug: 'jose'));

        $error = ($this->refusal)();

        expect($error->code)->toBe('booking_link_already_exists')
            ->and($error->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->profiles->saved)->toBe([])
            ->and($this->profiles->stored(StaffFixtures::SECOND_PROFILE_ID)?->bookingSlug()?->value)->toBe('jose');
    });

    it('reports the existing link before any blocker', function () {
        $this->profiles->store(StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID, bookingSlug: 'jose'));
        $this->services = new FakeServiceAssignments;
        $this->workingHours = new FakeWorkingHours;

        expect(($this->refusal)()->code)->toBe('booking_link_already_exists');
    });

    it('refuses a member who cannot receive bookings yet', function (bool $offersServices, bool $hasWorkingHours) {
        $this->services = $offersServices
            ? (new FakeServiceAssignments)->offering(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID)
            : new FakeServiceAssignments;
        $this->workingHours = $hasWorkingHours
            ? (new FakeWorkingHours)->working(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID)
            : new FakeWorkingHours;

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_cannot_receive_bookings')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->profiles->saved)->toBe([]);
    })->with([
        'no services' => [false, true],
        'no working hours' => [true, false],
        'neither' => [false, false],
    ]);

    it('refuses a member it does not know', function () {
        $error = ($this->refusal)(StaffFixtures::FOURTH_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([]);
    });

    it('refuses a member id that is not a uuid before looking anything up', function (string $staffMemberId) {
        $error = ($this->refusal)($staffMemberId);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([])
            ->and($this->profiles->lookups)->toBe([]);
    })->with([
        'empty' => '',
        'an int id' => '42',
    ]);

    it('refuses a member with no profile', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::FOURTH_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member));

        $error = ($this->refusal)(StaffFixtures::FOURTH_MEMBER_ID);

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([]);
    });

    it('refuses a member whose account cannot be described', function () {
        $error = ($this->refusal)(useCase: ($this->build)(accounts: new FakeAccountDirectory));

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->saved)->toBe([]);
    });

    it('answers with the conflict when a racing teammate took the slug first', function () {
        $race = BookingSlugAlreadyTaken::for('jose-pablo-nunez', new RuntimeException('23505'));
        $this->profiles->refuseSaveWith($race);

        $error = ($this->refusal)();

        expect($error->code)->toBe('booking_slug_taken')
            ->and($error->kind)->toBe(DomainFailureKind::Conflict)
            ->and($error->cause())->toBe($race);
    });
});

describe('the shape of the answer', function () {
    it('succeeds with no warnings when everything holds', function () {
        $response = ($this->generate)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->warnings())->toBe([]);
    });

    it('answers with a failure instead of throwing when a domain rule refuses', function () {
        $this->services = new FakeServiceAssignments;

        $response = ($this->generate)();

        expect($response->failed())->toBeTrue()
            ->and(fn () => $response->value())->toThrow(StaffMemberCannotReceiveBookings::class);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new LogicException('the profiles table is gone');
        $this->profiles->refuseSaveWith($bug);

        expect(fn () => ($this->generate)())->toThrow($bug);
    });
});
