<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\StaffNotificationMapper;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

const STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY = 42;

const STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY = 17;

const STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY = 99;

const STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY = 23;

function mappedStaffMemberRow(int $key, string $uuid): StaffMemberModel
{
    $staffMember = new StaffMemberModel;
    $staffMember->setRawAttributes(['id' => $key, 'uuid' => $uuid], true);

    return $staffMember;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function mappedStaffNotificationRow(array $overrides = [], bool $withAppointment = true, bool $withSubject = false): StaffNotificationModel
{
    $model = new StaffNotificationModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => NotificationsFixtures::NOTIFICATION_ID,
        'business_id' => STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
        'recipient_staff_member_id' => STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        'type' => 'appointment_booked',
        'appointment_id' => $withAppointment ? STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY : null,
        'subject_staff_member_id' => $withSubject ? STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY : null,
        'read_at' => null,
        'created_at' => NotificationsFixtures::CREATED_AT,
        ...$overrides,
    ], true);

    $model->setRelation('recipient', mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY, NotificationsFixtures::MEMBER_ID));

    $appointment = null;

    if ($withAppointment) {
        $appointment = new AppointmentModel;
        $appointment->setRawAttributes(['id' => STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY, 'uuid' => NotificationsFixtures::APPOINTMENT_ID], true);
    }

    $model->setRelation('appointment', $appointment);
    $model->setRelation('subject', $withSubject
        ? mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY, NotificationsFixtures::OTHER_MEMBER_ID)
        : null);

    return $model;
}

function mappedScheduleChangeRow(): StaffNotificationModel
{
    return mappedStaffNotificationRow(['type' => 'staff_schedule_changed'], withAppointment: false, withSubject: true);
}

beforeEach(function () {
    $this->mapper = new StaffNotificationMapper;
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        expect($this->mapper->toAttributes(
            NotificationsFixtures::notification(),
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
            STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY,
            null,
        ))->toEqual([
            'uuid' => NotificationsFixtures::NOTIFICATION_ID,
            'business_id' => STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            'recipient_staff_member_id' => STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
            'type' => NotificationType::AppointmentBooked,
            'appointment_id' => STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY,
            'subject_staff_member_id' => null,
            'read_at' => null,
            'created_at' => new DateTimeImmutable(NotificationsFixtures::CREATED_AT),
        ]);
    });

    it('writes the business, the recipient and the appointment as the int keys it was handed, never as uuids', function () {
        $attributes = $this->mapper->toAttributes(
            NotificationsFixtures::notification(),
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
            STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY,
            null,
        );

        expect($attributes['business_id'])->toBeInt()
            ->and($attributes['recipient_staff_member_id'])->toBeInt()
            ->and($attributes['appointment_id'])->toBeInt()
            ->and($attributes)->not->toContain(NotificationsFixtures::BUSINESS_ID)
            ->and($attributes)->not->toContain(NotificationsFixtures::MEMBER_ID)
            ->and($attributes)->not->toContain(NotificationsFixtures::APPOINTMENT_ID)
            ->and($attributes)->not->toHaveKey('id');
    });

    it('writes the read time once the notification is read', function () {
        $notification = NotificationsFixtures::notification();
        $notification->markAsReadBy(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::now());

        $attributes = $this->mapper->toAttributes($notification, STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY, STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY, null, null);

        expect($attributes['read_at'])->toEqual(NotificationsFixtures::now())
            ->and($attributes['appointment_id'])->toBeNull();
    });

    it('writes a schedule change about its subject as the int key it was handed, with no appointment', function () {
        $attributes = $this->mapper->toAttributes(
            NotificationsFixtures::scheduleChangeNotification(),
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
            null,
            STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY,
        );

        expect($attributes['type'])->toBe(NotificationType::StaffScheduleChanged)
            ->and($attributes['subject_staff_member_id'])->toBe(STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY)
            ->and($attributes['appointment_id'])->toBeNull()
            ->and($attributes)->not->toContain(NotificationsFixtures::MEMBER_ID)
            ->and($attributes)->not->toContain(NotificationsFixtures::OWNER_MEMBER_ID);
    });
});

describe('reading a row', function () {
    it('rehydrates the notification under its uuids', function () {
        $notification = $this->mapper->toEntity(mappedStaffNotificationRow(), NotificationsFixtures::BUSINESS_ID);

        expect($notification)->toBeInstanceOf(StaffNotification::class)
            ->and($notification->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($notification->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($notification->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($notification->appointmentId)->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($notification->subjectStaffMemberId)->toBeNull()
            ->and($notification->type)->toBe(NotificationType::AppointmentBooked)
            ->and($notification->isUnread())->toBeTrue()
            ->and($notification->createdAt->getTimestamp())->toBe((new DateTimeImmutable(NotificationsFixtures::CREATED_AT))->getTimestamp());
    });

    it('never lets the int keys of the row become identities of the entity', function () {
        $notification = $this->mapper->toEntity(mappedStaffNotificationRow(), NotificationsFixtures::BUSINESS_ID);

        expect($notification->id)->not->toBe('7')
            ->and($notification->recipientStaffMemberId)->not->toBe((string) STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY)
            ->and($notification->appointmentId)->not->toBe((string) STAFF_NOTIFICATION_MAPPER_APPOINTMENT_KEY);
    });

    it('reads the stored read time', function () {
        $notification = $this->mapper->toEntity(
            mappedStaffNotificationRow(['read_at' => NotificationsFixtures::READ_AT]),
            NotificationsFixtures::BUSINESS_ID,
        );

        expect($notification->isUnread())->toBeFalse()
            ->and($notification->readAt()?->getTimestamp())->toBe((new DateTimeImmutable(NotificationsFixtures::READ_AT))->getTimestamp());
    });

    it('reads a notification about no appointment', function () {
        $notification = $this->mapper->toEntity(mappedStaffNotificationRow(withAppointment: false), NotificationsFixtures::BUSINESS_ID);

        expect($notification->appointmentId)->toBeNull();
    });

    it('reads the subject of a schedule change under its uuid, never its int key', function () {
        $notification = $this->mapper->toEntity(mappedScheduleChangeRow(), NotificationsFixtures::BUSINESS_ID);

        expect($notification->type)->toBe(NotificationType::StaffScheduleChanged)
            ->and($notification->subjectStaffMemberId)->toBe(NotificationsFixtures::OTHER_MEMBER_ID)
            ->and($notification->subjectStaffMemberId)->not->toBe((string) STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY)
            ->and($notification->appointmentId)->toBeNull();
    });
});

it('survives a full round trip of a schedule change without losing its subject', function () {
    $notification = NotificationsFixtures::scheduleChangeNotification();

    $attributes = $this->mapper->toAttributes(
        $notification,
        STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
        STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        null,
        STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY,
    );

    $row = new StaffNotificationModel;
    $row->setRawAttributes([
        ...$attributes,
        'type' => $attributes['type']->value,
        'created_at' => $attributes['created_at']->format(DATE_ATOM),
    ], true);
    $row->setRelation('recipient', mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY, $notification->recipientStaffMemberId));
    $row->setRelation('appointment', null);
    $row->setRelation('subject', mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_SUBJECT_KEY, (string) $notification->subjectStaffMemberId));

    expect($this->mapper->toEntity($row, NotificationsFixtures::BUSINESS_ID))->toEqual($notification);
});
