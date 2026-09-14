<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Exists so that exactly one class knows spatie/laravel-permission is here: the
 * layers above speak StaffRole and have never heard of a role row.
 *
 * Roles are matched by name, which is StaffRole's own value, so the seeded
 * primary keys stay a detail of the seeder and the index that needs them.
 */
final class StaffRoleAssignments
{
    /** Mirrors config/permission.php, named here so EloquentBusinessMembership's join need not spell them out again. */
    public const ASSIGNMENTS_TABLE = 'model_has_roles';

    public const ROLES_TABLE = 'roles';

    public const TEAM_COLUMN = 'business_id';

    /**
     * Sync rather than assign, so a membership that changes role does not keep
     * the permissions of the old one. With teams on, the sync reaches only the
     * roles held at this business.
     */
    public function assign(User $account, int $businessKey, StaffRole $role): void
    {
        $this->withTeam($businessKey, static fn () => $account->syncRoles($role->value));
    }

    /**
     * A name that is not one of StaffRole's raises: a role row nobody can name
     * is a broken seed, and guessing which one was meant would hide it.
     */
    public function roleFor(User $account, int $businessKey): ?StaffRole
    {
        $name = $this->assignmentsOf($account)
            ->where(self::ASSIGNMENTS_TABLE.'.'.self::TEAM_COLUMN, $businessKey)
            ->value(self::ROLES_TABLE.'.name');

        if ($name === null) {
            return null;
        }

        return StaffRole::from((string) $name);
    }

    /**
     * Queried straight off the pivot rather than through Spatie's API, because
     * the question is deliberately unscoped: the rule it answers - one owned
     * business per account - is platform-wide, while every Spatie read is
     * scoped to the current team.
     */
    public function ownsAnyBusiness(User $account): bool
    {
        return $this->assignmentsOf($account)
            ->where(self::ROLES_TABLE.'.name', StaffRole::Owner->value)
            ->exists();
    }

    private function assignmentsOf(User $account): Builder
    {
        return DB::table(self::ASSIGNMENTS_TABLE)
            ->join(
                self::ROLES_TABLE,
                self::ROLES_TABLE.'.id',
                '=',
                self::ASSIGNMENTS_TABLE.'.role_id',
            )
            ->where(self::ASSIGNMENTS_TABLE.'.model_type', $account->getMorphClass())
            ->where(self::ASSIGNMENTS_TABLE.'.model_id', $account->getKey());
    }

    /**
     * Spatie reads the current team id from a registrar that lives for the whole
     * request, so the previous team is put back: setting it and walking away
     * would re-scope role checks the caller makes later.
     */
    private function withTeam(int $businessKey, callable $work): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($businessKey);

        try {
            $work();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
