<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use App\Domains\Notifications\ValueObjects\Payloads\NotificationPayload;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('excludes read notifications only when asked for the unread ones', function (NotificationStatus $status, bool $excludesRead) {
    expect($status->excludesRead())->toBe($excludesRead);
})->with([
    'unread' => [NotificationStatus::Unread, true],
    'all' => [NotificationStatus::All, false],
]);

it('keeps the wire values the client sends and reads', function () {
    expect(array_map(static fn (NotificationScope $scope) => $scope->value, NotificationScope::cases()))->toBe(['mine', 'team'])
        ->and(array_map(static fn (NotificationStatus $status) => $status->value, NotificationStatus::cases()))->toBe(['unread', 'all'])
        ->and(array_map(static fn (NotificationType $type) => $type->value, NotificationType::cases()))->toBe(['appointment_booked', 'staff_schedule_changed']);
});

it('stores the subject types as the morph aliases of the models they name', function () {
    expect(array_map(static fn (NotificationSubjectType $type) => $type->value, NotificationSubjectType::cases()))
        ->toBe(['appointment', 'staff_member']);
});

describe('building a key for a type', function () {
    it('prefixes the source uuid with the type', function (NotificationType $type, string $key) {
        expect($type->keyFor(NotificationsFixtures::APPOINTMENT_ID))->toBe($key);
    })->with([
        'a booking' => [NotificationType::AppointmentBooked, 'appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID],
        'a schedule change' => [NotificationType::StaffScheduleChanged, 'staff_schedule_changed:'.NotificationsFixtures::APPOINTMENT_ID],
    ]);

    it('never lets two types share a key for the same source', function () {
        expect(NotificationType::AppointmentBooked->keyFor(NotificationsFixtures::MEMBER_ID))
            ->not->toBe(NotificationType::StaffScheduleChanged->keyFor(NotificationsFixtures::MEMBER_ID));
    });
});

describe('reading a stored snapshot back for a type', function () {
    it('picks the payload class that matches the type', function (NotificationType $type, NotificationPayload $payload, string $class) {
        $read = $type->payloadFrom($payload->toArray());

        expect($read)->toBeInstanceOf($class)
            ->and($read->type())->toBe($type)
            ->and($read->toArray())->toBe($payload->toArray());
    })->with([
        'a booking' => [NotificationType::AppointmentBooked, NotificationsFixtures::bookingPayload(), AppointmentBookedPayload::class],
        'a schedule change' => [NotificationType::StaffScheduleChanged, NotificationsFixtures::scheduleChangePayload(), StaffScheduleChangedPayload::class],
    ]);

    it('reads an empty schedule change snapshot without failing', function () {
        expect(NotificationType::StaffScheduleChanged->payloadFrom([])->toArray())
            ->toBe(['staff_member' => ['id' => '', 'name' => '']]);
    });

    it('refuses to read a booking snapshot with no instants', function () {
        expect(fn () => NotificationType::AppointmentBooked->payloadFrom([]))
            ->toThrow(UnexpectedValueException::class);
    });
});
