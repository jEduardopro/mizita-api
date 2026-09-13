<?php

declare(strict_types=1);

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\StaffRole;

/*
| Pure PHP. This row is what makes an account a business user, so it carries the
| public uuids of both neighbours - the int keys the schema joins on never reach
| an entity.
*/

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
    // A named constructor per role rather than one create() taking a StaffRole:
    // the call site should read as what it is.
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
    // The int primary keys the schema joins on stay in the repository adapter.
    $owner = StaffMember::registerOwner(STAFF_MEMBER_ID, STAFF_BUSINESS_ID, STAFF_ACCOUNT_ID, new DateTimeImmutable);

    expect($owner->businessId)->toBeString()
        ->and($owner->accountId)->toBeString();
});

it('rehydrates whatever role was stored', function (StaffRole $role) {
    // restore() reproduces the row; which role it holds is a fact already
    // written, not a decision being made again.
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
]);

it('skips the creation-time rules when restoring', function () {
    // Nothing here is re-checked: the row was valid when it was written, and a
    // membership that cannot be loaded is worse than one that looks odd.
    $member = StaffMember::restore('', '', '', StaffRole::Member, new DateTimeImmutable);

    expect($member->id)->toBe('')
        ->and($member->businessId)->toBe('')
        ->and($member->accountId)->toBe('');
});
