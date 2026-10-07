<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Application\UseCases\NotifyStaffScheduleChanged;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBusinessOwners;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

const SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b3';

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->owners = (new FakeBusinessOwners($this->journal))
        ->ownedBy(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID)
        ->ownedBy(NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID);
    $this->notifications = new FakeStaffNotificationRepository($this->journal);
    $this->clock = new FakeClock(NotificationsFixtures::now());

    $this->useCase = new NotifyStaffScheduleChanged(
        $this->owners,
        $this->notifications,
        new FixedIdGenerator(NotificationsFixtures::NEW_NOTIFICATION_ID, NotificationsFixtures::NEXT_NOTIFICATION_ID),
        $this->clock,
    );

    $this->notify = fn (
        string $staffMemberId = NotificationsFixtures::MEMBER_ID,
        string $businessId = NotificationsFixtures::BUSINESS_ID,
    ) => $this->useCase->handle(new NotifyStaffScheduleChangedInput($businessId, $staffMemberId));
});

describe('a team member changing their own schedule', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('notifies the owner of the business once', function () {
        ($this->notify)();

        expect($this->notifications->addedOrRefreshed)->toHaveCount(1)
            ->and($this->notifications->addedOrRefreshed[0]->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });

    it('is about the staff member whose schedule changed, and about no appointment', function () {
        ($this->notify)();

        expect($this->notifications->addedOrRefreshed[0]->subjectStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($this->notifications->addedOrRefreshed[0]->appointmentId)->toBeNull();
    });

    it('identifies the notification by the uuid it generated', function () {
        ($this->notify)();

        expect($this->notifications->addedOrRefreshed[0]->id)->toBe(NotificationsFixtures::NEW_NOTIFICATION_ID);
    });

    it('files the notification as a schedule change, unread, at the instant of the clock', function () {
        ($this->notify)();

        $notification = $this->notifications->addedOrRefreshed[0];

        expect($notification->type)->toBe(NotificationType::StaffScheduleChanged)
            ->and($notification->isUnread())->toBeTrue()
            ->and($notification->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('files the notification under the business the change happened in, addressed to that business owner', function (string $businessId, string $owner) {
        ($this->notify)(NotificationsFixtures::MEMBER_ID, $businessId);

        expect($this->owners->lookups)->toBe([$businessId])
            ->and($this->notifications->addedOrRefreshed[0]->businessId)->toBe($businessId)
            ->and($this->notifications->addedOrRefreshed[0]->recipientStaffMemberId)->toBe($owner);
    })->with([
        'the first business' => [NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID],
        'another business' => [NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID],
    ]);

    it('writes through the door that refreshes an unread notification, never through addOnce or a plain save', function () {
        ($this->notify)();

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf', 'notifications.addOrRefreshUnread'])
            ->and($this->notifications->added)->toBe([])
            ->and($this->notifications->saved)->toBe([]);
    });
});

describe('repeated schedule changes', function () {
    it('hands every change to the refreshing door with a fresh uuid and the instant it happened', function () {
        ($this->notify)();
        $this->clock->advance('PT10M');
        ($this->notify)();

        expect($this->journal->entries)->toBe([
            'owners.ownerStaffMemberIdOf',
            'notifications.addOrRefreshUnread',
            'owners.ownerStaffMemberIdOf',
            'notifications.addOrRefreshUnread',
        ])
            ->and($this->notifications->addedOrRefreshed[1]->id)->toBe(NotificationsFixtures::NEXT_NOTIFICATION_ID)
            ->and($this->notifications->addedOrRefreshed[1]->createdAt)->toEqual(NotificationsFixtures::now()->modify('+10 minutes'));
    });

    it('names each staff member as the subject of their own change', function () {
        ($this->notify)(NotificationsFixtures::MEMBER_ID);
        ($this->notify)(NotificationsFixtures::OTHER_MEMBER_ID);

        expect(array_map(
            static fn ($notification) => $notification->subjectStaffMemberId,
            $this->notifications->addedOrRefreshed,
        ))->toBe([NotificationsFixtures::MEMBER_ID, NotificationsFixtures::OTHER_MEMBER_ID]);
    });
});

describe('a business with no owner', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)(NotificationsFixtures::MEMBER_ID, SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('writes no notification', function () {
        ($this->notify)(NotificationsFixtures::MEMBER_ID, SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID);

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf'])
            ->and($this->notifications->all())->toBe([]);
    });
});

describe('the owner changing their own schedule', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)(NotificationsFixtures::OWNER_MEMBER_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('writes no notification to the owner about themselves', function () {
        ($this->notify)(NotificationsFixtures::OWNER_MEMBER_ID);

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf'])
            ->and($this->notifications->all())->toBe([]);
    });

    it('still notifies the owner of a business when the owner of another business changes their schedule there as staff', function () {
        ($this->notify)(NotificationsFixtures::OTHER_MEMBER_ID, NotificationsFixtures::BUSINESS_ID);

        expect($this->notifications->addedOrRefreshed)->toHaveCount(1)
            ->and($this->notifications->addedOrRefreshed[0]->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });
});

describe('an identifier that is no uuid', function () {
    it('answers not found', function (string $staffMemberId, string $businessId) {
        $response = ($this->notify)($staffMemberId, $businessId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notified_staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'an empty staff member' => ['', NotificationsFixtures::BUSINESS_ID],
        'a whitespace only staff member' => ['   ', NotificationsFixtures::BUSINESS_ID],
        'a sequential int staff member' => ['42', NotificationsFixtures::BUSINESS_ID],
        'a sequential int business' => [NotificationsFixtures::MEMBER_ID, '7'],
    ]);

    it('refuses before it looks the owner up or writes anything', function () {
        ($this->notify)('42');

        expect($this->journal->entries)->toBe([])
            ->and($this->notifications->all())->toBe([]);
    });
});
