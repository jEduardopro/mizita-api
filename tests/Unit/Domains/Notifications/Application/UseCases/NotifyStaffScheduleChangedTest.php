<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Application\UseCases\NotifyStaffScheduleChanged;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBusinessOwners;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeNotifiedStaffMembers;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsJournal;

const SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b3';

const SCHEDULE_CHANGE_NEXT_DELIVERY_ID = '01930000-0000-7000-8000-000000000297';

const SCHEDULE_CHANGE_UNKNOWN_STAFF_MEMBER_ID = '01930000-0000-7000-8000-0000000000df';

beforeEach(function () {
    $this->journal = new NotificationsJournal;
    $this->owners = (new FakeBusinessOwners($this->journal))
        ->ownedBy(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID)
        ->ownedBy(NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID);
    $this->staffMembers = (new FakeNotifiedStaffMembers($this->journal))
        ->add(NotificationsFixtures::BUSINESS_ID, new NotifiedStaffMember(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::MEMBER_NAME))
        ->add(NotificationsFixtures::BUSINESS_ID, new NotifiedStaffMember(NotificationsFixtures::OTHER_MEMBER_ID, NotificationsFixtures::OTHER_MEMBER_NAME))
        ->add(NotificationsFixtures::BUSINESS_ID, new NotifiedStaffMember(NotificationsFixtures::OWNER_MEMBER_ID, NotificationsFixtures::OWNER_NAME))
        ->add(NotificationsFixtures::OTHER_BUSINESS_ID, new NotifiedStaffMember(NotificationsFixtures::MEMBER_ID, NotificationsFixtures::MEMBER_NAME));
    $this->transactions = new FakeTransactionManager;
    $this->notifications = new FakeStaffNotificationRepository($this->journal, $this->transactions);
    $this->clock = new FakeClock(NotificationsFixtures::now());

    $this->useCase = new NotifyStaffScheduleChanged(
        $this->owners,
        $this->staffMembers,
        $this->notifications,
        $this->transactions,
        new FixedIdGenerator(
            NotificationsFixtures::EVENT_ID,
            NotificationsFixtures::NEW_NOTIFICATION_ID,
            NotificationsFixtures::NEXT_EVENT_ID,
            SCHEDULE_CHANGE_NEXT_DELIVERY_ID,
        ),
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

    it('records one event delivered to the owner of the business only', function () {
        ($this->notify)();

        expect($this->notifications->recorded)->toHaveCount(1)
            ->and($this->notifications->recorded[0]['deliveries'])->toHaveCount(1)
            ->and($this->notifications->recorded[0]['deliveries'][0]->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID);
    });

    it('records the event under the first uuid it generated, about the staff member, at the instant of the clock', function () {
        ($this->notify)();

        $event = $this->notifications->recorded[0]['event'];

        expect($event->id)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($event->type)->toBe(NotificationType::StaffScheduleChanged)
            ->and($event->subject->type)->toBe(NotificationSubjectType::StaffMember)
            ->and($event->subject->id)->toBe(NotificationsFixtures::MEMBER_ID)
            ->and($event->occurredAt)->toEqual(NotificationsFixtures::now());
    });

    it('snapshots the staff member under their uuid and their name', function () {
        ($this->notify)();

        expect($this->notifications->recorded[0]['event']->payload->toArray())->toBe([
            'staff_member' => [
                'id' => NotificationsFixtures::MEMBER_ID,
                'name' => NotificationsFixtures::MEMBER_NAME,
            ],
        ]);
    });

    it('keys the event on its own uuid, since every change is a new fact', function () {
        ($this->notify)();

        expect($this->notifications->recorded[0]['event']->idempotencyKey)->toBe(NotificationsFixtures::EVENT_ID);
    });

    it('collapses the delivery on the staff member uuid', function () {
        ($this->notify)();

        expect($this->notifications->recorded[0]['deliveries'][0]->collapseKey)
            ->toBe('staff_schedule_changed:'.NotificationsFixtures::MEMBER_ID);
    });

    it('delivers under the second uuid it generated, unread, at the instant of the clock', function () {
        ($this->notify)();

        $delivery = $this->notifications->recorded[0]['deliveries'][0];

        expect($delivery->id)->toBe(NotificationsFixtures::NEW_NOTIFICATION_ID)
            ->and($delivery->eventId)->toBe(NotificationsFixtures::EVENT_ID)
            ->and($delivery->isUnread())->toBeTrue()
            ->and($delivery->createdAt)->toEqual(NotificationsFixtures::now());
    });

    it('files the event under the business the change happened in, delivered to that business owner', function (string $businessId, string $owner) {
        ($this->notify)(NotificationsFixtures::MEMBER_ID, $businessId);

        $recorded = $this->notifications->recorded[0];

        expect($this->owners->lookups)->toBe([$businessId])
            ->and($this->staffMembers->lookups)->toBe([['businessId' => $businessId, 'staffMemberId' => NotificationsFixtures::MEMBER_ID]])
            ->and($recorded['event']->businessId)->toBe($businessId)
            ->and($recorded['deliveries'][0]->businessId)->toBe($businessId)
            ->and($recorded['deliveries'][0]->recipientStaffMemberId)->toBe($owner);
    })->with([
        'the first business' => [NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID],
        'another business' => [NotificationsFixtures::OTHER_BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ID],
    ]);

    it('records the event inside one transaction', function () {
        ($this->notify)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->notifications->recorded[0]['insideTransaction'])->toBeTrue();
    });

    it('finds the owner, then describes the staff member, then records through the event door only', function () {
        ($this->notify)();

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf', 'staffMembers.describe', 'notifications.record'])
            ->and($this->notifications->markedAsRead)->toBe([]);
    });
});

describe('repeated schedule changes', function () {
    beforeEach(function () {
        ($this->notify)();
        $this->clock->advance('PT10M');
        ($this->notify)();
    });

    it('records each change as its own event, with a fresh uuid and the instant it happened', function () {
        expect($this->notifications->recorded)->toHaveCount(2)
            ->and($this->notifications->recorded[1]['event']->id)->toBe(NotificationsFixtures::NEXT_EVENT_ID)
            ->and($this->notifications->recorded[1]['event']->occurredAt)->toEqual(NotificationsFixtures::now()->modify('+10 minutes'))
            ->and($this->notifications->recorded[1]['deliveries'][0]->id)->toBe(SCHEDULE_CHANGE_NEXT_DELIVERY_ID);
    });

    it('never lets the second change pass for a duplicate of the first', function () {
        expect($this->notifications->recorded[0]['event']->idempotencyKey)
            ->not->toBe($this->notifications->recorded[1]['event']->idempotencyKey);
    });

    it('collapses both deliveries on the same key', function () {
        expect($this->notifications->recorded[1]['deliveries'][0]->collapseKey)
            ->toBe($this->notifications->recorded[0]['deliveries'][0]->collapseKey);
    });
});

it('collapses the changes of two different staff members on different keys', function () {
    ($this->notify)(NotificationsFixtures::MEMBER_ID);
    ($this->notify)(NotificationsFixtures::OTHER_MEMBER_ID);

    expect(array_map(
        static fn (array $recorded) => $recorded['deliveries'][0]->collapseKey,
        $this->notifications->recorded,
    ))->toBe([
        'staff_schedule_changed:'.NotificationsFixtures::MEMBER_ID,
        'staff_schedule_changed:'.NotificationsFixtures::OTHER_MEMBER_ID,
    ]);
});

describe('a business with no owner', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)(NotificationsFixtures::MEMBER_ID, SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('writes nothing and opens no transaction', function () {
        ($this->notify)(NotificationsFixtures::MEMBER_ID, SCHEDULE_CHANGE_OWNERLESS_BUSINESS_ID);

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf'])
            ->and($this->notifications->recorded)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});

describe('the owner changing their own schedule', function () {
    it('succeeds with nothing to hand back', function () {
        $response = ($this->notify)(NotificationsFixtures::OWNER_MEMBER_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('writes nothing to the owner about themselves and opens no transaction', function () {
        ($this->notify)(NotificationsFixtures::OWNER_MEMBER_ID);

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf'])
            ->and($this->notifications->recorded)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('still notifies the owner of a business when the owner of another business changes their schedule there as staff', function () {
        ($this->notify)(NotificationsFixtures::OTHER_MEMBER_ID, NotificationsFixtures::BUSINESS_ID);

        expect($this->notifications->recorded)->toHaveCount(1)
            ->and($this->notifications->recorded[0]['deliveries'][0]->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
            ->and($this->notifications->recorded[0]['event']->payload->toArray()['staff_member']['name'])->toBe(NotificationsFixtures::OTHER_MEMBER_NAME);
    });
});

describe('a staff member that cannot be found in the business', function () {
    it('answers not found', function (string $staffMemberId, string $businessId) {
        $response = ($this->notify)($staffMemberId, $businessId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('notified_staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'unknown everywhere' => [SCHEDULE_CHANGE_UNKNOWN_STAFF_MEMBER_ID, NotificationsFixtures::BUSINESS_ID],
        'a member of another business only' => [NotificationsFixtures::OWNER_MEMBER_ID, NotificationsFixtures::OTHER_BUSINESS_ID],
    ]);

    it('writes nothing and opens no transaction', function () {
        ($this->notify)(SCHEDULE_CHANGE_UNKNOWN_STAFF_MEMBER_ID);

        expect($this->journal->entries)->toBe(['owners.ownerStaffMemberIdOf', 'staffMembers.describe'])
            ->and($this->notifications->recorded)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
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

    it('refuses before it looks anything up or writes anything', function () {
        ($this->notify)('42');

        expect($this->journal->entries)->toBe([])
            ->and($this->notifications->recorded)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});
