<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\MarkStaffNotificationAsReadInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Application\UseCases\MarkStaffNotificationAsRead;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeNotificationFeed;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeNotificationReaders;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->readers = FakeNotificationReaders::ofTheTeam($this->journal);
    $this->notifications = (new FakeStaffNotificationRepository($this->journal))->store(
        NotificationsFixtures::notification(),
        NotificationsFixtures::notification(id: NotificationsFixtures::SECOND_NOTIFICATION_ID, readAt: NotificationsFixtures::READ_AT),
        NotificationsFixtures::notification(id: NotificationsFixtures::THIRD_NOTIFICATION_ID, recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID),
    );
    $this->feed = (new FakeNotificationFeed($this->journal, $this->notifications))->add(
        NotificationsFixtures::BUSINESS_ID,
        NotificationsFixtures::record(),
        NotificationsFixtures::record(id: NotificationsFixtures::SECOND_NOTIFICATION_ID, readAt: NotificationsFixtures::READ_AT),
        NotificationsFixtures::record(id: NotificationsFixtures::THIRD_NOTIFICATION_ID, recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID),
    );
    $this->clock = new FakeClock(NotificationsFixtures::now());

    $this->mark = fn (
        string $accountId,
        string $notificationId = NotificationsFixtures::NOTIFICATION_ID,
        FakeBusinessContext $business = new FakeBusinessContext,
    ): UseCaseResponse => (new MarkStaffNotificationAsRead($this->notifications, $this->feed, $this->readers, $business, $this->clock))
        ->handle(new MarkStaffNotificationAsReadInput($accountId, $notificationId));
});

describe('the recipient', function () {
    it('stores the notification as read at the instant of the clock', function () {
        ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->notifications->saved)->toHaveCount(1)
            ->and($this->notifications->saved[0]->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($this->notifications->stored(NotificationsFixtures::NOTIFICATION_ID)?->readAt())->toEqual(NotificationsFixtures::now());
    });

    it('hands back the notification as read, under its uuid, with nothing left to mark', function () {
        $data = ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->value();

        expect($data)->toBeInstanceOf(StaffNotificationData::class)
            ->and($data->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($data->recipient->id)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($data->readAt)->toEqual(NotificationsFixtures::now())
            ->and($data->canMarkAsRead)->toBeFalse();
    });

    it('reads the notification back only after storing it', function () {
        ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->journal->entries)->toBe([
            'readers.readerFor',
            'notifications.findForBusiness',
            'notifications.save',
            'feed.find',
        ]);
    });

    it('keeps the first read time when marking an already read notification', function () {
        $this->clock->advance('PT2H');

        $data = ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::SECOND_NOTIFICATION_ID)->value();

        expect($data->readAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT))
            ->and($this->notifications->stored(NotificationsFixtures::SECOND_NOTIFICATION_ID)?->readAt())
            ->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT));
    });

    it('treats marking an already read notification as a success', function () {
        expect(($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::SECOND_NOTIFICATION_ID)->succeeded())
            ->toBeTrue();
    });
});

describe('the owner', function () {
    it('is forbidden from marking a team member notification as read', function () {
        $response = ($this->mark)(NotificationsFixtures::OWNER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notification_not_addressed_to_reader')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('leaves a team member notification unread and unsaved', function () {
        ($this->mark)(NotificationsFixtures::OWNER_ACCOUNT_ID);

        expect($this->notifications->saved)->toBe([])
            ->and($this->notifications->stored(NotificationsFixtures::NOTIFICATION_ID)?->isUnread())->toBeTrue();
    });

    it('marks a notification addressed to the owner', function () {
        $data = ($this->mark)(NotificationsFixtures::OWNER_ACCOUNT_ID, NotificationsFixtures::THIRD_NOTIFICATION_ID)->value();

        expect($data->id)->toBe(NotificationsFixtures::THIRD_NOTIFICATION_ID)
            ->and($data->readAt)->toEqual(NotificationsFixtures::now())
            ->and($this->notifications->saved)->toHaveCount(1);
    });
});

describe('another team member', function () {
    it('is told the notification does not exist, never that it is forbidden', function () {
        $response = ($this->mark)(NotificationsFixtures::OTHER_MEMBER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('leaves the notification unread and unsaved', function () {
        ($this->mark)(NotificationsFixtures::OTHER_MEMBER_ACCOUNT_ID);

        expect($this->notifications->saved)->toBe([])
            ->and($this->notifications->stored(NotificationsFixtures::NOTIFICATION_ID)?->isUnread())->toBeTrue();
    });
});

describe('a notification that is not there', function () {
    it('answers not found for an unknown notification and saves nothing', function () {
        $response = ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::UNKNOWN_NOTIFICATION_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->notifications->saved)->toBe([]);
    });

    it('answers not found for a notification identifier that is no uuid, before reading anything', function (string $notificationId) {
        $response = ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID, $notificationId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([]);
    })->with([
        'empty' => '',
        'a sequential int' => '1',
        'a word' => 'not-a-uuid',
    ]);
});

describe('tenant isolation', function () {
    it('looks the notification up in the business in context', function () {
        ($this->mark)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->notifications->lookups)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'id' => NotificationsFixtures::NOTIFICATION_ID,
        ]])->and($this->feed->finds[0]['businessId'])->toBe(NotificationsFixtures::BUSINESS_ID)
            ->and($this->readers->lookups[0]['businessId'])->toBe(NotificationsFixtures::BUSINESS_ID);
    });

    it('answers not found for the recipient notification seen from another business, and saves nothing', function () {
        $this->readers->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
            NotificationReader::member(NotificationsFixtures::MEMBER_ID),
        );

        $response = ($this->mark)(
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
            NotificationsFixtures::NOTIFICATION_ID,
            new FakeBusinessContext(NotificationsFixtures::OTHER_BUSINESS_ID),
        );

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($this->notifications->saved)->toBe([])
            ->and($this->notifications->stored(NotificationsFixtures::NOTIFICATION_ID)?->isUnread())->toBeTrue();
    });

    it('refuses an account that is no member of the business and touches nothing', function () {
        $response = ($this->mark)(NotificationsFixtures::STRANGER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->journal->entries)->toBe(['readers.readerFor']);
    });
});
