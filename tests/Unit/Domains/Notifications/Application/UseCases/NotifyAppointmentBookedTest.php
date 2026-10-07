<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Application\UseCases\NotifyAppointmentBooked;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBookedAppointments;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->appointments = (new FakeBookedAppointments($this->journal))->add(
        NotificationsFixtures::bookedAppointment(),
        NotificationsFixtures::bookedAppointment(
            appointmentId: NotificationsFixtures::SECOND_APPOINTMENT_ID,
            businessId: NotificationsFixtures::OTHER_BUSINESS_ID,
            staffMemberId: NotificationsFixtures::OTHER_MEMBER_ID,
        ),
    );
    $this->transactions = new FakeTransactionManager;
    $this->notifications = new FakeStaffNotificationRepository($this->journal, $this->transactions);

    $this->useCase = new NotifyAppointmentBooked(
        $this->appointments,
        $this->notifications,
        $this->transactions,
        new FixedIdGenerator(NotificationsFixtures::EVENT_ID, NotificationsFixtures::NEW_NOTIFICATION_ID),
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

    it('records one event with exactly one delivery', function () {
        ($this->notify)();

        expect($this->notifications->recorded)->toHaveCount(1)
            ->and($this->notifications->recorded[0]['deliveries'])->toHaveCount(1);
    });

    it('records the event under the first uuid it generated, about the appointment, at the instant of the clock', function () {
        ($this->notify)();

        $event = $this->notifications->recorded[0]['event'];

        expect($event->id)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($event->type)->toBe(NotificationType::AppointmentBooked)
            ->and($event->subject->type)->toBe(NotificationSubjectType::Appointment)
            ->and($event->subject->id)->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($event->occurredAt)->toEqual(NotificationsFixtures::now());
    });

    it('keys the event on the appointment uuid, so a redelivered booking is recognised as the same fact', function () {
        ($this->notify)();

        expect($this->notifications->recorded[0]['event']->idempotencyKey)
            ->toBe('appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID);
    });

    it('snapshots the appointment and the customer under their uuids', function () {
        ($this->notify)();

        $payload = $this->notifications->recorded[0]['event']->payload;

        expect($payload)->toBeInstanceOf(AppointmentBookedPayload::class)
            ->and($payload->toArray())->toBe(NotificationsFixtures::bookingSnapshot());
    });

    it('delivers to the staff member the appointment is booked with, under the second uuid it generated', function () {
        ($this->notify)();

        $delivery = $this->notifications->recorded[0]['deliveries'][0];

        expect($delivery->id)->toBe(NotificationsFixtures::NEW_NOTIFICATION_ID)
            ->and($delivery->eventId)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($delivery->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('collapses the delivery on the appointment uuid', function () {
        ($this->notify)();

        expect($this->notifications->recorded[0]['deliveries'][0]->collapseKey)
            ->toBe('appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID);
    });

    it('delivers unread, at the instant of the clock', function () {
        ($this->notify)();

        $delivery = $this->notifications->recorded[0]['deliveries'][0];

        expect($delivery->isUnread())->toBeTrue()
            ->and($delivery->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('files the event and the delivery under the business the appointment belongs to', function (string $appointmentId, string $businessId, string $recipient) {
        ($this->notify)($appointmentId);

        $recorded = $this->notifications->recorded[0];

        expect($recorded['event']->businessId)->toBe($businessId)
            ->and($recorded['deliveries'][0]->businessId)->toBe($businessId)
            ->and($recorded['deliveries'][0]->recipientStaffMemberId)->toBe($recipient);
    })->with([
        'the first business' => [NotificationsFixtures::APPOINTMENT_ID, NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ID],
        'another business' => [NotificationsFixtures::SECOND_APPOINTMENT_ID, NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID],
    ]);

    it('asks for the appointment it was handed', function () {
        ($this->notify)();

        expect($this->appointments->lookups)->toBe([NotificationsFixtures::APPOINTMENT_ID]);
    });

    it('records the event inside one transaction', function () {
        ($this->notify)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->notifications->recorded[0]['insideTransaction'])->toBeTrue();
    });

    it('describes the appointment before it records anything, and records through the event door only', function () {
        ($this->notify)();

        expect($this->journal->entries)->toBe(['appointments.describe', 'notifications.record'])
            ->and($this->notifications->markedAsRead)->toBe([]);
    });
});

describe('an appointment that cannot be found', function () {
    it('answers not found', function () {
        $response = ($this->notify)(NotificationsFixtures::UNKNOWN_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notified_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('records nothing and opens no transaction', function () {
        ($this->notify)(NotificationsFixtures::UNKNOWN_APPOINTMENT_ID);

        expect($this->notifications->recorded)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
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

    it('refuses before it looks anything up or records anything', function () {
        ($this->notify)('42');

        expect($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});
