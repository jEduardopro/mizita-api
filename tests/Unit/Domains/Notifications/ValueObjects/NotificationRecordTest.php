<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationType;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

describe('what it is about', function () {
    it('takes its type from its payload', function () {
        expect(NotificationsFixtures::record()->type())->toBe(NotificationType::AppointmentBooked)
            ->and(NotificationsFixtures::scheduleChangeRecord()->type())->toBe(NotificationType::StaffScheduleChanged);
    });

    it('details a booking as its appointment and customer snapshot', function () {
        expect(NotificationsFixtures::record()->details())->toBe(NotificationsFixtures::bookingSnapshot());
    });

    it('details a schedule change as the staff member it is about', function () {
        expect(NotificationsFixtures::scheduleChangeRecord()->details())->toBe([
            'staff_member' => [
                'id' => NotificationsFixtures::MEMBER_ID,
                'name' => NotificationsFixtures::MEMBER_NAME,
            ],
        ]);
    });
});

describe('visibility', function () {
    it('is visible to its recipient', function () {
        expect(NotificationsFixtures::record()->isVisibleTo(NotificationReader::member(NotificationsFixtures::MEMBER_ID)))
            ->toBeTrue();
    });

    it('is visible to the owner', function () {
        expect(NotificationsFixtures::record()->isVisibleTo(NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID)))
            ->toBeTrue();
    });

    it('is invisible to another team member', function () {
        expect(NotificationsFixtures::record()->isVisibleTo(NotificationReader::member(NotificationsFixtures::OTHER_MEMBER_ID)))
            ->toBeFalse();
    });

    it('is invisible to the staff member a schedule change is about', function () {
        expect(NotificationsFixtures::scheduleChangeRecord()->isVisibleTo(NotificationReader::member(NotificationsFixtures::MEMBER_ID)))
            ->toBeFalse();
    });
});

describe('who may mark it as read', function () {
    it('lets its recipient mark it while unread', function () {
        expect(NotificationsFixtures::record()->canBeMarkedAsReadBy(NotificationReader::member(NotificationsFixtures::MEMBER_ID)))
            ->toBeTrue();
    });

    it('lets nobody mark it once read, its recipient included', function () {
        expect(NotificationsFixtures::record(readAt: NotificationsFixtures::READ_AT)
            ->canBeMarkedAsReadBy(NotificationReader::member(NotificationsFixtures::MEMBER_ID)))
            ->toBeFalse();
    });

    it('never lets the owner mark a notification addressed to a team member', function () {
        expect(NotificationsFixtures::record()->canBeMarkedAsReadBy(NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID)))
            ->toBeFalse();
    });

    it('lets the owner mark a notification addressed to the owner', function () {
        $record = NotificationsFixtures::record(recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID);

        expect($record->canBeMarkedAsReadBy(NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID)))->toBeTrue();
    });

    it('never lets another team member mark it', function () {
        expect(NotificationsFixtures::record()->canBeMarkedAsReadBy(NotificationReader::member(NotificationsFixtures::OTHER_MEMBER_ID)))
            ->toBeFalse();
    });
});
