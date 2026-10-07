<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\MarkStaffNotificationAsReadInput;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('accepts a notification uuid', function () {
    $input = new MarkStaffNotificationAsReadInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationsFixtures::NOTIFICATION_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('treats a notification identifier that is no uuid as a notification that does not exist', function (string $notificationId) {
    $input = new MarkStaffNotificationAsReadInput(NotificationsFixtures::MEMBER_ACCOUNT_ID, $notificationId);

    expect(fn () => $input->validate())->toThrow(StaffNotificationNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '42',
    'a word' => 'not-a-uuid',
]);
