<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->journal = new AppointmentJournal;

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->presenter = new GuestBookingPresenter($this->services, $this->staff);

    $this->describe = fn (): GuestBookingData => $this->presenter
        ->describe(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::guestAppointment(),
            CancellationRule::ofMinutes(120),
            AppointmentFixtures::now(),
        );
});

it('describes the booking, field by field', function () {
    $booking = ($this->describe)();

    expect($booking->referenceCode)->toBe(AppointmentFixtures::REFERENCE_CODE)
        ->and($booking->serviceName)->toBe(AppointmentFixtures::SERVICE_NAME)
        ->and($booking->staffMemberName)->toBe(AppointmentFixtures::STAFF_NAME)
        ->and($booking->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
        ->and($booking->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
        ->and($booking->durationMinutes)->toBe(90)
        ->and($booking->status)->toBe(AppointmentStatus::Booked)
        ->and($booking->cancelledAt)->toBeNull()
        ->and($booking->cancellationWindowMinutes)->toBe(120)
        ->and($booking->changeable)->toBeTrue();
});

it('carries no customer name and no customer identity', function () {
    expect(get_object_vars(($this->describe)()))
        ->not->toHaveKey('customerName')
        ->not->toHaveKey('customerId')
        ->and(json_encode(($this->describe)(), JSON_THROW_ON_ERROR))
        ->not->toContain(AppointmentFixtures::CUSTOMER_NAME)
        ->not->toContain(AppointmentFixtures::CUSTOMER_ID);
});

it('asks only for the service and the staff member, never for the customer', function () {
    ($this->describe)();

    expect($this->journal->entries)->toBe(['services.describe', 'staff.describe']);
});

it('refuses an appointment that carries no reference code', function () {
    expect(fn () => $this->presenter->describe(
        FakeBusinessContext::BUSINESS_ID,
        AppointmentFixtures::appointment(),
        CancellationRule::ofMinutes(120),
        AppointmentFixtures::now(),
    ))->toThrow(GuestBookingNotFound::class);
});

it('looks the service up under the business it was given, so another tenant sees nothing', function () {
    expect(fn () => $this->presenter->describe(
        AppointmentFixtures::OTHER_BUSINESS_ID,
        AppointmentFixtures::guestAppointment(businessId: AppointmentFixtures::OTHER_BUSINESS_ID),
        CancellationRule::ofMinutes(120),
        AppointmentFixtures::now(),
    ))->toThrow(AppointmentServiceNotFound::class);
});
