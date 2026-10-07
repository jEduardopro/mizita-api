<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

beforeEach(function () {
    $this->payload = NotificationsFixtures::scheduleChangePayload();
});

describe('what it is about', function () {
    it('is a schedule change', function () {
        expect($this->payload->type())->toBe(NotificationType::StaffScheduleChanged);
    });

    it('is about the staff member, by uuid', function () {
        expect($this->payload->subject()->type)->toBe(NotificationSubjectType::StaffMember)
            ->and($this->payload->subject()->id)->toBe(NotificationsFixtures::MEMBER_ID);
    });

    it('keys idempotency on the event uuid it is handed', function (string $eventId) {
        expect($this->payload->idempotencyKey($eventId))->toBe($eventId);
    })->with([
        'an event' => NotificationsFixtures::EVENT_ID,
        'another event' => NotificationsFixtures::NEXT_EVENT_ID,
    ]);

    it('collapses on the staff member, whatever the event uuid', function (string $eventId) {
        expect($this->payload->collapseKey($eventId))->toBe('staff_schedule_changed:'.NotificationsFixtures::MEMBER_ID);
    })->with([
        'an event' => NotificationsFixtures::EVENT_ID,
        'another event' => NotificationsFixtures::NEXT_EVENT_ID,
    ]);
});

describe('the snapshot', function () {
    it('writes the staff member under their uuid and their name', function () {
        expect($this->payload->toArray())->toBe([
            'staff_member' => [
                'id' => NotificationsFixtures::MEMBER_ID,
                'name' => NotificationsFixtures::MEMBER_NAME,
            ],
        ]);
    });

    it('survives a round trip', function () {
        expect(StaffScheduleChangedPayload::fromArray($this->payload->toArray()))->toEqual($this->payload);
    });

    it('reads a missing field as empty rather than failing', function (string $field) {
        $snapshot = $this->payload->toArray();
        unset($snapshot['staff_member'][$field]);

        expect(StaffScheduleChangedPayload::fromArray($snapshot)->toArray()['staff_member'][$field])->toBe('');
    })->with(['id', 'name']);

    it('reads a missing or malformed section as an empty staff member', function (array $snapshot) {
        expect(StaffScheduleChangedPayload::fromArray($snapshot)->toArray())
            ->toBe(['staff_member' => ['id' => '', 'name' => '']]);
    })->with([
        'an empty snapshot' => [[]],
        'a section that is a string' => [['staff_member' => 'Ana']],
        'a section that is null' => [['staff_member' => null]],
    ]);
});
