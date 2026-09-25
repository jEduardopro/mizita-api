<?php

declare(strict_types=1);

use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Infrastructure\Gateways\AppointmentsAppointmentDirectory;
use App\Domains\Payments\ValueObjects\AppointmentSnapshot;
use App\Domains\Payments\ValueObjects\CalendarScope;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->appointments = (new FakeAppointmentRepository)->store(
        AppointmentFixtures::appointment(),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
            cancelledAt: '2026-02-01T10:00:00+00:00',
            cancelledBy: Canceller::Business,
        ),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ),
    );

    $this->describe = fn (string $appointmentId = AppointmentFixtures::APPOINTMENT_ID) => (new AppointmentsAppointmentDirectory($this->appointments))
        ->describe(FakeBusinessContext::BUSINESS_ID, $appointmentId);
});

it('describes the appointment with the uuid of the team member whose calendar it is on', function () {
    $snapshot = ($this->describe)();

    expect($snapshot)->toBeInstanceOf(AppointmentSnapshot::class)
        ->and($snapshot->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
        ->and($snapshot->serviceId)->toBe(AppointmentFixtures::SERVICE_ID)
        ->and($snapshot->cancelled)->toBeFalse()
        ->and($snapshot->staffMemberId)->toBe(AppointmentFixtures::STAFF_ID);
});

it('carries the team member of a cancelled appointment too', function () {
    $snapshot = ($this->describe)(AppointmentFixtures::SECOND_APPOINTMENT_ID);

    expect($snapshot->cancelled)->toBeTrue()
        ->and($snapshot->staffMemberId)->toBe(AppointmentFixtures::SECOND_STAFF_ID);
});

it('hands a scope what it needs to tell one calendar from another', function () {
    $scope = CalendarScope::ownedBy(AppointmentFixtures::STAFF_ID);

    expect($scope->covers(($this->describe)()))->toBeTrue()
        ->and($scope->covers(($this->describe)(AppointmentFixtures::SECOND_APPOINTMENT_ID)))->toBeFalse();
});

it('answers not found for an appointment of another business', function () {
    expect(fn () => ($this->describe)(AppointmentFixtures::FOREIGN_APPOINTMENT_ID))
        ->toThrow(PaymentAppointmentNotFound::class);
});

it('answers not found for an appointment nobody booked', function () {
    expect(fn () => ($this->describe)(AppointmentFixtures::UNKNOWN_ID))
        ->toThrow(PaymentAppointmentNotFound::class);
});
