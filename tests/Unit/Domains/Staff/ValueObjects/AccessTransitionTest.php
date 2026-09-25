<?php

declare(strict_types=1);

use App\Domains\Staff\ValueObjects\AccessTransition;
use App\Domains\Staff\ValueObjects\StaffRole;

it('names the change of access a move between two roles makes', function (StaffRole $from, StaffRole $to, AccessTransition $transition) {
    expect(AccessTransition::between($from, $to))->toBe($transition);
})->with([
    'no access to staff' => [StaffRole::NoAccess, StaffRole::Member, AccessTransition::Granted],
    'no access to owner' => [StaffRole::NoAccess, StaffRole::Owner, AccessTransition::Granted],
    'staff to no access' => [StaffRole::Member, StaffRole::NoAccess, AccessTransition::Revoked],
    'owner to no access' => [StaffRole::Owner, StaffRole::NoAccess, AccessTransition::Revoked],
    'staff to staff' => [StaffRole::Member, StaffRole::Member, AccessTransition::Unchanged],
    'no access to no access' => [StaffRole::NoAccess, StaffRole::NoAccess, AccessTransition::Unchanged],
    'owner to owner' => [StaffRole::Owner, StaffRole::Owner, AccessTransition::Unchanged],
    'staff to owner' => [StaffRole::Member, StaffRole::Owner, AccessTransition::Unchanged],
    'owner to staff' => [StaffRole::Owner, StaffRole::Member, AccessTransition::Unchanged],
]);
