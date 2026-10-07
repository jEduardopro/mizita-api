<?php

declare(strict_types=1);

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Infrastructure\Eloquent\Mappers\StaffNotificationMapper;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\NotificationEventModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

const STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY = 42;

const STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY = 17;

const STAFF_NOTIFICATION_MAPPER_EVENT_KEY = 99;

function mappedStaffMemberRow(int $key, string $uuid): StaffMemberModel
{
    $staffMember = new StaffMemberModel;
    $staffMember->setRawAttributes(['id' => $key, 'uuid' => $uuid], true);

    return $staffMember;
}

function mappedNotificationEventRow(int $key, string $uuid): NotificationEventModel
{
    $event = new NotificationEventModel;
    $event->setRawAttributes(['id' => $key, 'uuid' => $uuid], true);

    return $event;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function mappedStaffNotificationRow(array $overrides = []): StaffNotificationModel
{
    $model = new StaffNotificationModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => NotificationsFixtures::NOTIFICATION_ID,
        'business_id' => STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
        'notification_event_id' => STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
        'recipient_staff_member_id' => STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        'collapse_key' => NotificationsFixtures::BOOKING_COLLAPSE_KEY,
        'read_at' => null,
        'created_at' => NotificationsFixtures::CREATED_AT,
        ...$overrides,
    ], true);

    $model->setRelation('recipient', mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY, NotificationsFixtures::MEMBER_ID));
    $model->setRelation('event', mappedNotificationEventRow(STAFF_NOTIFICATION_MAPPER_EVENT_KEY, NotificationsFixtures::EVENT_ID));

    return $model;
}

beforeEach(function () {
    $this->mapper = new StaffNotificationMapper;
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        expect($this->mapper->toAttributes(
            NotificationsFixtures::notification(),
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        ))->toEqual([
            'uuid' => NotificationsFixtures::NOTIFICATION_ID,
            'business_id' => STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            'notification_event_id' => STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
            'recipient_staff_member_id' => STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
            'collapse_key' => NotificationsFixtures::BOOKING_COLLAPSE_KEY,
            'read_at' => null,
            'created_at' => new DateTimeImmutable(NotificationsFixtures::CREATED_AT),
        ]);
    });

    it('writes the business, the event and the recipient as the int keys it was handed, never as uuids', function () {
        $attributes = $this->mapper->toAttributes(
            NotificationsFixtures::notification(),
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        );

        expect($attributes['business_id'])->toBe(STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY)
            ->and($attributes['notification_event_id'])->toBe(STAFF_NOTIFICATION_MAPPER_EVENT_KEY)
            ->and($attributes['recipient_staff_member_id'])->toBe(STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY)
            ->and($attributes)->not->toContain(NotificationsFixtures::BUSINESS_ID)
            ->and($attributes)->not->toContain(NotificationsFixtures::EVENT_ID)
            ->and($attributes)->not->toContain(NotificationsFixtures::MEMBER_ID)
            ->and($attributes)->not->toHaveKey('id');
    });

    it('writes the read time once the delivery is read', function () {
        $notification = NotificationsFixtures::notification();
        $notification->markAsReadBy(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::now());

        $attributes = $this->mapper->toAttributes(
            $notification,
            STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
            STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
            STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
        );

        expect($attributes['read_at'])->toEqual(NotificationsFixtures::now());
    });
});

describe('reading a row', function () {
    it('rehydrates the delivery under its uuids', function () {
        $notification = $this->mapper->toEntity(mappedStaffNotificationRow(), NotificationsFixtures::BUSINESS_ID);

        expect($notification)->toBeInstanceOf(StaffNotification::class)
            ->and($notification->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($notification->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($notification->eventId)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($notification->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($notification->collapseKey)->toBe(NotificationsFixtures::BOOKING_COLLAPSE_KEY)
            ->and($notification->isUnread())->toBeTrue()
            ->and($notification->createdAt->getTimestamp())->toBe((new DateTimeImmutable(NotificationsFixtures::CREATED_AT))->getTimestamp());
    });

    it('never lets the int keys of the row become identities of the entity', function () {
        $notification = $this->mapper->toEntity(mappedStaffNotificationRow(), NotificationsFixtures::BUSINESS_ID);

        expect($notification->id)->not->toBe('7')
            ->and($notification->eventId)->not->toBe((string) STAFF_NOTIFICATION_MAPPER_EVENT_KEY)
            ->and($notification->recipientStaffMemberId)->not->toBe((string) STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY);
    });

    it('reads the stored read time', function () {
        $notification = $this->mapper->toEntity(
            mappedStaffNotificationRow(['read_at' => NotificationsFixtures::READ_AT]),
            NotificationsFixtures::BUSINESS_ID,
        );

        expect($notification->isUnread())->toBeFalse()
            ->and($notification->readAt()?->getTimestamp())->toBe((new DateTimeImmutable(NotificationsFixtures::READ_AT))->getTimestamp());
    });
});

it('survives a full round trip', function () {
    $notification = NotificationsFixtures::notification(
        recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID,
        collapseKey: NotificationsFixtures::SCHEDULE_CHANGE_COLLAPSE_KEY,
        readAt: NotificationsFixtures::READ_AT,
    );

    $attributes = $this->mapper->toAttributes(
        $notification,
        STAFF_NOTIFICATION_MAPPER_BUSINESS_KEY,
        STAFF_NOTIFICATION_MAPPER_EVENT_KEY,
        STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY,
    );

    $row = new StaffNotificationModel;
    $row->setRawAttributes([
        ...$attributes,
        'read_at' => $attributes['read_at']->format(DATE_ATOM),
        'created_at' => $attributes['created_at']->format(DATE_ATOM),
    ], true);
    $row->setRelation('recipient', mappedStaffMemberRow(STAFF_NOTIFICATION_MAPPER_RECIPIENT_KEY, $notification->recipientStaffMemberId));
    $row->setRelation('event', mappedNotificationEventRow(STAFF_NOTIFICATION_MAPPER_EVENT_KEY, $notification->eventId));

    expect($this->mapper->toEntity($row, NotificationsFixtures::BUSINESS_ID))->toEqual($notification);
});
