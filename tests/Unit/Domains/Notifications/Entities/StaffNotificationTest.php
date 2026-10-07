<?php

declare(strict_types=1);

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\NotificationNotAddressedToReader;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

describe('creating a delivery', function () {
    beforeEach(function () {
        $this->notification = StaffNotification::create(
            id: NotificationsFixtures::NOTIFICATION_ID,
            businessId: NotificationsFixtures::BUSINESS_ID,
            eventId: NotificationsFixtures::EVENT_ID,
            recipientStaffMemberId: NotificationsFixtures::MEMBER_ID,
            collapseKey: NotificationsFixtures::BOOKING_COLLAPSE_KEY,
            now: NotificationsFixtures::now(),
        );
    });

    it('carries the uuids it was handed for itself, its business, its event and its recipient', function () {
        expect($this->notification->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($this->notification->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($this->notification->eventId)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($this->notification->recipientStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('carries the collapse key it was handed', function () {
        expect($this->notification->collapseKey)->toBe(NotificationsFixtures::BOOKING_COLLAPSE_KEY);
    });

    it('is created unread', function () {
        expect($this->notification->isUnread())->toBeTrue()
            ->and($this->notification->readAt())->toBeNull();
    });

    it('is created at the instant it was handed', function () {
        expect($this->notification->createdAt)->toEqual(NotificationsFixtures::now());
    });
});

describe('restoring a delivery', function () {
    it('keeps the read time it was stored with', function () {
        $notification = NotificationsFixtures::notification(readAt: NotificationsFixtures::READ_AT);

        expect($notification->isUnread())->toBeFalse()
            ->and($notification->readAt())->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT))
            ->and($notification->createdAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::CREATED_AT));
    });

    it('keeps its event and its collapse key', function () {
        $notification = NotificationsFixtures::notification(
            eventId: NotificationsFixtures::NEXT_EVENT_ID,
            collapseKey: NotificationsFixtures::SCHEDULE_CHANGE_COLLAPSE_KEY,
        );

        expect($notification->eventId)->toBe(NotificationsFixtures::NEXT_EVENT_ID)
            ->and($notification->collapseKey)->toBe(NotificationsFixtures::SCHEDULE_CHANGE_COLLAPSE_KEY);
    });

    it('restores an unread delivery as unread', function () {
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

    it('is addressed to the owner, never to the staff member a schedule change is about', function () {
        $notification = NotificationsFixtures::notification(
            recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID,
            collapseKey: NotificationsFixtures::SCHEDULE_CHANGE_COLLAPSE_KEY,
        );

        expect($notification->isAddressedTo(NotificationsFixtures::OWNER_MEMBER_ID))->toBeTrue()
            ->and($notification->isAddressedTo(NotificationsFixtures::MEMBER_ID))->toBeFalse();
    });
});

describe('marking a delivery as read', function () {
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

    it('refuses a stranger even when the delivery is already read', function () {
        $notification = NotificationsFixtures::notification(readAt: NotificationsFixtures::READ_AT);

        expect(fn () => $notification->markAsReadBy(NotificationsFixtures::OWNER_MEMBER_ID, NotificationsFixtures::now()))
            ->toThrow(NotificationNotAddressedToReader::class);
    });
});
