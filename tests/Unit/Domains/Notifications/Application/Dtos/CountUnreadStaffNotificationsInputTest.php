<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\CountUnreadStaffNotificationsInput;
use App\Domains\Notifications\Exceptions\InvalidNotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Shared\Contracts\DomainFailure;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

function countUnreadStaffNotificationsRefusalOf(CountUnreadStaffNotificationsInput $input): ?DomainFailure
{
    try {
        $input->validate();
    } catch (DomainFailure $refusal) {
        return $refusal;
    }

    return null;
}

describe('building from the request', function () {
    it('reads the scope of a well formed payload', function () {
        $input = CountUnreadStaffNotificationsInput::fromRequest(['scope' => 'team'], NotificationsFixtures::OWNER_ACCOUNT_ID);

        expect($input->accountId)->toBe(NotificationsFixtures::OWNER_ACCOUNT_ID)
            ->and($input->scope)->toBe('team')
            ->and($input->scope())->toBe(NotificationScope::Team);
    });

    it('takes the caller from the session, never from the payload', function () {
        $input = CountUnreadStaffNotificationsInput::fromRequest(
            ['account_id' => NotificationsFixtures::STRANGER_ACCOUNT_ID, 'accountId' => NotificationsFixtures::STRANGER_ACCOUNT_ID],
            NotificationsFixtures::MEMBER_ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(NotificationsFixtures::MEMBER_ACCOUNT_ID);
    });

    it('survives an empty payload and falls back to the caller own notifications', function () {
        $input = CountUnreadStaffNotificationsInput::fromRequest([], NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class)
            ->and($input->scope)->toBeNull()
            ->and($input->scope())->toBe(NotificationScope::Mine);
    });

    it('drops a scope of the wrong type instead of failing on it', function (mixed $scope) {
        $input = CountUnreadStaffNotificationsInput::fromRequest(['scope' => $scope], NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect($input->scope)->toBeNull()
            ->and($input->scope())->toBe(NotificationScope::Mine);
    })->with([
        'a list' => [['team']],
        'a number' => 7,
        'a boolean' => true,
        'null' => null,
    ]);
});

describe('validating', function () {
    it('accepts every scope the domain serves', function (string $scope) {
        $input = new CountUnreadStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, $scope);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with(['mine', 'team']);

    it('accepts no scope at all', function () {
        $input = new CountUnreadStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a scope the form request would have refused with a domain failure', function (string $scope) {
        $refusal = countUnreadStaffNotificationsRefusalOf(
            CountUnreadStaffNotificationsInput::fromRequest(['scope' => $scope], NotificationsFixtures::MEMBER_ACCOUNT_ID),
        );

        expect($refusal)->toBeInstanceOf(InvalidNotificationScope::class)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_notification_scope');
    })->with([
        'an unknown scope' => 'everyone',
        'an empty scope' => '',
        'whitespace only' => '   ',
        'a scope in upper case' => 'TEAM',
        'a scope padded with spaces' => ' team ',
        'an accented look-alike' => 'téam',
    ]);
});

describe('the scope it asks for', function () {
    it('maps each served scope to its case', function (string $scope, NotificationScope $expected) {
        expect((new CountUnreadStaffNotificationsInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, $scope))->scope())
            ->toBe($expected);
    })->with([
        'mine' => ['mine', NotificationScope::Mine],
        'team' => ['team', NotificationScope::Team],
    ]);
});
