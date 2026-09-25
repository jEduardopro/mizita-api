<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\Infrastructure\Gateways\StaffCalendarAccess;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->memberLookups = [];
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->members->shouldReceive('findForAccount')->andReturnUsing(
        function (string $businessId, string $accountId) {
            $this->memberLookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

            if ($businessId === FakeBusinessContext::BUSINESS_ID && $accountId === PaymentFixtures::ACTOR_ID) {
                return StaffFixtures::member(
                    id: PaymentFixtures::STAFF_MEMBER_ID,
                    accountId: PaymentFixtures::ACTOR_ID,
                    role: StaffRole::Member,
                );
            }

            throw StaffMemberNotFound::forAccount($accountId);
        },
    );

    $this->authorization = new FakeBusinessAuthorization;

    $this->scopeFor = fn (string $accountId = PaymentFixtures::ACTOR_ID) => (new StaffCalendarAccess(
        $this->authorization,
        $this->members,
    ))->scopeFor(FakeBusinessContext::BUSINESS_ID, $accountId);
});

describe('a team member without the permission to keep every calendar', function () {
    it('keeps a restricted calendar', function () {
        expect(($this->scopeFor)()->isRestricted())->toBeTrue();
    });

    it('covers the appointments of their own staff member', function () {
        expect(($this->scopeFor)()->covers(PaymentFixtures::appointmentSnapshot()))->toBeTrue();
    });

    it('covers no appointment of another team member', function () {
        $appointment = PaymentFixtures::appointmentSnapshot(staffMemberId: PaymentFixtures::OTHER_STAFF_MEMBER_ID);

        expect(($this->scopeFor)()->covers($appointment))->toBeFalse();
    });

    it('stays restricted when the permission was granted in another business only', function () {
        $this->authorization->add(PaymentFixtures::ACTOR_ID, PaymentFixtures::OTHER_BUSINESS_ID, ['manage_all_calendars']);

        expect(($this->scopeFor)()->isRestricted())->toBeTrue();
    });

    it('looks the staff row up in the business and for the account it was handed', function () {
        ($this->scopeFor)();

        expect($this->memberLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => PaymentFixtures::ACTOR_ID,
        ]]);
    });
});

describe('a team member with the permission to keep every calendar', function () {
    beforeEach(function () {
        $this->authorization->add(PaymentFixtures::ACTOR_ID, FakeBusinessContext::BUSINESS_ID, ['manage_all_calendars']);
    });

    it('keeps every calendar of the business', function () {
        $scope = ($this->scopeFor)();

        expect($scope->isRestricted())->toBeFalse()
            ->and($scope->covers(PaymentFixtures::appointmentSnapshot(staffMemberId: PaymentFixtures::OTHER_STAFF_MEMBER_ID)))
            ->toBeTrue();
    });

    it('asks about exactly that permission, for that account, in that business', function () {
        ($this->scopeFor)();

        expect($this->authorization->lastCheck())->toBe([
            'accountId' => PaymentFixtures::ACTOR_ID,
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'permission' => 'manage_all_calendars',
        ]);
    });
});

describe('an account with no staff row in the business', function () {
    it('refuses the account as not found', function () {
        expect(fn () => ($this->scopeFor)(PaymentFixtures::OTHER_ACTOR_ID))->toThrow(PaymentAccountNotFound::class);
    });

    it('refuses with a failure the transport can classify', function () {
        $failure = null;

        try {
            ($this->scopeFor)(PaymentFixtures::OTHER_ACTOR_ID);
        } catch (PaymentAccountNotFound $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('payment_account_not_found')
            ->and($failure?->kind())->toBe(DomainFailureKind::NotFound);
    });
});

describe('an account holding the permission to keep every calendar but no staff row in the business', function () {
    beforeEach(function () {
        $this->authorization->add(PaymentFixtures::OTHER_ACTOR_ID, FakeBusinessContext::BUSINESS_ID, ['manage_all_calendars']);
    });

    it('refuses the account as not found instead of opening every calendar', function () {
        expect(fn () => ($this->scopeFor)(PaymentFixtures::OTHER_ACTOR_ID))->toThrow(PaymentAccountNotFound::class);
    });

    it('never asks about the permission', function () {
        try {
            ($this->scopeFor)(PaymentFixtures::OTHER_ACTOR_ID);
        } catch (PaymentAccountNotFound) {
        }

        expect($this->authorization->checks)->toBe([])
            ->and($this->authorization->lookups)->toBe([]);
    });
});
