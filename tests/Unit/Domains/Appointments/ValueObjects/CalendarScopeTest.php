<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentStaffNotPermitted;
use App\Domains\Appointments\ValueObjects\CalendarScope;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;

dataset('team members of the business', [
    'the first' => AppointmentFixtures::STAFF_ID,
    'the second' => AppointmentFixtures::SECOND_STAFF_ID,
    'one nobody has seen' => AppointmentFixtures::UNKNOWN_ID,
]);

describe('a scope over every calendar', function () {
    it('permits every team member', function (string $staffMemberId) {
        expect(CalendarScope::everyone()->permits($staffMemberId))->toBeTrue();
    })->with('team members of the business');

    it('lets the caller assign any team member', function (string $staffMemberId) {
        expect(fn () => CalendarScope::everyone()->ensureMayAssign($staffMemberId))->not->toThrow(Throwable::class);
    })->with('team members of the business');

    it('names no team member to restrict a query to', function () {
        expect(CalendarScope::everyone()->restrictedStaffMemberId())->toBeNull();
    });
});

describe('a scope over one team member calendar', function () {
    beforeEach(function () {
        $this->scope = CalendarScope::ownedBy(AppointmentFixtures::STAFF_ID);
    });

    it('permits the team member it belongs to', function () {
        expect($this->scope->permits(AppointmentFixtures::STAFF_ID))->toBeTrue();
    });

    it('permits no other team member', function (string $staffMemberId) {
        expect($this->scope->permits($staffMemberId))->toBeFalse();
    })->with([
        'another member' => AppointmentFixtures::SECOND_STAFF_ID,
        'one nobody has seen' => AppointmentFixtures::UNKNOWN_ID,
        'an empty id' => '',
    ]);

    it('lets the caller assign the team member it belongs to', function () {
        expect(fn () => $this->scope->ensureMayAssign(AppointmentFixtures::STAFF_ID))->not->toThrow(Throwable::class);
    });

    it('refuses to let the caller assign another team member', function () {
        expect(fn () => $this->scope->ensureMayAssign(AppointmentFixtures::SECOND_STAFF_ID))
            ->toThrow(AppointmentStaffNotPermitted::class);
    });

    it('refuses with a forbidden failure the transport can classify', function () {
        $failure = null;

        try {
            $this->scope->ensureMayAssign(AppointmentFixtures::SECOND_STAFF_ID);
        } catch (AppointmentStaffNotPermitted $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('appointment_staff_not_permitted')
            ->and($failure?->kind())->toBe(DomainFailureKind::Forbidden);
    });

    it('names the team member a query has to be restricted to', function () {
        expect($this->scope->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });
});
