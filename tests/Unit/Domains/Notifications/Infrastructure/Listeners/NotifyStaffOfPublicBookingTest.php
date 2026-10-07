<?php

declare(strict_types=1);

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Notifications\Application\UseCases\NotifyAppointmentBooked;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\Infrastructure\Listeners\NotifyStaffOfPublicBooking;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBookedAppointments;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

beforeEach(function () {
    $this->appointments = (new FakeBookedAppointments)->add(NotificationsFixtures::bookedAppointment());
    $this->notifications = new FakeStaffNotificationRepository;

    $this->listener = new NotifyStaffOfPublicBooking(new NotifyAppointmentBooked(
        $this->appointments,
        $this->notifications,
        new FakeTransactionManager,
        new FixedIdGenerator(NotificationsFixtures::EVENT_ID, NotificationsFixtures::NEW_NOTIFICATION_ID),
        new FakeClock(NotificationsFixtures::now()),
    ));
});

it('notifies the staff member of the appointment the guest booked', function () {
    $this->listener->handle(new AppointmentBooked(NotificationsFixtures::APPOINTMENT_ID));

    expect($this->appointments->lookups)->toBe([NotificationsFixtures::APPOINTMENT_ID])
        ->and($this->notifications->recorded)->toHaveCount(1)
        ->and($this->notifications->recorded[0]['event']->subject->id)->toBe(NotificationsFixtures::APPOINTMENT_ID)
        ->and($this->notifications->recorded[0]['deliveries'][0]->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
});

it('rethrows a refusal so the queue retries the job', function () {
    expect(fn () => $this->listener->handle(new AppointmentBooked(NotificationsFixtures::UNKNOWN_APPOINTMENT_ID)))
        ->toThrow(NotifiedAppointmentNotFound::class);
});

it('runs only once the booking transaction has committed', function () {
    expect($this->listener)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('retries three times, backing off between attempts', function () {
    expect($this->listener->tries)->toBe(3)
        ->and($this->listener->backoff())->toBe([30, 120]);
});
