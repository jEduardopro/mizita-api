<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

/**
 * The primary keys StaffRoleSeeder gives the two membership roles.
 *
 * Fixed rather than auto-assigned because a database guarantee is written in
 * terms of one of them: the partial unique index on model_has_roles that keeps
 * an account to a single owner role names role_id = OWNER_ID literally, since
 * an index predicate cannot look a name up. Change these numbers, or seed the
 * roles some other way, and that index silently starts protecting a role
 * nobody holds.
 *
 * Infrastructure, deliberately: a Spatie primary key is a persistence detail,
 * and StaffRole stays the domain's vocabulary.
 */
final class SeededStaffRole
{
    public const OWNER_ID = 1;

    public const MEMBER_ID = 2;
}
