<?php

declare(strict_types=1);

use App\Domains\Notifications\Exceptions\NotificationsNotAccessible;
use App\Domains\Notifications\Exceptions\TeamNotificationsRequireOwner;
use App\Domains\Notifications\Infrastructure\Gateways\StaffNotificationReaders;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\FakeBusinessAuthorization;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\StaffFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(
            id: NotificationsFixtures::OWNER_MEMBER_ID,
            accountId: NotificationsFixtures::OWNER_ACCOUNT_ID,
            role: StaffRole::Owner,
        ),
        StaffFixtures::member(
            id: NotificationsFixtures::MEMBER_ID,
            accountId: NotificationsFixtures::MEMBER_ACCOUNT_ID,
            role: StaffRole::Member,
        ),
    );
    $this->authorization = (new FakeBusinessAuthorization)
        ->add(NotificationsFixtures::OWNER_ACCOUNT_ID, NotificationsFixtures::BUSINESS_ID, [], [StaffRole::Owner->value])
        ->add(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::BUSINESS_ID, [], [StaffRole::Member->value]);

    $this->readerFor = fn (string $accountId): NotificationReader => (new StaffNotificationReaders($this->members, $this->authorization))
        ->readerFor(NotificationsFixtures::BUSINESS_ID, $accountId);
});

it('reads as the owner an account holding the owner role in the business', function () {
    $reader = ($this->readerFor)(NotificationsFixtures::OWNER_ACCOUNT_ID);

    expect($reader->staffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
        ->and($reader->audienceFor(NotificationScope::Team)->recipientFilter())->toBeNull();
});

it('reads as a team member an account holding the staff role', function () {
    $reader = ($this->readerFor)(NotificationsFixtures::MEMBER_ACCOUNT_ID);

    expect($reader->staffMemberId)->toBe(NotificationsFixtures::MEMBER_ID)
        ->and(fn () => $reader->audienceFor(NotificationScope::Team))->toThrow(TeamNotificationsRequireOwner::class);
});

it('reads as a team member an account that owns another business only', function () {
    $this->authorization
        ->add(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::BUSINESS_ID, [], [StaffRole::Member->value])
        ->add(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::OTHER_BUSINESS_ID, [], [StaffRole::Owner->value]);

    expect(fn () => ($this->readerFor)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->audienceFor(NotificationScope::Team))
        ->toThrow(TeamNotificationsRequireOwner::class);
});

it('identifies the reader by its staff member uuid, never by the account', function () {
    expect(($this->readerFor)(NotificationsFixtures::MEMBER_ACCOUNT_ID)->staffMemberId)
        ->toBe(NotificationsFixtures::MEMBER_ID)
        ->not->toBe(NotificationsFixtures::MEMBER_ACCOUNT_ID);
});

it('asks for the membership and the roles in the business it was handed', function () {
    ($this->readerFor)(NotificationsFixtures::OWNER_ACCOUNT_ID);

    expect($this->members->accountLookups)->toBe([[
        'businessId' => NotificationsFixtures::BUSINESS_ID,
        'accountId' => NotificationsFixtures::OWNER_ACCOUNT_ID,
    ]])->and($this->authorization->lookups)->toBe([[
        'accountId' => NotificationsFixtures::OWNER_ACCOUNT_ID,
        'businessId' => NotificationsFixtures::BUSINESS_ID,
    ]]);
});

describe('an account with no membership in the business', function () {
    beforeEach(function () {
        $this->refusal = null;

        try {
            ($this->readerFor)(NotificationsFixtures::STRANGER_ACCOUNT_ID);
        } catch (NotificationsNotAccessible $refusal) {
            $this->refusal = $refusal;
        }
    });

    it('is refused', function () {
        expect($this->refusal)->toBeInstanceOf(NotificationsNotAccessible::class);
    });

    it('is refused with the missing membership as the cause', function () {
        expect($this->refusal?->getPrevious())->toBeInstanceOf(StaffMemberNotFound::class);
    });

    it('is never asked about its roles', function () {
        expect($this->authorization->lookups)->toBe([]);
    });
});
