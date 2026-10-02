<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\BookingLinkStatus;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\ValueObjects\AccountSharing;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\Staff\StaffFixtures;

it('offers the temporary password only when the invitation is pending and the account still holds it', function (
    StaffRole $role,
    bool $awaitingPasswordChange,
    bool $holdsTemporaryPassword,
    bool $invitationPending,
    bool $temporaryPasswordAvailable,
) {
    $data = TeamMemberData::fromEntities(
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: $role),
        null,
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, awaitingPasswordChange: $awaitingPasswordChange),
        AccountSharing::ExclusiveToBusiness,
        null,
        null,
        new BookingLinkStatus(null, []),
        $holdsTemporaryPassword,
    );

    expect($data->invitationPending)->toBe($invitationPending)
        ->and($data->temporaryPasswordAvailable)->toBe($temporaryPasswordAvailable);
})->with([
    'pending and held' => [StaffRole::Member, true, true, true, true],
    'pending but discarded' => [StaffRole::Member, true, false, true, false],
    'signed in with the password still held' => [StaffRole::Member, false, true, false, false],
    'signed in and discarded' => [StaffRole::Member, false, false, false, false],
    'no access member holding one' => [StaffRole::NoAccess, true, true, false, false],
    'the owner holding one' => [StaffRole::Owner, true, true, false, false],
]);

it('neither marks as pending nor offers the password of an account shared with other businesses', function (bool $holdsTemporaryPassword) {
    $data = TeamMemberData::fromEntities(
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        null,
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, awaitingPasswordChange: true),
        AccountSharing::SharedWithOtherBusinesses,
        null,
        null,
        new BookingLinkStatus(null, []),
        $holdsTemporaryPassword,
    );

    expect($data->invitationPending)->toBeFalse()
        ->and($data->temporaryPasswordAvailable)->toBeFalse()
        ->and($data->level)->toBe(StaffRole::Member);
})->with([
    'holding a temporary password' => true,
    'holding none' => false,
]);

it('carries the booking link status it was handed, untouched', function () {
    $status = new BookingLinkStatus(
        new BookingLinkData(StaffFixtures::BOOKING_SLUG, StaffFixtures::BOOKING_URL),
        [BookingLinkBlocker::NoWorkingHours],
    );

    $data = TeamMemberData::fromEntities(
        StaffFixtures::member(),
        StaffFixtures::profile(bookingSlug: StaffFixtures::BOOKING_SLUG),
        StaffFixtures::account(),
        AccountSharing::ExclusiveToBusiness,
        null,
        null,
        $status,
        false,
    );

    expect($data->bookingLink)->toBe($status);
});
