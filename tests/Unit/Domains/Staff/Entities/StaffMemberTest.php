<?php

declare(strict_types=1);

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\OwnerCannotBeRemoved;
use App\Domains\Staff\Exceptions\OwnerLevelIsFixed;
use App\Domains\Staff\Exceptions\TeamInvitationNotPending;
use App\Domains\Staff\ValueObjects\AccessTransition;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\Staff\StaffFixtures;

const STAFF_MEMBER_ID = '01930000-0000-7000-8000-0000000000c1';
const STAFF_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';
const STAFF_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

it('makes the account that registered a business its owner', function () {
    $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

    $owner = StaffMember::registerOwner(
        id: STAFF_MEMBER_ID,
        businessId: STAFF_BUSINESS_ID,
        accountId: STAFF_ACCOUNT_ID,
        now: $now,
    );

    expect($owner->id)->toBe(STAFF_MEMBER_ID)
        ->and($owner->businessId)->toBe(STAFF_BUSINESS_ID)
        ->and($owner->accountId)->toBe(STAFF_ACCOUNT_ID)
        ->and($owner->role())->toBe(StaffRole::Owner)
        ->and($owner->createdAt)->toEqual($now);
});

it('adds somebody to a team with no ownership', function () {
    $member = StaffMember::register(
        id: STAFF_MEMBER_ID,
        businessId: STAFF_BUSINESS_ID,
        accountId: STAFF_ACCOUNT_ID,
        now: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    );

    expect($member->role())->toBe(StaffRole::Member)
        ->and($member->role())->not->toBe(StaffRole::Owner);
});

it('takes its creation instant from the caller rather than from real time', function () {
    $registeredAt = new DateTimeImmutable('2026-03-29T02:30:00+00:00');

    expect(StaffMember::registerOwner(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, $registeredAt)->createdAt)
        ->toEqual($registeredAt);
});

it('carries uuids for both neighbours, never a persistence key', function () {
    $owner = StaffMember::registerOwner(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, new DateTimeImmutable);

    expect($owner->businessId)->toBeString()
        ->and($owner->accountId)->toBeString();
});

it('rehydrates whatever role was stored', function (StaffRole $role) {
    $member = StaffMember::restore(
        id: STAFF_MEMBER_ID,
        businessId: STAFF_BUSINESS_ID,
        accountId: STAFF_ACCOUNT_ID,
        role: $role,
        createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
    );

    expect($member->role())->toBe($role)
        ->and($member->createdAt)->toEqual(new DateTimeImmutable('2025-05-01T08:30:00+00:00'));
})->with([
    'owner' => StaffRole::Owner,
    'staff' => StaffRole::Member,
    'no access' => StaffRole::NoAccess,
]);

it('skips the creation-time rules when restoring', function () {
    $member = StaffMember::restore('', '', '', StaffRole::Member, new DateTimeImmutable);

    expect($member->id)->toBe('')
        ->and($member->businessId)->toBe('')
        ->and($member->accountId)->toBe('');
});

function staffMemberWithRole(StaffRole $role): StaffMember
{
    return StaffMember::restore(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, $role, new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
}

describe('registering a team member at a level', function () {
    it('registers a member at the level they were invited with', function (StaffRole $role) {
        $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $member = StaffMember::register(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, $now, $role);

        expect($member->role())->toBe($role)
            ->and($member->id)->toBe(STAFF_MEMBER_ID)
            ->and($member->businessId)->toBe(STAFF_BUSINESS_ID)
            ->and($member->accountId)->toBe(STAFF_ACCOUNT_ID)
            ->and($member->createdAt)->toEqual($now);
    })->with([
        'staff' => StaffRole::Member,
        'no access' => StaffRole::NoAccess,
    ]);

    it('refuses to register a second owner through the invitation path', function () {
        expect(fn () => StaffMember::register(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, new DateTimeImmutable, StaffRole::Owner))
            ->toThrow(InvalidTeamLevel::class, 'The owner level belongs to whoever registered the business and cannot be given.');
    });
});

describe('changing the level of a member', function () {
    it('moves the member to the new level and names the change of access', function (StaffRole $from, StaffRole $to, AccessTransition $transition) {
        $member = staffMemberWithRole($from);

        expect($member->changeRole($to))->toBe($transition)
            ->and($member->role())->toBe($to);
    })->with([
        'granting access' => [StaffRole::NoAccess, StaffRole::Member, AccessTransition::Granted],
        'revoking access' => [StaffRole::Member, StaffRole::NoAccess, AccessTransition::Revoked],
        'keeping staff' => [StaffRole::Member, StaffRole::Member, AccessTransition::Unchanged],
        'keeping no access' => [StaffRole::NoAccess, StaffRole::NoAccess, AccessTransition::Unchanged],
    ]);

    it('never changes the level of the owner, whatever the new level', function (StaffRole $to) {
        $owner = staffMemberWithRole(StaffRole::Owner);

        expect(fn () => $owner->changeRole($to))
            ->toThrow(OwnerLevelIsFixed::class, 'Staff member ['.STAFF_MEMBER_ID.'] owns the business, and that level cannot change.')
            ->and($owner->role())->toBe(StaffRole::Owner);
    })->with([
        'to staff' => StaffRole::Member,
        'to no access' => StaffRole::NoAccess,
        'to owner' => StaffRole::Owner,
    ]);

    it('never promotes a member to owner, and leaves the level untouched', function (StaffRole $from) {
        $member = staffMemberWithRole($from);

        expect(fn () => $member->changeRole(StaffRole::Owner))->toThrow(InvalidTeamLevel::class)
            ->and($member->role())->toBe($from);
    })->with([
        'from staff' => StaffRole::Member,
        'from no access' => StaffRole::NoAccess,
    ]);
});

describe('removing a member', function () {
    it('lets anyone but the owner be removed', function (StaffRole $role) {
        expect(fn () => staffMemberWithRole($role)->ensureRemovable())->not->toThrow(Throwable::class);
    })->with([
        'staff' => StaffRole::Member,
        'no access' => StaffRole::NoAccess,
    ]);

    it('refuses to remove the owner', function () {
        expect(fn () => staffMemberWithRole(StaffRole::Owner)->ensureRemovable())
            ->toThrow(OwnerCannotBeRemoved::class, 'Staff member ['.STAFF_MEMBER_ID.'] owns the business and cannot be removed from it.');
    });
});

describe('a pending invitation', function () {
    it('is pending only for a staff member whose account still holds the temporary password', function (StaffRole $role, bool $awaitingPasswordChange, bool $pending) {
        $account = StaffFixtures::account(id: STAFF_ACCOUNT_ID, awaitingPasswordChange: $awaitingPasswordChange);

        expect(staffMemberWithRole($role)->hasPendingInvitation($account))->toBe($pending);
    })->with([
        'staff awaiting a password change' => [StaffRole::Member, true, true],
        'staff who already chose a password' => [StaffRole::Member, false, false],
        'no access awaiting a password change' => [StaffRole::NoAccess, true, false],
        'no access with a password' => [StaffRole::NoAccess, false, false],
        'the owner awaiting a password change' => [StaffRole::Owner, true, false],
        'the owner with a password' => [StaffRole::Owner, false, false],
    ]);

    it('lets an invitation that is still pending be resent', function () {
        $account = StaffFixtures::account(id: STAFF_ACCOUNT_ID, awaitingPasswordChange: true);

        expect(fn () => staffMemberWithRole(StaffRole::Member)->ensureInvitationPending($account))->not->toThrow(Throwable::class);
    });

    it('refuses to resend an invitation that is not pending', function (StaffRole $role, bool $awaitingPasswordChange) {
        $account = StaffFixtures::account(id: STAFF_ACCOUNT_ID, awaitingPasswordChange: $awaitingPasswordChange);

        expect(fn () => staffMemberWithRole($role)->ensureInvitationPending($account))
            ->toThrow(TeamInvitationNotPending::class, 'Staff member ['.STAFF_MEMBER_ID.'] has no invitation waiting to be accepted.');
    })->with([
        'already accepted' => [StaffRole::Member, false],
        'no access' => [StaffRole::NoAccess, true],
        'the owner' => [StaffRole::Owner, true],
    ]);
});

describe('the invitation a member is owed', function () {
    it('owes a staff member one invitation carrying the uuids and the temporary password', function () {
        $events = staffMemberWithRole(StaffRole::Member)->invitationFor('Tmp-Pa55word!');

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(TeamMemberInvited::class)
            ->and($events[0]->staffMemberId)->toBe(STAFF_MEMBER_ID)
            ->and($events[0]->businessId)->toBe(STAFF_BUSINESS_ID)
            ->and($events[0]->accountId)->toBe(STAFF_ACCOUNT_ID)
            ->and($events[0]->temporaryPassword)->toBe('Tmp-Pa55word!');
    });

    it('owes a staff member who already had an account an invitation with no password', function () {
        $events = staffMemberWithRole(StaffRole::Member)->invitationFor(null);

        expect($events)->toHaveCount(1)
            ->and($events[0]->temporaryPassword)->toBeNull();
    });

    it('owes a no access member nothing, so they are never told they can sign in', function (?string $temporaryPassword) {
        expect(staffMemberWithRole(StaffRole::NoAccess)->invitationFor($temporaryPassword))->toBe([]);
    })->with([
        'with a password' => 'Tmp-Pa55word!',
        'without one' => null,
    ]);
});
