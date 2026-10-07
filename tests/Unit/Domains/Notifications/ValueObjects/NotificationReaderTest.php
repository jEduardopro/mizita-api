<?php

declare(strict_types=1);

use App\Domains\Notifications\Exceptions\TeamNotificationsRequireOwner;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationScope;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

describe('a team member', function () {
    beforeEach(function () {
        $this->reader = NotificationReader::member(NotificationsFixtures::MEMBER_ID);
    });

    it('is identified by its staff member uuid', function () {
        expect($this->reader->staffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('reads only the notifications addressed to it', function () {
        expect($this->reader->audienceFor(NotificationScope::Mine)->recipientFilter())
            ->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('may not read the whole team', function () {
        expect(fn () => $this->reader->audienceFor(NotificationScope::Team))
            ->toThrow(TeamNotificationsRequireOwner::class);
    });

    it('views its own notification', function () {
        expect($this->reader->canView(NotificationsFixtures::MEMBER_ID))->toBeTrue();
    });

    it('views no notification addressed to somebody else', function (string $recipient) {
        expect($this->reader->canView($recipient))->toBeFalse();
    })->with([
        'the owner' => NotificationsFixtures::OWNER_MEMBER_ID,
        'another team member' => NotificationsFixtures::OTHER_MEMBER_ID,
    ]);

    it('is the recipient of its own notification only', function () {
        expect($this->reader->isRecipient(NotificationsFixtures::MEMBER_ID))->toBeTrue()
            ->and($this->reader->isRecipient(NotificationsFixtures::OTHER_MEMBER_ID))->toBeFalse();
    });
});

describe('the owner', function () {
    beforeEach(function () {
        $this->reader = NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID);
    });

    it('reads the whole team when it asks for the team', function () {
        expect($this->reader->audienceFor(NotificationScope::Team)->recipientFilter())->toBeNull();
    });

    it('reads only its own notifications when it asks for its own', function () {
        expect($this->reader->audienceFor(NotificationScope::Mine)->recipientFilter())
            ->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });

    it('views a notification addressed to any team member', function (string $recipient) {
        expect($this->reader->canView($recipient))->toBeTrue();
    })->with([
        'itself' => NotificationsFixtures::OWNER_MEMBER_ID,
        'a team member' => NotificationsFixtures::MEMBER_ID,
        'another team member' => NotificationsFixtures::OTHER_MEMBER_ID,
    ]);

    it('is not the recipient of a notification addressed to a team member', function () {
        expect($this->reader->isRecipient(NotificationsFixtures::MEMBER_ID))->toBeFalse();
    });

    it('is the recipient of a notification addressed to itself', function () {
        expect($this->reader->isRecipient(NotificationsFixtures::OWNER_MEMBER_ID))->toBeTrue();
    });
});
