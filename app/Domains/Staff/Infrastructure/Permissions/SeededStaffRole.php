<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

/**
 * Fixed rather than auto-assigned because a database guarantee is written in
 * terms of it: the partial unique index on model_has_roles that keeps an account
 * to a single owner role names role_id = OWNER_ID literally, since an index
 * predicate cannot look a name up. Change this number, or seed the role some
 * other way, and that index silently starts protecting a role nobody holds.
 *
 * config/authorization.php reads it back, so the catalogue and the index agree
 * on one number rather than two literals that happen to match today.
 *
 * There is no constant for the staff role, and there must not be: staff has no
 * global row at all, only one clone per business.
 */
final class SeededStaffRole
{
    public const OWNER_ID = 1;
}
