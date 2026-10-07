<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\ListStaffNotificationsInput;
use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\Exceptions\InvalidNotificationStatus;
use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\PageOutOfRange;
use App\Shared\ValueObjects\Pagination;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

function listStaffNotificationsRefusalOf(ListStaffNotificationsInput $input): ?DomainFailure
{
    try {
        $input->validate();
    } catch (DomainFailure $refusal) {
        return $refusal;
    }

    return null;
}

describe('building from the request', function () {
    it('reads every key of a well formed payload', function () {
        $input = ListStaffNotificationsInput::fromRequest(
            ['scope' => 'team', 'status' => 'unread', 'page' => 3, 'per_page' => 50],
            NotificationsFixtures::OWNER_ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(NotificationsFixtures::OWNER_ACCOUNT_ID)
            ->and($input->scope())->toBe(NotificationScope::Team)
            ->and($input->status())->toBe(NotificationStatus::Unread)
            ->and($input->pagination()->page)->toBe(3)
            ->and($input->pagination()->perPage)->toBe(50);
    });

    it('takes the caller from the session, never from the payload', function () {
        $input = ListStaffNotificationsInput::fromRequest(
            ['account_id' => NotificationsFixtures::STRANGER_ACCOUNT_ID, 'accountId' => NotificationsFixtures::STRANGER_ACCOUNT_ID],
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(NotificationsFixtures::MEMBER_ACCOUNT_ID);
    });

    it('survives an empty payload and falls back to the caller own notifications, read or not, on the first page', function () {
        $input = ListStaffNotificationsInput::fromRequest([], NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class)
            ->and($input->scope())->toBe(NotificationScope::Mine)
            ->and($input->status())->toBe(NotificationStatus::All)
            ->and($input->pagination()->page)->toBe(1)
            ->and($input->pagination()->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('reads numeric strings from a query string as numbers', function () {
        $input = ListStaffNotificationsInput::fromRequest(['page' => '2', 'per_page' => '10'], NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(10);
    });

    it('drops values of the wrong type instead of failing on them', function () {
        $input = ListStaffNotificationsInput::fromRequest(
            ['scope' => ['team'], 'status' => 7, 'page' => 'second', 'per_page' => null],
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
        );

        expect($input->scope)->toBeNull()
            ->and($input->status)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });
});

describe('validating', function () {
    it('accepts every scope and status the domain serves', function (string $scope, string $status) {
        $input = new ListStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, $scope, $status);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with(['mine', 'team'])->with(['unread', 'all']);

    it('accepts the first and the deepest page it serves', function (int $page) {
        expect(fn () => (new ListStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, page: $page))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'the first page' => 1,
        'the deepest page' => Pagination::MAXIMUM_PAGE,
    ]);

    it('refuses a payload the form request would have refused with a domain failure', function (array $payload, string $failure, string $code) {
        $refusal = listStaffNotificationsRefusalOf(ListStaffNotificationsInput::fromRequest($payload, NotificationsFixtures::MEMBER_ACCOUNT_ID));

        expect($refusal)->toBeInstanceOf($failure)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe($code);
    })->with([
        'an unknown scope' => [['scope' => 'everyone'], InvalidNotificationScope::class, 'invalid_notification_scope'],
        'an empty scope' => [['scope' => ''], InvalidNotificationScope::class, 'invalid_notification_scope'],
        'a scope in upper case' => [['scope' => 'TEAM'], InvalidNotificationScope::class, 'invalid_notification_scope'],
        'a scope padded with spaces' => [['scope' => ' team '], InvalidNotificationScope::class, 'invalid_notification_scope'],
        'an unknown status' => [['status' => 'archived'], InvalidNotificationStatus::class, 'invalid_notification_status'],
        'an empty status' => [['status' => ''], InvalidNotificationStatus::class, 'invalid_notification_status'],
        'a status in upper case' => [['status' => 'UNREAD'], InvalidNotificationStatus::class, 'invalid_notification_status'],
        'page zero' => [['page' => 0], PageOutOfRange::class, 'page_out_of_range'],
        'a negative page' => [['page' => -1], PageOutOfRange::class, 'page_out_of_range'],
        'a page past the deepest' => [['page' => Pagination::MAXIMUM_PAGE + 1], PageOutOfRange::class, 'page_out_of_range'],
    ]);

    it('reports the scope before the status', function () {
        $input = new ListStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, 'everyone', 'archived', 0);

        expect(fn () => $input->validate())->toThrow(InvalidNotificationScope::class);
    });
});

describe('the page it asks for', function () {
    it('clamps a page size outside the served range instead of refusing it', function (int $perPage, int $served) {
        expect((new ListStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, perPage: $perPage))->pagination()->perPage)
            ->toBe($served);
    })->with([
        'zero rows' => [0, 1],
        'more rows than served' => [Pagination::MAXIMUM_PER_PAGE + 1, Pagination::MAXIMUM_PER_PAGE],
        'the largest page served' => [Pagination::MAXIMUM_PER_PAGE, Pagination::MAXIMUM_PER_PAGE],
    ]);
});
