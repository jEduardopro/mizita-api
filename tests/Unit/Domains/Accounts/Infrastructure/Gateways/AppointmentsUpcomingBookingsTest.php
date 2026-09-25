<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Gateways\AppointmentsUpcomingBookings;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->appointments = new FakeAppointmentRepository;
    $this->clock = new FakeClock(AppointmentFixtures::now());
    $this->gateway = new AppointmentsUpcomingBookings($this->appointments, $this->clock);
});

it('counts the upcoming appointments of the business at the clock instant', function () {
    $this->appointments->store(
        AppointmentFixtures::appointment(id: AppointmentFixtures::APPOINTMENT_ID),
        AppointmentFixtures::appointment(id: AppointmentFixtures::SECOND_APPOINTMENT_ID, startsAt: '2026-04-01T09:00:00+00:00', endsAt: '2026-04-01T10:00:00+00:00'),
    );

    expect($this->gateway->countForBusiness(FakeBusinessContext::BUSINESS_ID))->toBe(2)
        ->and($this->appointments->upcomingCounts)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'now' => AppointmentFixtures::NOW,
        ]]);
});

it('answers zero for a business with nothing booked', function () {
    $this->appointments->store(AppointmentFixtures::appointment(businessId: AppointmentFixtures::OTHER_BUSINESS_ID));

    expect($this->gateway->countForBusiness(FakeBusinessContext::BUSINESS_ID))->toBe(0);
});

it('reads the instant from the clock on every call, never caching it', function () {
    $this->gateway->countForBusiness(FakeBusinessContext::BUSINESS_ID);
    $this->clock->advance('PT1H');
    $this->gateway->countForBusiness(FakeBusinessContext::BUSINESS_ID);

    expect(array_column($this->appointments->upcomingCounts, 'now'))
        ->toBe(['2026-01-01T12:00:00+00:00', '2026-01-01T13:00:00+00:00']);
});
