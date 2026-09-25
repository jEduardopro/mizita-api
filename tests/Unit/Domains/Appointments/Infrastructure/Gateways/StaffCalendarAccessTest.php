<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\Infrastructure\Gateways\StaffCalendarAccess;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->memberLookups = [];
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->members->shouldReceive('findForAccount')->andReturnUsing(
        function (string $businessId, string $accountId) {
            $this->memberLookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

            if ($businessId === FakeBusinessContext::BUSINESS_ID && $accountId === AppointmentFixtures::ACCOUNT_ID) {
                return StaffFixtures::member(
                    id: AppointmentFixtures::STAFF_ID,
                    accountId: AppointmentFixtures::ACCOUNT_ID,
                    role: StaffRole::Member,
                );
            }

            throw StaffMemberNotFound::forAccount($accountId);
        },
    );

    $this->authorization = new FakeBusinessAuthorization;

    $this->scopeFor = fn (string $accountId = AppointmentFixtures::ACCOUNT_ID) => (new StaffCalendarAccess(
        $this->members,
        $this->authorization,
    ))->scopeFor(FakeBusinessContext::BUSINESS_ID, $accountId);
});

describe('a team member without the permission to keep every calendar', function () {
    it('keeps only the calendar of their own staff member', function () {
        expect(($this->scopeFor)()->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('stays restricted when the permission was granted in another business only', function () {
        $this->authorization->add(
            AppointmentFixtures::ACCOUNT_ID,
            AppointmentFixtures::OTHER_BUSINESS_ID,
            ['manage_all_calendars'],
        );

        expect(($this->scopeFor)()->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('stays restricted with every other permission in the business', function () {
        $this->authorization->add(
            AppointmentFixtures::ACCOUNT_ID,
            FakeBusinessContext::BUSINESS_ID,
            ['view_appointments', 'manage_appointments'],
        );

        expect(($this->scopeFor)()->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });
});

describe('a team member with the permission to keep every calendar', function () {
    beforeEach(function () {
        $this->authorization->add(
            AppointmentFixtures::ACCOUNT_ID,
            FakeBusinessContext::BUSINESS_ID,
            ['manage_all_calendars'],
        );
    });

    it('keeps every calendar of the business', function () {
        $scope = ($this->scopeFor)();

        expect($scope->restrictedStaffMemberId())->toBeNull()
            ->and($scope->permits(AppointmentFixtures::SECOND_STAFF_ID))->toBeTrue();
    });

    it('asks about exactly that permission, for that account, in that business', function () {
        ($this->scopeFor)();

        expect($this->authorization->lastCheck())->toBe([
            'accountId' => AppointmentFixtures::ACCOUNT_ID,
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'permission' => 'manage_all_calendars',
        ]);
    });
});

describe('an account with no staff row in the business', function () {
    it('refuses the account as unable to reach the business', function () {
        expect(fn () => ($this->scopeFor)(AppointmentFixtures::SECOND_ACCOUNT_ID))
            ->toThrow(CalendarNotAccessible::class);
    });

    it('refuses with a forbidden failure the transport can classify', function () {
        $failure = null;

        try {
            ($this->scopeFor)(AppointmentFixtures::SECOND_ACCOUNT_ID);
        } catch (CalendarNotAccessible $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('business_not_accessible')
            ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden)
            ->and($failure?->getPrevious())->toBeInstanceOf(StaffMemberNotFound::class);
    });

    it('refuses the account even when the permission was granted to it', function () {
        $this->authorization->add(
            AppointmentFixtures::SECOND_ACCOUNT_ID,
            FakeBusinessContext::BUSINESS_ID,
            ['manage_all_calendars'],
        );

        expect(fn () => ($this->scopeFor)(AppointmentFixtures::SECOND_ACCOUNT_ID))
            ->toThrow(CalendarNotAccessible::class);
    });

    it('looks the staff row up in the business and for the account it was handed', function () {
        expect(fn () => ($this->scopeFor)(AppointmentFixtures::SECOND_ACCOUNT_ID))
            ->toThrow(CalendarNotAccessible::class)
            ->and($this->memberLookups)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
            ]]);
    });
});
