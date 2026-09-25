<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberData;
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
        null,
        null,
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
