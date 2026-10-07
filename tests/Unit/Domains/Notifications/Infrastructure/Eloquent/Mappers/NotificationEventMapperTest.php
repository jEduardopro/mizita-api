<?php

declare(strict_types=1);

use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\NotificationEventMapper;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

const NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY = 42;

const NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY = 99;

beforeEach(function () {
    $this->mapper = new NotificationEventMapper;
    $this->booking = NotificationEvent::record(
        id: NotificationsFixtures::EVENT_ID,
        businessId: NotificationsFixtures::BUSINESS_ID,
        payload: NotificationsFixtures::bookingPayload(),
        occurredAt: NotificationsFixtures::now(),
    );
});

it('writes exactly the columns the row owns', function () {
    expect($this->mapper->toAttributes($this->booking, NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY, NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY))->toEqual([
        'uuid' => NotificationsFixtures::EVENT_ID,
        'business_id' => NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY,
        'type' => NotificationType::AppointmentBooked,
        'subject_type' => NotificationSubjectType::Appointment,
        'subject_id' => NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY,
        'payload' => NotificationsFixtures::bookingSnapshot(),
        'idempotency_key' => 'appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID,
        'occurred_at' => NotificationsFixtures::now(),
        'created_at' => NotificationsFixtures::now(),
        'updated_at' => NotificationsFixtures::now(),
    ]);
});

it('writes the business and the subject as the int keys it was handed, never as uuids', function () {
    $attributes = $this->mapper->toAttributes($this->booking, NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY, NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY);

    expect($attributes['business_id'])->toBe(NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY)
        ->and($attributes['subject_id'])->toBe(NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY)
        ->and($attributes)->not->toContain(NotificationsFixtures::BUSINESS_ID)
        ->and($attributes)->not->toHaveKey('id');
});

it('keeps the uuids inside the payload snapshot, never the int keys', function () {
    $payload = $this->mapper->toAttributes($this->booking, NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY, NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY)['payload'];

    expect($payload['appointment']['id'])->toBe(NotificationsFixtures::APPOINTMENT_ID)
        ->and($payload['customer']['id'])->toBe(NotificationsFixtures::CUSTOMER_ID);
});

it('writes a schedule change about a staff member, keyed on the event uuid', function () {
    $event = NotificationEvent::record(
        id: NotificationsFixtures::EVENT_ID,
        businessId: NotificationsFixtures::BUSINESS_ID,
        payload: NotificationsFixtures::scheduleChangePayload(),
        occurredAt: NotificationsFixtures::now(),
    );

    $attributes = $this->mapper->toAttributes($event, NOTIFICATION_EVENT_MAPPER_BUSINESS_KEY, NOTIFICATION_EVENT_MAPPER_SUBJECT_KEY);

    expect($attributes['type'])->toBe(NotificationType::StaffScheduleChanged)
        ->and($attributes['subject_type'])->toBe(NotificationSubjectType::StaffMember)
        ->and($attributes['idempotency_key'])->toBe(NotificationsFixtures::EVENT_ID)
        ->and($attributes['payload'])->toBe([
            'staff_member' => ['id' => NotificationsFixtures::MEMBER_ID, 'name' => NotificationsFixtures::MEMBER_NAME],
        ]);
});
