<?php

declare(strict_types=1);

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\NotificationNotAddressedToReader;
use App\Domains\Notifications\ValueObjects\NotificationType;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

describe('creating a notification', function () {
    beforeEach(function () {
        $this->notification = StaffNotification::create(
            id: NotificationsFixtures::NOTIFICATION_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            recipientStaffMemberId: NotificationsFixtures::MEMBER_ID,
            type: NotificationType::AppointmentBooked,
            appointmentId: NotificationsFixtures::APPOINTMENT_ID,
            now: NotificationsFixtures::now(),
        );
    });

    it('carries the uuids it was handed for itself, its business, its recipient and its appointment', function () {
        expect($this->notification->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($this->notification->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($this->notification->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($this->notification->appointmentId)->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($this->notification->type)->toBe(NotificationType::AppointmentBooked);
    });

    it('is created unread', function () {
        expect($this->notification->isUnread())->toBeTrue()
            ->and($this->notification->readAt())->toBeNull();
    });

    it('is created at the instant it was handed', function () {
        expect($this->notification->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('accepts a notification about no appointment', function () {
        $notification = StaffNotification::create(
            id: NotificationsFixtures::NOTIFICATION_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            recipientStaffMemberId: NotificationsFixtures::MEMBER_ID,
            type: NotificationType::AppointmentBooked,
            appointmentId: null,
            now: NotificationsFixtures::now(),
        );

        expect($notification->appointmentId)->toBeNull();
    });
});

describe('restoring a notification', function () {
    it('keeps the read time it was stored with', function () {
        $notification = NotificationsFixtures::notification(readAt: NotificationsFixtures::READ_AT);

        expect($notification->isUnread())->toBeFalse()
            ->and($notification->readAt())->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT))
            ->and($notification->createdAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::CREATED_AT));
    });

    it('restores an unread notification as unread', function () {
        expect(NotificationsFixtures::notification()->isUnread())->toBeTrue();
    });
});

describe('knowing who it is addressed to', function () {
    it('is addressed to its recipient', function () {
        expect(NotificationsFixtures::notification()->isAddressedTo(NotificationsFixtures::MEMBER_ID))->toBeTrue();
    });

    it('is addressed to nobody else, the owner included', function (string $staffMemberId) {
        expect(NotificationsFixtures::notification()->isAddressedTo($staffMemberId))->toBeFalse();
    })->with([
        'the owner' => NotificationsFixtures::OWNER_MEMBER_ID,
        'another team member' => NotificationsFixtures::OTHER_MEMBER_ID,
        'an empty identity' => '',
    ]);
});

describe('marking a notification as read', function () {
    it('records the instant its recipient read it', function () {
        $notification = NotificationsFixtures::notification();

        $notification->markAsReadBy(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::now());

        expect($notification->isUnread())->toBeFalse()
            ->and($notification->readAt())->toEqual(NotificationsFixtures::now());
    });

    it('keeps the first read time when read again', function () {
        $notification = NotificationsFixtures::notification(readAt: NotificationsFixtures::READ_AT);

        $notification->markAsReadBy(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::now());

        expect($notification->readAt())->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT));
    });

    it('refuses anybody but its recipient', function (string $staffMemberId) {
        $notification = NotificationsFixtures::notification();

        expect(fn () => $notification->markAsReadBy($staffMemberId, NotificationsFixtures::now()))
            ->toThrow(NotificationNotAddressedToReader::class);
    })->with([
        'the owner' => NotificationsFixtures::OWNER_MEMBER_ID,
        'another team member' => NotificationsFixtures::OTHER_MEMBER_ID,
    ]);

    it('stays unread after a refusal', function () {
        $notification = NotificationsFixtures::notification();

        try {
            $notification->markAsReadBy(NotificationsFixtures::OWNER_MEMBER_ID, NotificationsFixtures::now());
        } catch (NotificationNotAddressedToReader) {
        }

        expect($notification->isUnread())->toBeTrue()
            ->and($notification->readAt())->toBeNull();
    });

    it('refuses a stranger even when the notification is already read', function () {
        $notification = NotificationsFixtures::notification(readAt: NotificationsFixtures::READ_AT);

        expect(fn () => $notification->markAsReadBy(NotificationsFixtures::OWNER_MEMBER_ID, NotificationsFixtures::now()))
            ->toThrow(NotificationNotAddressedToReader::class);
    });
});
