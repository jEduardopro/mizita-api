<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Domains\Notifications\ValueObjects\NotificationType;

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
