<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\CountUnreadStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\UnreadNotificationCountData;
use App\Domains\Notifications\Application\UseCases\CountUnreadStaffNotifications;
use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\Exceptions\TeamNotificationsRequireOwner;
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
        NotificationsFixtures::record(id: NotificationsFixtures::SECOND_NOTIFICATION_ID),
        NotificationsFixtures::record(id: NotificationsFixtures::THIRD_NOTIFICATION_ID, readAt: NotificationsFixtures::READ_AT),
        NotificationsFixtures::record(
            id: NotificationsFixtures::UNKNOWN_NOTIFICATION_ID,
            recipientStaffMemberId: NotificationsFixtures::OTHER_MEMBER_ID,
        ),
    );

    $this->count = fn (
        string $accountId,
        array $payload = [],
        FakeBusinessContext $business = new FakeBusinessContext,
    ): UseCaseResponse => (new CountUnreadStaffNotifications($this->feed, $this->readers, $business))
        ->handle(CountUnreadStaffNotificationsInput::fromRequest($payload, $accountId));
});

describe('the caller own notifications', function () {
    it('counts the unread notifications addressed to the caller when no scope is given', function () {
        $data = ($this->count)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->value();

        expect($data)->toBeInstanceOf(UnreadNotificationCountData::class)
            ->and($data->count)->toBe(2);
    });

    it('counts the same unread notifications when asked for its own explicitly', function () {
        $data = ($this->count)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['scope' => 'mine'])->value();

        expect($data->count)->toBe(2);
    });

    it('asks the feed for the caller own staff member in the business in context', function (array $payload) {
        ($this->count)(NotificationsFixtures::MEMBER_ACCOUNT_ID, $payload);

        expect($this->feed->unreadCounts)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'recipientFilter' => NotificationsFixtures::MEMBER_ID,
        ]]);
    })->with([
        'no scope' => [[]],
        'its own scope' => [['scope' => 'mine']],
    ]);

    it('counts only the owner own unread notifications, never the team, when no scope is given', function () {
        $data = ($this->count)(NotificationsFixtures::OWNER_ACCOUNT_ID)->value();

        expect($data->count)->toBe(0)
            ->and($this->feed->unreadCounts[0]['recipientFilter'])->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });
});

describe('the whole team', function () {
    it('counts the unread notifications of every recipient for the owner', function () {
        $this->feed->add(
            NotificationsFixtures::BUSINESS_ID,
            NotificationsFixtures::record(
                id: NotificationsFixtures::NEW_NOTIFICATION_ID,
                recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID,
            ),
        );

        $data = ($this->count)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team'])->value();

        expect($data->count)->toBe(4);
    });

    it('asks the feed for no recipient filter when the owner asks for the team', function () {
        ($this->count)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team']);

        expect($this->feed->unreadCounts)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'recipientFilter' => null,
        ]]);
    });

    it('refuses a team member who is not the owner', function () {
        $response = ($this->count)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['scope' => 'team']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('team_notifications_require_owner')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($response->error()->cause())->toBeInstanceOf(TeamNotificationsRequireOwner::class);
    });

    it('never reaches the feed when a team member is refused the team', function () {
        ($this->count)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['scope' => 'team']);

        expect($this->feed->unreadCounts)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('counts in the business in context only', function () {
        $this->readers->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
            NotificationReader::member(NotificationsFixtures::MEMBER_ID),
        );

        $data = ($this->count)(
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
            business: new FakeBusinessContext(NotificationsFixtures::OTHER_BUSINESS_ID),
        )->value();

        expect($data->count)->toBe(0)
            ->and($this->readers->lookups[0]['businessId'])->toBe(NotificationsFixtures::OTHER_BUSINESS_ID)
            ->and($this->feed->unreadCounts[0]['businessId'])->toBe(NotificationsFixtures::OTHER_BUSINESS_ID);
    });

    it('counts the team of the business in context only', function () {
        $this->readers->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID),
        );
        $this->feed->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::record(id: NotificationsFixtures::NEW_NOTIFICATION_ID, recipientStaffMemberId: NotificationsFixtures::OTHER_MEMBER_ID),
        );

        $data = ($this->count)(
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            ['scope' => 'team'],
            new FakeBusinessContext(NotificationsFixtures::OTHER_BUSINESS_ID),
        )->value();

        expect($data->count)->toBe(1)
            ->and($this->feed->unreadCounts[0]['businessId'])->toBe(NotificationsFixtures::OTHER_BUSINESS_ID);
    });

    it('refuses an account that is no member of the business and counts nothing', function () {
        $response = ($this->count)(NotificationsFixtures::STRANGER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->feed->unreadCounts)->toBe([]);
    });
});

describe('a scope it refuses', function () {
    it('answers with the refusal before resolving the caller or reading the feed', function (string $scope) {
        $response = ($this->count)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => $scope]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_notification_scope')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause())->toBeInstanceOf(InvalidNotificationScope::class)
            ->and($this->journal->entries)->toBe([]);
    })->with([
        'an unknown scope' => 'everyone',
        'an empty scope' => '',
        'a scope in upper case' => 'TEAM',
    ]);
});
