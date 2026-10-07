<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('copies a booking notification, its recipient and its snapshot under their uuids', function () {
    $data = StaffNotificationData::forReader(
        NotificationsFixtures::record(),
        NotificationReader::member(NotificationsFixtures::MEMBER_ID),
    );

    expect($data->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
        ->and($data->type)->toBe('appointment_booked')
        ->and($data->readAt)->toBeNull()
        ->and($data->createdAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::CREATED_AT))
        ->and($data->recipient->id)->toBe(NotificationsFixtures::MEMBER_ID)
        ->and($data->recipient->name)->toBe(NotificationsFixtures::MEMBER_NAME)
        ->and($data->details)->toBe(NotificationsFixtures::bookingSnapshot());
});

it('copies the staff member a schedule change is about under their uuid', function () {
    $data = StaffNotificationData::forReader(
        NotificationsFixtures::scheduleChangeRecord(),
        NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID),
    );

    expect($data->type)->toBe('staff_schedule_changed')
        ->and($data->recipient->id)->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
        ->and($data->recipient->name)->toBe(NotificationsFixtures::OWNER_NAME)
        ->and($data->details)->toBe([
            'staff_member' => [
                'id' => NotificationsFixtures::MEMBER_ID,
                'name' => NotificationsFixtures::MEMBER_NAME,
            ],
        ])
        ->and($data->canMarkAsRead)->toBeTrue();
});

it('tells the reader whether it may mark the notification as read', function (?string $readAt, NotificationReader $reader, bool $canMarkAsRead) {
    expect(StaffNotificationData::forReader(NotificationsFixtures::record(readAt: $readAt), $reader)->canMarkAsRead)
        ->toBe($canMarkAsRead);
})->with([
    'its recipient, while unread' => [null, NotificationReader::member(NotificationsFixtures::MEMBER_ID), true],
    'its recipient, once read' => [NotificationsFixtures::READ_AT, NotificationReader::member(NotificationsFixtures::MEMBER_ID), false],
    'the owner, on a team member notification' => [null, NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID), false],
]);

it('keeps the read time of a read notification', function () {
    $data = StaffNotificationData::forReader(
        NotificationsFixtures::record(readAt: NotificationsFixtures::READ_AT),
        NotificationReader::member(NotificationsFixtures::MEMBER_ID),
    );

    expect($data->readAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT));
});
