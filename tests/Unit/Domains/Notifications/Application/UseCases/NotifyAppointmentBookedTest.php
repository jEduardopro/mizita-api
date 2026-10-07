<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Application\UseCases\NotifyAppointmentBooked;
use App\Domains\Notifications\ValueObjects\BookedAppointment;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBookedAppointments;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->appointments = (new FakeBookedAppointments($this->journal))->add(
        new BookedAppointment(
            appointmentId: NotificationsFixtures::APPOINTMENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            staffMemberId: NotificationsFixtures::MEMBER_ID,
        ),
        new BookedAppointment(
            appointmentId: NotificationsFixtures::SECOND_APPOINTMENT_ID,
            businessId: NotificationsFixtures::OTHER_BUSINESS_ID,
            staffMemberId: NotificationsFixtures::OTHER_MEMBER_ID,
        ),
    );
    $this->notifications = new FakeStaffNotificationRepository($this->journal);

    $this->useCase = new NotifyAppointmentBooked(
        $this->appointments,
        $this->notifications,
        new FixedIdGenerator(NotificationsFixtures::NEW_NOTIFICATION_ID),
        new FakeClock(NotificationsFixtures::now()),
    );

    $this->notify = fn (string $appointmentId = NotificationsFixtures::APPOINTMENT_ID) => $this->useCase
        ->handle(new NotifyAppointmentBookedInput($appointmentId));
});

describe('a booked appointment', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('adds one notification addressed to the staff member the appointment is booked with', function () {
        ($this->notify)();

        expect($this->notifications->added)->toHaveCount(1)
            ->and($this->notifications->added[0]->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('identifies the notification and its appointment by uuid', function () {
        ($this->notify)();

        $notification = $this->notifications->added[0];

        expect($notification->id)->toBe(NotificationsFixtures::NEW_NOTIFICATION_ID)
            ->and($notification->appointmentId)->toBe(NotificationsFixtures::APPOINTMENT_ID);
    });

    it('files the notification as a booking, unread, at the instant of the clock', function () {
        ($this->notify)();

        $notification = $this->notifications->added[0];

        expect($notification->type)->toBe(NotificationType::AppointmentBooked)
            ->and($notification->isUnread())->toBeTrue()
            ->and($notification->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('files the notification under the business the appointment belongs to', function (string $appointmentId, string $businessId, string $recipient) {
        ($this->notify)($appointmentId);

        expect($this->notifications->added[0]->businessId)->toBe($businessId)
            ->and($this->notifications->added[0]->recipientStaffMemberId)->toBe($recipient);
    })->with([
        'the first business' => [NotificationsFixtures::APPOINTMENT_ID, NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ID],
        'another business' => [NotificationsFixtures::SECOND_APPOINTMENT_ID, NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID],
    ]);

    it('asks for the appointment it was handed', function () {
        ($this->notify)();

        expect($this->appointments->lookups)->toBe([NotificationsFixtures::APPOINTMENT_ID]);
    });

    it('adds the notification through the idempotent door, never through a plain save', function () {
        ($this->notify)();

        expect($this->journal->entries)->toBe(['appointments.recipientOf', 'notifications.addOnce'])
            ->and($this->notifications->saved)->toBe([]);
    });
});

describe('an appointment that cannot be found', function () {
    it('answers not found', function () {
        $response = ($this->notify)(NotificationsFixtures::UNKNOWN_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notified_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('adds no notification', function () {
        ($this->notify)(NotificationsFixtures::UNKNOWN_APPOINTMENT_ID);

        expect($this->notifications->added)->toBe([]);
    });
});

describe('an appointment identifier that is no uuid', function () {
    it('answers not found', function (string $appointmentId) {
        $response = ($this->notify)($appointmentId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notified_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '42',
        'a word' => 'not-a-uuid',
    ]);

    it('refuses before it looks anything up or adds anything', function () {
        ($this->notify)('42');

        expect($this->journal->entries)->toBe([]);
    });
});
