<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\ListStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Application\UseCases\ListStaffNotifications;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Pagination;
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
        NotificationsFixtures::record(
            id: NotificationsFixtures::SECOND_NOTIFICATION_ID,
            recipientStaffMemberId: NotificationsFixtures::OTHER_MEMBER_ID,
        ),
        NotificationsFixtures::record(
            id: NotificationsFixtures::THIRD_NOTIFICATION_ID,
            recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID,
            readAt: NotificationsFixtures::READ_AT,
        ),
    );

    $this->list = fn (
        string $accountId,
        array $payload = [],
        FakeBusinessContext $business = new FakeBusinessContext,
    ): UseCaseResponse => (new ListStaffNotifications($this->feed, $this->readers, $business))
        ->handle(ListStaffNotificationsInput::fromRequest($payload, $accountId));

    $this->idsOf = static fn (UseCaseResponse $response): array => array_map(
        static fn (StaffNotificationData $data) => $data->id,
        $response->value()->items,
    );
});

describe('a team member reading their own notifications', function () {
    it('lists the notifications addressed to them', function () {
        $response = ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect(($this->idsOf)($response))->toBe([NotificationsFixtures::NOTIFICATION_ID]);
    });

    it('asks the feed for the notifications addressed to their staff member only', function () {
        ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->feed->queries[0]['recipientFilter'])->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('hands back each notification under its uuids, with the right to mark it as read', function () {
        $data = ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->value()->items[0];

        expect($data)->toBeInstanceOf(StaffNotificationData::class)
            ->and($data->id)->toBe(NotificationsFixtures::NOTIFICATION_ID)
            ->and($data->type)->toBe('appointment_booked')
            ->and($data->recipient->id)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($data->recipient->name)->toBe(NotificationsFixtures::MEMBER_NAME)
            ->and($data->appointment?->id)->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($data->customer?->id)->toBe(NotificationsFixtures::CUSTOMER_ID)
            ->and($data->readAt)->toBeNull()
            ->and($data->createdAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::CREATED_AT))
            ->and($data->canMarkAsRead)->toBeTrue();
    });

    it('is refused the whole team', function () {
        $response = ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['scope' => 'team']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('team_notifications_require_owner')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('never reaches the feed when refused the whole team', function () {
        ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['scope' => 'team']);

        expect($this->feed->queries)->toBe([]);
    });
});

describe('the owner', function () {
    it('lists the whole team when asking for the team', function () {
        $response = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team']);

        expect(($this->idsOf)($response))->toBe([
            NotificationsFixtures::NOTIFICATION_ID,
            NotificationsFixtures::SECOND_NOTIFICATION_ID,
            NotificationsFixtures::THIRD_NOTIFICATION_ID,
        ])->and($this->feed->queries[0]['recipientFilter'])->toBeNull();
    });

    it('may mark none of the team notifications as read', function () {
        $items = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team'])->value()->items;

        expect(array_map(static fn (StaffNotificationData $data) => $data->canMarkAsRead, $items))
            ->toBe([false, false, false]);
    });

    it('may mark an unread notification addressed to the owner', function () {
        $this->feed->add(
            NotificationsFixtures::BUSINESS_ID,
            NotificationsFixtures::record(
                id: NotificationsFixtures::THIRD_NOTIFICATION_ID,
                recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID,
            ),
        );

        $items = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team'])->value()->items;

        expect($items[2]->id)->toBe(NotificationsFixtures::THIRD_NOTIFICATION_ID)
            ->and($items[2]->canMarkAsRead)->toBeTrue();
    });

    it('lists only the notifications addressed to the owner when asking for their own', function () {
        $response = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID);

        expect(($this->idsOf)($response))->toBe([NotificationsFixtures::THIRD_NOTIFICATION_ID])
            ->and($this->feed->queries[0]['recipientFilter'])->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });
});

describe('what it asks the feed for', function () {
    it('asks for every notification, read or not, on the first default page when nothing is said', function () {
        ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->feed->queries)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'recipientFilter' => NotificationsFixtures::MEMBER_ID,
            'status' => NotificationStatus::All,
            'page' => 1,
            'perPage' => Pagination::DEFAULT_PER_PAGE,
        ]]);
    });

    it('asks for the unread ones only when told to', function () {
        ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team', 'status' => 'unread']);

        expect($this->feed->queries[0]['status'])->toBe(NotificationStatus::Unread);
    });

    it('asks for the page it was handed', function () {
        ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID, ['page' => 2, 'per_page' => 5]);

        expect($this->feed->queries[0]['page'])->toBe(2)
            ->and($this->feed->queries[0]['perPage'])->toBe(5);
    });

    it('keeps the total and the page of the feed', function () {
        $page = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, ['scope' => 'team', 'per_page' => 2])->value();

        expect($page->total)->toBe(3)
            ->and($page->items)->toHaveCount(2)
            ->and($page->pagination->perPage)->toBe(2)
            ->and($page->lastPage())->toBe(2);
    });
});

describe('tenant isolation', function () {
    it('resolves the caller inside the business in context', function () {
        ($this->list)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($this->readers->lookups)->toBe([[
            'businessId' => NotificationsFixtures::BUSINESS_ID,
            'accountId' => NotificationsFixtures::MEMBER_ACCOUNT_ID,
        ]]);
    });

    it('reads the feed of the business in context only', function () {
        $this->readers->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID),
        );
        $this->feed->add(
            NotificationsFixtures::OTHER_BUSINESS_ID,
            NotificationsFixtures::record(id: NotificationsFixtures::UNKNOWN_NOTIFICATION_ID, recipientStaffMemberId: NotificationsFixtures::OWNER_MEMBER_ID),
        );

        $response = ($this->list)(
            NotificationsFixtures::OWNER_ACCOUNT_ID,
            ['scope' => 'team'],
            new FakeBusinessContext(NotificationsFixtures::OTHER_BUSINESS_ID),
        );

        expect(($this->idsOf)($response))->toBe([NotificationsFixtures::UNKNOWN_NOTIFICATION_ID])
            ->and($this->feed->queries[0]['businessId'])->toBe(NotificationsFixtures::OTHER_BUSINESS_ID);
    });

    it('refuses an account that is no member of the business in context', function () {
        $response = ($this->list)(NotificationsFixtures::STRANGER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->feed->queries)->toBe([]);
    });
});

describe('a payload it refuses', function () {
    it('answers with the refusal and reads nothing', function (array $payload, string $code) {
        $response = ($this->list)(NotificationsFixtures::OWNER_ACCOUNT_ID, $payload);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->journal->entries)->toBe([]);
    })->with([
        'an unknown scope' => [['scope' => 'everyone'], 'invalid_notification_scope'],
        'an unknown status' => [['status' => 'archived'], 'invalid_notification_status'],
        'page zero' => [['page' => 0], 'page_out_of_range'],
    ]);
});
