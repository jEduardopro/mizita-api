<?php

declare(strict_types=1);

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Staff\Infrastructure\Gateways\AppointmentsUpcomingAppointments;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

it('asks the appointments of the business about the member, as of the instant it was handed', function (bool $upcoming) {
    $now = new DateTimeImmutable('2026-03-29T01:30:00+00:00');
    $appointments = Mockery::mock(AppointmentRepository::class);
    $appointments->shouldReceive('hasUpcomingForStaffMember')->once()
        ->with(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID, $now)
        ->andReturn($upcoming);

    expect((new AppointmentsUpcomingAppointments($appointments))->existFor(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID, $now))
        ->toBe($upcoming);
})->with([
    'some ahead' => true,
    'none ahead' => false,
]);
