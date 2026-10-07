<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('accepts a business uuid and a staff member uuid', function () {
    $input = new NotifyStaffScheduleChangedInput(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('treats an identifier that is no uuid as a staff member that does not exist', function (string $businessId, string $staffMemberId) {
    $input = new NotifyStaffScheduleChangedInput($businessId, $staffMemberId);

    expect(fn () => $input->validate())->toThrow(NotifiedStaffMemberNotFound::class);
})->with([
    'an empty staff member' => [NotificationsFixtures::BUSINESS_ID, ''],
    'a whitespace only staff member' => [NotificationsFixtures::BUSINESS_ID, '   '],
    'a sequential int staff member' => [NotificationsFixtures::BUSINESS_ID, '42'],
    'a staff member uuid with a trailing newline' => [NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ID."\n"],
    'an empty business' => ['', NotificationsFixtures::MEMBER_ID],
    'a sequential int business' => ['7', NotificationsFixtures::MEMBER_ID],
    'a word for a business' => ['not-a-uuid', NotificationsFixtures::MEMBER_ID],
]);
