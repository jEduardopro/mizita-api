<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\ShowStaffNotificationInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Application\UseCases\ShowStaffNotification;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeNotificationFeed;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeNotificationReaders;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->readers = FakeNotificationReaders::ofTheTeam($this->journal);
    $this->feed = (new FakeNotificationFeed($this->journal))->add(
        NotificationsFixtures::BUSINESS_ID,
        NotificationsFixtures::record(),
        NotificationsFixtures::record(id: NotificationsFixtures::SECOND_NOTIFICATION_ID, readAt: NotificationsFixtures::READ_AT),
    );

    $this->show = fn (
        string $accountId,
        string $notificationId = NotificationsFixtures::NOTIFICATION_ID,
        FakeBusinessContext $business = new FakeBusinessContext,
    ): UseCaseResponse => (new ShowStaffNotification($this->feed, $this->readers, $business))
        ->handle(new ShowStaffNotificationInput($accountId, $notificationId));
});

describe('the recipient', function () {
    it('sees the notification under its uuids', function () {
        $data = ($this->show)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->value();

        expect($data)->toBeInstanceOf(StaffNotificationData::class)
            ->and($data->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($data->type)->toBe('appointment_booked')
            ->and($data->recipient->id)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($data->details['appointment']['id'])->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($data->details['customer']['id'])->toBe(NotificationsFixtures::CUSTOMER_ID)
            ->and($data->readAt)->toBeNull();
    });

    it('may mark it as read while unread', function () {
        expect(($this->show)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->value()->canMarkAsRead)->toBeTrue();
    });

    it('may not mark it again once read', function () {
        $data = ($this->show)(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::SECOND_NOTIFICATION_ID)->value();

        expect($data->canMarkAsRead)->toBeFalse()
            ->and($data->readAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::READ_AT));
    });
});

describe('the owner', function () {
    it('sees a notification addressed to a team member', function () {
        $data = ($this->show)(NotificationsFixtures::OWNER_ACCOUNT_ID)->value();

        expect($data->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($data->recipient->id)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('may not mark a team member notification as read', function () {
        expect(($this->show)(NotificationsFixtures::OWNER_ACCOUNT_ID)->value()->canMarkAsRead)->toBeFalse();
    });
});

describe('another team member', function () {
    it('is told the notification does not exist, never that it is forbidden', function () {
        $response = ($this->show)(NotificationsFixtures::OTHER_MEMBER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });
});

describe('a notification that is not there', function () {
    it('answers not found for an unknown notification', function () {
        $response = ($this->show)(NotificationsFixtures::OWNER_ACCOUNT_ID, NotificationsFixtures::UNKNOWN_NOTIFICATION_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers not found for a notification identifier that is no uuid, before reading anything', function (string $notificationId) {
        $response = ($this->show)(NotificationsFixtures::OWNER_ACCOUNT_ID, $notificationId);

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
        ($this->show)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->feed->finds)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'notificationId' => NotificationsFixtures::NOTIFICATION_ID,
        ]])->and($this->readers->lookups)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'accountId' => NotificationsFixtures::MEMBER_ACCOUNT_ID,
        ]]);
    });

    it('answers not found for a notification of another business, even to its owner', function () {
        $this->readers->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID),
        );

        $response = ($this->show)(
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            NotificationsFixtures::NOTIFICATION_ID,
            new FakeBusinessContext(NotificationsFixtures::OTHER_BUSINESS_ID),
        );

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_notification_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('refuses an account that is no member of the business and reads nothing', function () {
        $response = ($this->show)(NotificationsFixtures::STRANGER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->feed->finds)->toBe([]);
    });
});
