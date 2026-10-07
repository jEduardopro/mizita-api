<?php

declare(strict_types=1);

use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

describe('recording a booking', function () {
    beforeEach(function () {
        $this->event = NotificationEvent::record(
            id: NotificationsFixtures::EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::bookingPayload(),
            occurredAt: NotificationsFixtures::now(),
        );
    });

    it('carries the uuids it was handed for itself and its business, and the instant it occurred', function () {
        expect($this->event->id)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($this->event->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($this->event->occurredAt)->toEqual(NotificationsFixtures::now());
    });

    it('takes its type and its subject from the payload', function () {
        expect($this->event->type)->toBe(NotificationType::AppointmentBooked)
            ->and($this->event->subject->type)->toBe(NotificationSubjectType::Appointment)
            ->and($this->event->subject->id)->toBe(NotificationsFixtures::APPOINTMENT_ID);
    });

    it('keeps the payload it was handed', function () {
        $payload = NotificationsFixtures::bookingPayload();

        $event = NotificationEvent::record(
            id: NotificationsFixtures::EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: $payload,
            occurredAt: NotificationsFixtures::now(),
        );

        expect($event->payload)->toBe($payload);
    });

    it('is keyed on the appointment, never on its own uuid', function () {
        expect($this->event->idempotencyKey)->toBe('appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID);
    });

    it('shares its idempotency key with a second event about the same appointment', function () {
        $redelivered = NotificationEvent::record(
            id: NotificationsFixtures::NEXT_EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::bookingPayload(),
            occurredAt: NotificationsFixtures::now()->modify('+1 minute'),
        );

        expect($redelivered->idempotencyKey)->toBe($this->event->idempotencyKey);
    });

    it('delivers under the collapse key of the appointment', function () {
        $delivery = $this->event->deliverTo(NotificationsFixtures::NEW_NOTIFICATION_ID, NotificationsFixtures::MEMBER_ID);

        expect($delivery->collapseKey)->toBe('appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID);
    });
});

describe('recording a schedule change', function () {
    beforeEach(function () {
        $this->event = NotificationEvent::record(
            id: NotificationsFixtures::EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::scheduleChangePayload(),
            occurredAt: NotificationsFixtures::now(),
        );
    });

    it('takes its type and its subject from the payload', function () {
        expect($this->event->type)->toBe(NotificationType::StaffScheduleChanged)
            ->and($this->event->subject->type)->toBe(NotificationSubjectType::StaffMember)
            ->and($this->event->subject->id)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('is keyed on its own uuid', function () {
        expect($this->event->idempotencyKey)->toBe(NotificationsFixtures::EVENT_ID);
    });

    it('never shares its idempotency key with a later change of the same staff member', function () {
        $later = NotificationEvent::record(
            id: NotificationsFixtures::NEXT_EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::scheduleChangePayload(),
            occurredAt: NotificationsFixtures::now()->modify('+10 minutes'),
        );

        expect($later->idempotencyKey)->not->toBe($this->event->idempotencyKey);
    });

    it('delivers under the collapse key of the staff member, shared with every later change of theirs', function () {
        $later = NotificationEvent::record(
            id: NotificationsFixtures::NEXT_EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::scheduleChangePayload(),
            occurredAt: NotificationsFixtures::now()->modify('+10 minutes'),
        );

        $first = $this->event->deliverTo(NotificationsFixtures::NEW_NOTIFICATION_ID, NotificationsFixtures::OWNER_MEMBER_ID);
        $second = $later->deliverTo(NotificationsFixtures::NEXT_NOTIFICATION_ID, NotificationsFixtures::OWNER_MEMBER_ID);

        expect($first->collapseKey)->toBe(NotificationsFixtures::SCHEDULE_CHANGE_COLLAPSE_KEY)
            ->and($second->collapseKey)->toBe($first->collapseKey);
    });
});

describe('delivering an event', function () {
    beforeEach(function () {
        $this->event = NotificationEvent::record(
            id: NotificationsFixtures::EVENT_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            payload: NotificationsFixtures::bookingPayload(),
            occurredAt: NotificationsFixtures::now(),
        );

        $this->delivery = $this->event->deliverTo(NotificationsFixtures::NEW_NOTIFICATION_ID, NotificationsFixtures::MEMBER_ID);
    });

    it('creates a delivery under the uuid it was handed, addressed to the recipient it was handed', function () {
        expect($this->delivery->id)->toBe(NotificationsFixtures::NEW_NOTIFICATION_ID)
            ->and($this->delivery->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('files the delivery under the business and the event it came from', function () {
        expect($this->delivery->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($this->delivery->eventId)->toBe(NotificationsFixtures::EVENT_ID);
    });

    it('delivers unread, at the instant the event occurred', function () {
        expect($this->delivery->isUnread())->toBeTrue()
            ->and($this->delivery->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('delivers the same event to several recipients as distinct deliveries of one event', function () {
        $other = $this->event->deliverTo(NotificationsFixtures::NEXT_NOTIFICATION_ID, NotificationsFixtures::OWNER_MEMBER_ID);

        expect($other->id)->not->toBe($this->delivery->id)
            ->and($other->eventId)->toBe($this->delivery->eventId)
            ->and($other->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
            ->and($other->collapseKey)->toBe($this->delivery->collapseKey);
    });
});
